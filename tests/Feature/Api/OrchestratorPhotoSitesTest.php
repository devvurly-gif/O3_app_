<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Agents\PhotoSites;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les sites autorisés pour la recherche de photos : les ajouter (au clic), les retirer, chercher sur un site à modèle
 * d'adresse puis par IA, et ne jamais sortir du domaine autorisé. Le web est simulé : aucune requête réelle ne part.
 */
class OrchestratorPhotoSitesTest extends TestCase
{
    use RefreshTenantDatabase;

    private const SEARCH = 'https://www.quincaillerie-atlas.ma/recherche?q=PRC18';
    private const PAGE = 'https://www.quincaillerie-atlas.ma/produit/prc18-perceuse';
    private const IMG = 'https://cdn.quincaillerie-atlas.ma/img/prc18.jpg';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        Storage::fake('public');
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function jpeg(int $side = 300): string
    {
        $im = imagecreatetruecolor($side, $side);
        ob_start();
        imagejpeg($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    private function product(string $sku = 'PRC18', string $title = 'Perceuse 18V'): Product
    {
        return Product::factory()->create(['p_sku' => $sku, 'p_title' => $title, 'p_status' => true, 'category_id' => Category::factory()->create()->id]);
    }

    /** Autorise le site de test (par le chat, avec le clic). */
    private function authorizeSite(string $phrase = 'autorise le site https://www.quincaillerie-atlas.ma/recherche?q={ref}'): void
    {
        $event = AgentEvent::where('type', 'catalogue_site')->latest('id')->first();
        $proposal = $this->say($phrase);
        $this->assertStringContainsString('Autoriser le site « quincaillerie-atlas.ma »', $proposal['body']);
        $id = AgentEvent::where('type', 'catalogue_site')->latest('id')->firstOrFail()->id;
        $this->assertSame("applique le lot #{$id}", $proposal['suggestions'][0]['text']);
        $this->say("applique le lot #{$id}");
        $this->assertNotNull($event ?? true);
    }

    private function fakeWeb(array $over = []): void
    {
        Http::swap(new Factory());
        $pages = array_merge([
            self::SEARCH => Http::response('<html><body><a href="/produit/prc18-perceuse">Perceuse PRC18</a><a href="/autre">Autre</a></body></html>', 200, ['Content-Type' => 'text/html']),
            self::PAGE => Http::response('<html><head><title>Perceuse 18V PRC18</title><meta property="og:image" content="' . self::IMG . '"></head><body><h1>Perceuse 18V — réf. PRC18</h1></body></html>', 200, ['Content-Type' => 'text/html']),
            self::IMG => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ], $over);
        Http::fake(fn (Request $r) => $pages[$r->url()] ?? Http::response('introuvable', 404));
    }

    // ── L'adresse ────────────────────────────────────────────────────

    public function test_site_addresses_are_validated(): void
    {
        $sites = new PhotoSites();

        $ok = $sites->parse('autorise le site https://www.Quincaillerie-Atlas.ma/recherche?q={ref}');
        $this->assertTrue($ok['ok']);
        $this->assertSame('quincaillerie-atlas.ma', $ok['domain']);
        $this->assertSame('https://www.Quincaillerie-Atlas.ma/recherche?q={ref}', $ok['template']);

        $bare = $sites->parse('ajoute le site quincaillerie-atlas.ma');
        $this->assertTrue($bare['ok']);
        $this->assertNull($bare['template']);                       // sans modèle : par IA seulement

        foreach ([
            'autorise le site http://exemple.ma/recherche?q={ref}'  => 'https',
            'autorise le site https://192.168.1.10/x?q={ref}'       => 'public',
            'autorise le site https://localhost/x?q={ref}'          => 'public',
            'autorise le site https://intranet.local/x?q={ref}'     => 'public',
            'autorise le site https://serveur/x?q={ref}'            => 'public',
            'autorise le site https://user:pass@exemple.ma/x'        => 'identifiant',
            'autorise le site https://exemple.ma:8080/x?q={ref}'    => 'port',
            'autorise le site https://{ref}.exemple.ma/'            => 'dans le nom du site',
            'autorise le site https://www.jadevermall.com/ma'       => "d'office",
            'autorise le site'                                      => "pas d'adresse",
        ] as $phrase => $why) {
            $r = $sites->parse($phrase);
            $this->assertFalse($r['ok'], $phrase);
            $this->assertStringContainsStringIgnoringCase($why, $r['error'], $phrase);
        }

        $this->assertTrue(PhotoSites::within('cdn.exemple.ma', 'exemple.ma'));
        $this->assertTrue(PhotoSites::within('exemple.ma', 'exemple.ma'));
        $this->assertFalse(PhotoSites::within('exemple.ma.evil.com', 'exemple.ma'));
        $this->assertFalse(PhotoSites::within('evilexemple.ma', 'exemple.ma'));
    }

    // ── La gestion dans le chat ──────────────────────────────────────

    public function test_a_site_is_added_only_at_the_click_listed_and_removed(): void
    {
        $list = $this->say('sites autorisés pour les photos')['body'];
        $this->assertStringContainsString('jadevermall.com/ma — d\'office', $list);

        $proposal = $this->say('autorise le site https://www.quincaillerie-atlas.ma/recherche?q={ref}');
        $this->assertSame([], (new PhotoSites())->custom());                       // rien n'est autorisé avant le clic
        $id = AgentEvent::where('type', 'catalogue_site')->firstOrFail()->id;
        $this->assertStringContainsString("lot #{$id}", $proposal['body']);
        $this->assertStringContainsString('images du domaine quincaillerie-atlas.ma', $proposal['body']);

        $this->assertStringContainsString('est autorisé', $this->say("applique le lot #{$id}")['body']);
        $sites = (new PhotoSites())->custom();
        $this->assertCount(1, $sites);
        $this->assertSame('Karim Admin', $sites[0]['added_by']);
        $this->assertStringContainsString('quincaillerie-atlas.ma — recherche par modèle d\'adresse, puis IA', $this->say('mes sites autorisés')['body']);

        $this->assertStringContainsString('déjà autorisé', $this->say('autorise le site https://quincaillerie-atlas.ma/x?q={ref}')['body']);
        $this->assertStringContainsString('Seules les adresses https', $this->say('autorise le site http://exemple.ma/x?q={ref}')['body']);

        $other = $this->say('autorise le site autre-site.ma');                      // sans modèle
        $oid = AgentEvent::where('type', 'catalogue_site')->latest('id')->firstOrFail()->id;
        $this->assertStringContainsString("L'IA n'est pas activée", $other['body']);
        $this->say("ignore le lot #{$oid}");
        $this->assertCount(1, (new PhotoSites())->custom());                        // refusé : rien n'est ajouté

        $this->assertStringContainsString("n'est plus autorisé", $this->say('retire le site quincaillerie-atlas.ma')['body']);
        $this->assertSame([], (new PhotoSites())->custom());
        $this->assertStringContainsString('Quel site retirer', $this->say('retire le site inconnu.ma')['body']);
    }

    // ── La recherche par modèle d'adresse ────────────────────────────

    public function test_a_template_site_finds_the_photo_with_proof_and_the_click_attaches_it(): void
    {
        $p = $this->product();
        $this->authorizeSite();
        $this->fakeWeb();

        $reply = $this->say('cherche les photos des produits sans photo');

        $this->assertStringContainsString('1 photo(s) trouvée(s) sur quincaillerie-atlas.ma', $reply['body']);
        $this->assertStringContainsString("quincaillerie-atlas.ma (modèle d'adresse)", $reply['body']);
        $this->assertStringContainsString('regardez bien chaque image', $reply['body']);
        $this->assertSame(0, ProductImage::count());                                 // aperçu seulement
        $event = AgentEvent::where('type', 'catalogue_photos')->firstOrFail();
        $this->assertSame('modele', $event->payload['items'][0]['method']);

        $this->say("applique le lot #{$event->id}");
        $this->assertSame(1, ProductImage::where('product_id', $p->id)->count());
    }

    public function test_the_image_must_come_from_the_authorized_domain_and_the_page_must_carry_the_reference(): void
    {
        $this->product();
        $this->authorizeSite();

        // Image hébergée ailleurs : refusée, et jamais téléchargée.
        $this->fakeWeb([self::PAGE => Http::response('<html><head><meta property="og:image" content="https://evil.example.com/prc18.jpg"></head><body>PRC18</body></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->assertStringContainsString("Je n'ai récupéré aucune photo", $this->say('cherche les photos')['body']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example.com'));

        // La page ne porte pas la référence : pas de preuve, pas de photo.
        $anon = 'https://cdn.quincaillerie-atlas.ma/img/12345.jpg';   // ni la page ni l'adresse de l'image ne portent la référence
        $this->fakeWeb([self::PAGE => Http::response('<html><head><meta property="og:image" content="' . $anon . '"></head><body>Une autre perceuse</body></html>', 200, ['Content-Type' => 'text/html']), $anon => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);
        $this->assertStringContainsString("Je n'ai récupéré aucune photo", $this->say('cherche les photos')['body']);

        // Une redirection vers un autre domaine n'est pas suivie.
        $this->fakeWeb([self::SEARCH => Http::response('', 302, ['Location' => 'https://evil.example.com/recherche?q=PRC18'])]);
        $this->assertStringContainsString("Je n'ai récupéré aucune photo", $this->say('cherche les photos')['body']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example.com'));
    }

    public function test_without_added_sites_only_jadever_products_are_searched(): void
    {
        $this->product('PRC18');                                                     // pas une référence Jadever
        Http::swap(new Factory());
        Http::fake();

        $this->assertStringContainsString('Aucun produit Jadever sans photo', $this->say('cherche les photos Jadever')['body']);
        Http::assertNothingSent();
    }

    // ── Le second recours : l'IA ─────────────────────────────────────

    private function fakeAi(array $input): void
    {
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        Http::swap(new Factory());
        Http::fake(function (Request $r) use ($input) {
            return match (true) {
                str_contains($r->url(), 'api.anthropic.com') => Http::response(['content' => [['type' => 'tool_use', 'id' => 't', 'name' => 'propose_image', 'input' => $input]], 'stop_reason' => 'tool_use']),
                $r->url() === self::PAGE => Http::response('<html><body><h1>Perceuse 18V — réf. PRC18</h1></body></html>', 200, ['Content-Type' => 'text/html']),
                $r->url() === self::IMG => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
                default => Http::response('introuvable', 404),
            };
        });
    }

    public function test_the_ai_is_the_second_resort_limited_to_authorized_sites_and_verified(): void
    {
        $this->product();
        $this->say('autorise le site quincaillerie-atlas.ma');
        $this->say('applique le lot #' . AgentEvent::where('type', 'catalogue_site')->firstOrFail()->id);
        $this->fakeAi(['found' => true, 'page_url' => self::PAGE, 'image_url' => self::IMG]);

        $reply = $this->say('cherche les photos');

        $this->assertStringContainsString('trouvée par IA', $reply['body']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.anthropic.com')
            && $r['tools'][0]['allowed_domains'] === ['quincaillerie-atlas.ma']                       // la recherche web est limitée au site autorisé
            && str_contains($r['messages'][0]['content'], 'PRC18'));
        $this->assertSame('ia', AgentEvent::where('type', 'catalogue_photos')->firstOrFail()->payload['items'][0]['method']);
    }

    public function test_an_ai_answer_outside_the_authorized_sites_or_without_the_reference_is_refused(): void
    {
        $this->product();
        $this->say('autorise le site quincaillerie-atlas.ma');
        $this->say('applique le lot #' . AgentEvent::where('type', 'catalogue_site')->firstOrFail()->id);

        $this->fakeAi(['found' => true, 'page_url' => 'https://evil.example.com/p', 'image_url' => 'https://evil.example.com/i.jpg']);
        $this->assertStringContainsString("Je n'ai récupéré aucune photo", $this->say('cherche les photos')['body']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example.com'));

        $this->fakeAi(['found' => true, 'page_url' => self::PAGE, 'image_url' => 'https://evil.example.com/i.jpg']);   // page autorisée, image ailleurs
        $this->assertStringContainsString("Je n'ai récupéré aucune photo", $this->say('cherche les photos')['body']);

        $this->fakeAi(['found' => false]);
        $this->assertStringContainsString("Je n'ai récupéré aucune photo", $this->say('cherche les photos')['body']);
    }

    public function test_the_ai_is_not_called_when_the_template_succeeds_or_when_it_is_off(): void
    {
        $this->product();
        $this->authorizeSite();

        // IA éteinte : seul le modèle d'adresse travaille ; rien ne part chez Anthropic.
        $this->fakeWeb();
        $this->say('cherche les photos');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));

        // IA allumée mais le modèle réussit : l'IA n'est pas appelée.
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        AgentEvent::where('type', 'catalogue_photos')->update(['status' => 'rejected']);
        $this->fakeWeb();
        $this->say('cherche les photos');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'anthropic'));
    }
}
