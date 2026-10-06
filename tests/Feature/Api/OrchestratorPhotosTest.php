<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Agents\JadeverPhotoSource;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Cherche les photos Jadever » : le serveur va chercher la photo officielle sur jadevermall.com/ma, la vérifie et
 * la montre ; elle n'est rattachée au produit qu'au clic. Aucun appel réel : le site est simulé.
 */
class OrchestratorPhotosTest extends TestCase
{
    use RefreshTenantDatabase;

    private const IMG = 'https://res-de.togroup.com/stc/home_product/jadever/userfiles/1/images/photo/20260414222304455/';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function product(string $sku, string $title = 'Perceuse'): Product
    {
        return Product::factory()->create(['p_sku' => $sku, 'p_title' => $title, 'category_id' => Category::factory()->create()->id]);
    }

    private function jpeg(int $side = 300): string
    {
        $im = imagecreatetruecolor($side, $side);
        ob_start();
        imagejpeg($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** @param array<string, string> $catalog sku => url de l'image */
    private function siteKnows(array $catalog, ?string $imageBody = null): void
    {
        Http::swap(new Factory());
        Http::fake([
            'gatewayapi-de.rdmcenter.com/*' => function (Request $r) use ($catalog) {
                $kw = $r['keyword'];

                return Http::response(['code' => '0', 'message' => 'ok', 'data' => ['list' => isset($catalog[$kw]) ? [['productNo' => $kw, 'productName' => "Fiche {$kw}", 'productPics' => $catalog[$kw]]] : []]]);
            },
            'res-de.togroup.com/*' => Http::response($imageBody ?? $this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_it_finds_official_photos_for_jadever_products_only_and_shows_them_before_attaching(): void
    {
        $jd = $this->product('JDCDP5281', 'Perceuse à percussion');
        $other = $this->product('DEG123', 'Disque');
        $missing = $this->product('JDINCONNU', 'Inconnu');
        $this->siteKnows(['JDCDP5281' => self::IMG . 'JDCDP5281.jpg']);

        $reply = $this->say('cherche les photos Jadever');

        $this->assertStringContainsString('1 photo(s) officielle(s) trouvée(s)', $reply['body']);
        $this->assertStringContainsString('JDINCONNU', $reply['body']);          // sans photo sur le site
        $this->assertStringNotContainsString('DEG123', $reply['body']);          // pas une référence Jadever
        $event = AgentEvent::where('type', 'catalogue_photos')->firstOrFail();
        $this->assertSame('routed', $event->status);
        $this->assertSame("applique le lot #{$event->id}", $reply['suggestions'][0]['text']);
        $this->assertSame("/agents/orchestrateur/photos/{$event->id}/{$jd->id}", $reply['images'][0]['url']);
        $this->assertSame(0, ProductImage::count());                              // rien n'est rattaché avant le clic

        // Les en-têtes du site sont envoyés, et l'API n'est interrogée que pour les références Jadever.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'rdmcenter.com') && $r->header('domain') === ['www.jadevermall.com/ma']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'DEG123'));

        // L'aperçu est servi à l'administrateur, pour ce lot et ce produit seulement.
        $this->actingAs($this->admin, 'sanctum')->get("/api/agents/orchestrateur/photos/{$event->id}/{$jd->id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')->get("/api/agents/orchestrateur/photos/{$event->id}/{$other->id}")->assertNotFound();
    }

    public function test_applying_the_lot_attaches_the_photo_as_primary_and_removes_the_temporary_file(): void
    {
        $jd = $this->product('JDCDP5281');
        $this->siteKnows(['JDCDP5281' => self::IMG . 'JDCDP5281.jpg']);
        $this->say('cherche les photos Jadever');
        $event = AgentEvent::where('type', 'catalogue_photos')->firstOrFail();
        $path = $event->payload['items'][0]['path'];
        Storage::disk('local')->assertExists($path);

        $reply = $this->say("applique le lot #{$event->id}");

        $this->assertStringContainsString('1 photo(s) rattachée(s)', $reply['body']);
        $image = ProductImage::where('product_id', $jd->id)->firstOrFail();
        $this->assertTrue($image->isPrimary);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $image->url));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('done', $event->fresh()->status);

        // Valider de nouveau ne double rien, et l'aperçu a disparu.
        $this->say("applique le lot #{$event->id}");
        $this->assertSame(1, ProductImage::count());
        $this->actingAs($this->admin, 'sanctum')->get("/api/agents/orchestrateur/photos/{$event->id}/{$jd->id}")->assertNotFound();
    }

    public function test_a_product_that_got_a_photo_meanwhile_is_left_alone(): void
    {
        $jd = $this->product('JDCDP5281');
        $this->siteKnows(['JDCDP5281' => self::IMG . 'JDCDP5281.jpg']);
        $this->say('cherche les photos Jadever');
        $event = AgentEvent::where('type', 'catalogue_photos')->firstOrFail();
        ProductImage::create(['product_id' => $jd->id, 'url' => '/storage/products/manuelle.jpg', 'title' => 'm', 'isPrimary' => true]);

        $reply = $this->say("applique le lot #{$event->id}");

        $this->assertStringContainsString('0 photo(s) rattachée(s)', $reply['body']);
        $this->assertSame(1, ProductImage::count());
    }

    public function test_ignoring_the_lot_deletes_the_downloaded_files(): void
    {
        $this->product('JDCDP5281');
        $this->siteKnows(['JDCDP5281' => self::IMG . 'JDCDP5281.jpg']);
        $this->say('cherche les photos Jadever');
        $event = AgentEvent::where('type', 'catalogue_photos')->firstOrFail();
        $path = $event->payload['items'][0]['path'];

        $this->say("ignore le lot #{$event->id}");

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, ProductImage::count());
        $this->assertSame('rejected', $event->fresh()->status);
    }

    public function test_pending_photos_are_not_searched_twice(): void
    {
        $this->product('JDCDP5281');
        $this->siteKnows(['JDCDP5281' => self::IMG . 'JDCDP5281.jpg']);
        $this->say('cherche les photos Jadever');
        $reply = $this->say('cherche les photos Jadever');

        $this->assertStringContainsString('attendent déjà votre validation', $reply['body']);
        $this->assertSame(1, AgentEvent::where('type', 'catalogue_photos')->count());
    }

    public function test_an_image_outside_the_authorized_site_is_never_downloaded(): void
    {
        $this->product('JDCDP5281');
        $this->siteKnows(['JDCDP5281' => 'https://evil.example.com/jadever/JDCDP5281.jpg']);

        $reply = $this->say('cherche les photos Jadever');

        $this->assertStringContainsString("aucune photo", $reply['body']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example.com'));
        $this->assertSame(0, ProductImage::count());
    }

    public function test_a_non_image_or_too_small_image_is_refused(): void
    {
        $this->product('JDCDP5281');
        $this->siteKnows(['JDCDP5281' => self::IMG . 'a.jpg'], '<html>pas une image</html>');
        $this->assertStringContainsString('aucune photo', $this->say('cherche les photos Jadever')['body']);

        $this->siteKnows(['JDCDP5281' => self::IMG . 'a.jpg'], $this->jpeg(50));
        $this->assertStringContainsString('trop petite', $this->say('cherche les photos Jadever')['body']);
    }

    public function test_when_the_site_refuses_it_says_so_and_does_not_insist(): void
    {
        foreach (['JDA0001', 'JDA0002', 'JDA0003', 'JDA0004'] as $sku) {
            $this->product($sku);
        }
        Http::swap(new Factory());
        Http::fake(['gatewayapi-de.rdmcenter.com/*' => Http::response(['code' => '-4', 'message' => 'Domain name cannot be empty'])]);

        $reply = $this->say('cherche les photos Jadever');

        $this->assertTrue($reply['error']);
        $this->assertStringContainsString('a refusé la recherche', $reply['body']);
        $this->assertStringContainsString('déposer les photos', $reply['body']);
        Http::assertSentCount(2);   // abandon après deux refus : on n'insiste pas
        $this->assertSame(0, AgentEvent::where('type', 'catalogue_photos')->where('status', 'routed')->count());
    }

    public function test_the_photos_listing_offers_the_search_and_no_jadever_means_nothing_to_do(): void
    {
        $this->product('JDCDP5281');
        $this->assertSame('cherche les photos Jadever', $this->say('quels produits sont sans photo')['suggestions'][0]['text']);

        $source = new JadeverPhotoSource();
        $this->assertTrue(JadeverPhotoSource::isJadeverSku('JDCDP5281'));
        $this->assertFalse(JadeverPhotoSource::isJadeverSku('PS-1234'));
        $this->assertNull($source->download('https://res-de.togroup.com.evil.com/jadever/a.jpg'));
    }
}
