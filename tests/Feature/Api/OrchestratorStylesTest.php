<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use App\Services\Agents\PhraseNormalizer;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Les fautes de frappe, les abréviations, l'anglais et la darija transcrite : la phrase est remise en forme et
 * comprise par les règles, sans modèle de langage ; elle n'est essayée qu'après la phrase telle quelle, et un ordre
 * n'est jamais deviné.
 */
class OrchestratorStylesTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reply(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    public function test_the_normalizer_rewrites_typos_abbreviations_english_and_darija(): void
    {
        $c = fn (string $p) => PhraseNormalizer::canonical(Str::lower(Str::ascii($p)));

        $this->assertSame("chiffre d affaires du mois", $c('chifre d affaire du mois'));
        $this->assertSame('produits dormants', $c('produits dormans'));          // à égalité de distance : la forme qui finit comme le mot tapé
        $this->assertSame('clients inactifs', $c('cleints inactifs'));           // lettres inversées
        $this->assertSame('ventes d hier', $c('ventes dhier'));
        $this->assertSame('marge du mois', $c('marge du moi'));
        $this->assertSame('commandes en cours', $c('cmd en cours'));
        $this->assertSame('ma tresorerie', $c('tréso'));
        $this->assertSame('chiffre d affaires du mois', $c('TTC du mois'));
        $this->assertSame('ventes d aujourd hui', $c('sales today'));
        $this->assertSame('qui me doit de l argent', $c('who owes me money'));
        $this->assertSame('combien j ai vendu hier', $c('how much did I sell yesterday'));
        $this->assertSame('combien j ai vendu aujourd hui', $c('chhal bi3t lyoum'));
        $this->assertSame('qui me doit de l argent', $c('chkoun li 3ndo 3liya flous'));
        $this->assertSame('stock faible', $c('stock qalil'));
        $this->assertSame('argent en caisse', $c('chhal 3ndi fl caisse'));
        $this->assertSame('resume de la journee', $c('3tini resume dyal lyoum'));

        // Ce qui est déjà correct, un identifiant et un mot inconnu ne bougent pas.
        $this->assertSame('applique la proposition #5', $c('applique la proposition #5'));
        $this->assertSame('marche du mois', $c('marche du mois'));               // « marche » n'est pas « marge » : deux lettres d'écart sur un mot court
        $this->assertSame('atlas me doit combien', $c('atlas me doit combien'));
    }

    public function test_styled_phrases_get_the_right_answer_and_say_how_they_were_understood(): void
    {
        $cases = [
            ['chifre d affaire du mois', 'Ventes du mois'],
            ['stok bas', "Aucun produit n'est au seuil d'alerte"],
            ['cleints inactifs', 'Tous les clients actifs ont acheté'],
            ['ventes dhier', "Ventes d'hier"],
            ['sales today', "Ventes d'aujourd'hui"],
            ['overdue invoices', 'Aucune facture de vente échue'],
            ['who owes me money', 'Aucune facture de vente échue'],
            ['low stock', "Aucun produit n'est au seuil d'alerte"],
            ['daily summary', 'Le point du mercredi'],
            ['what should I validate', "Rien n'attend votre validation"],
            ['chhal bi3t lyoum', "Ventes d'aujourd'hui"],
            ['stock qalil', "Aucun produit n'est au seuil d'alerte"],
            ['chhal 3ndi fl caisse', 'Soldes de trésorerie'],
            ['tréso', 'Soldes de trésorerie'],
        ];
        foreach ($cases as [$phrase, $expected]) {
            $r = $this->reply($phrase);
            $this->assertStringContainsString($expected, $r['body'], $phrase);
        }
        Http::assertNothingSent();   // tout cela se comprend sans modèle de langage

        // L'administrateur voit comment sa phrase a été lue ; une phrase comprise telle quelle n'est pas annotée.
        $this->assertStringStartsWith("J'ai compris « ventes d hier ».", $this->reply('ventes dhier')['body']);
        $direct = $this->reply("ventes d'hier")['body'];
        $this->assertStringContainsString("Ventes d'hier", $direct);
        $this->assertStringNotContainsString("J'ai compris", $direct);
    }

    public function test_the_ai_is_only_asked_when_neither_the_phrase_nor_its_rewrite_is_understood(): void
    {
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        Http::swap(new Factory());
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'id' => 't', 'name' => 'route_request', 'input' => ['intent' => 'hors_sujet']]], 'stop_reason' => 'tool_use'])]);

        $this->assertStringContainsString("Ventes d'hier", $this->reply('ventes dhier')['body']);
        Http::assertNothingSent();                                    // la faute de frappe est corrigée sans appeler l'IA

        $this->reply('blablabla zorglub');
        Http::assertSentCount(1);                                     // une phrase inconnue part au modèle, une seule fois
    }

    public function test_orders_are_never_guessed_from_a_typo(): void
    {
        $this->assertStringContainsString('Je ne trouve pas la proposition #99999', $this->reply('applique la proposition #99999')['body']);
        $this->assertStringContainsString("Je n'ai pas compris", $this->reply('apliqu la proposisyon #99999')['body']);   // trop éloigné : rien n'est exécuté
        $this->assertSame(0, \App\Models\AgentEvent::where('status', 'done')->count());
    }
}
