<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentRoutine;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\OrchestratorMessage;
use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\AnalysisAssistant;
use App\Services\Agents\RoutineRunner;
use App\Services\Agents\RoutineSteps;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Quatrième lot de lectures : classements, tiers, qualité du catalogue, stock, historique d'une fiche, actions des
 * agents ; et ces lectures comme étapes de routine. Lecture seule et sans modèle de langage.
 */
class OrchestratorAnalysisTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): string
    {
        $body = $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply.body');
        Http::assertNothingSent();

        return str_replace(["\u{202f}", "\u{a0}"], ' ', $body);
    }

    private function product(string $title, string $sku, array $over = []): Product
    {
        return Product::factory()->create(array_merge(['p_title' => $title, 'p_sku' => $sku, 'p_status' => true, 'category_id' => Category::factory()->create(['ctg_title' => "Cat {$sku}"])->id], $over));
    }

    private function sale(string $type, string $day, float $ttc, ThirdPartner $tp, ?User $by = null, ?int $session = null, string $ref = null): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => $type, 'status' => 'confirmed', 'issued_at' => $day, 'thirdPartner_id' => $tp->id, 'user_id' => ($by ?? $this->admin)->id, 'pos_session_id' => $session, 'reference' => $ref ?? 'D-' . fake()->unique()->numerify('####')]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc]);

        return $d;
    }

    private function line(DocumentHeader $d, Product $p, float $qty, float $ht): void
    {
        $l = DocumentLigne::factory()->create(['document_header_id' => $d->id, 'product_id' => $p->id, 'designation' => $p->p_title, 'quantity' => $qty, 'status' => 'active', 'line_type' => 'product']);
        DB::table('document_lignes')->where('id', $l->id)->update(['total_ligne_ht' => $ht]);   // le total est fixé tel quel : on teste la lecture, pas le calcul de la ligne
    }

    public function test_top_products_sellers_registers_and_best_customers(): void
    {
        $atlas = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas']);
        $bati = ThirdPartner::factory()->create(['tp_title' => 'Client Bati']);
        $seller = User::factory()->create(['name' => 'Samira Vendeuse']);
        $perceuse = $this->product('Perceuse', 'P1');
        $disque = $this->product('Disque', 'D1');
        $session = PosSession::factory()->create(['pos_terminal_id' => PosTerminal::factory()->create()->id, 'user_id' => $seller->id, 'opened_at' => '2026-10-10 08:00:00']);

        $a = $this->sale('InvoiceSale', '2026-10-05', 1200, $atlas);
        $this->line($a, $perceuse, 2, 1000);
        $b = $this->sale('TicketSale', '2026-10-06', 240, $bati, $seller, $session->id);
        $this->line($b, $disque, 10, 200);
        $this->sale('InvoiceSale', '2026-07-01', 500, $bati);    // trimestre précédent : hors période

        $top = $this->say('top 2 des produits vendus ce mois');
        $this->assertStringContainsString('1. Perceuse — 2 vendu(s), 1 000,00 MAD', $top);
        $this->assertStringContainsString('2. Disque — 10 vendu(s), 200,00 MAD', $top);

        $bySeller = $this->say('ventes du mois par vendeur');
        $this->assertStringContainsString('Karim Admin — 1 vente(s), 1 200,00 MAD', $bySeller);
        $this->assertStringContainsString('Samira Vendeuse — 1 vente(s), 240,00 MAD', $bySeller);

        $register = $this->say('ventes du mois par caisse');
        $this->assertStringContainsString('1 ticket(s) sur 1 session(s)', $register);
        $this->assertStringContainsString("Session #{$session->id} — Samira Vendeuse", $register);

        $best = $this->say('meilleurs clients du trimestre');
        $this->assertStringContainsString('1. Client Atlas — 1 vente(s), 1 200,00 MAD (83 %)', $best);
        $this->assertStringContainsString('2. Client Bati — 1 vente(s), 240,00 MAD (17 %)', $best);
    }

    public function test_inactive_suppliers_and_account_customers_to_invoice(): void
    {
        $recent = ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Actif', 'tp_Role' => 'supplier', 'tp_status' => true]);
        $old = ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Dormant', 'tp_Role' => 'supplier', 'tp_status' => true]);
        ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Jamais', 'tp_Role' => 'supplier', 'tp_status' => true]);
        $this->sale('InvoicePurchase', '2026-09-20', 300, $recent);
        $this->sale('InvoicePurchase', '2025-01-10', 300, $old);

        $inactive = $this->say('fournisseurs inactifs depuis un an');
        $this->assertStringContainsString('2 fournisseur(s) actif(s) sans facture d\'achat depuis 365 jour(s)', $inactive);
        $this->assertStringContainsString('Fournisseur Dormant — dernière facture le 10/01/2025', $inactive);
        $this->assertStringContainsString('Fournisseur Jamais — jamais facturé', $inactive);
        $this->assertStringNotContainsString('Fournisseur Actif', $inactive);

        $account = ThirdPartner::factory()->create(['tp_title' => 'Client en compte', 'tp_Role' => 'customer', 'tp_status' => true]);
        DB::table('third_partners')->where('id', $account->id)->update(['type_compte' => 'en_compte', 'frequence_facturation' => 'mensuelle']);
        $normal = ThirdPartner::factory()->create(['tp_title' => 'Client comptant', 'tp_Role' => 'customer', 'tp_status' => true]);
        $this->sale('DeliveryNote', '2026-10-02', 480, $account);
        $this->sale('DeliveryNote', '2026-10-03', 120, $account);
        $this->sale('DeliveryNote', '2026-10-03', 999, $normal);

        $toInvoice = $this->say('clients en compte à facturer ce mois');
        $this->assertStringContainsString('1 client(s) en compte avec des bons de livraison à facturer, pour 600,00 MAD', $toInvoice);
        $this->assertStringContainsString('Client en compte — 2 bon(s), 600,00 MAD depuis le 02/10/2026 (facturation mensuelle)', $toInvoice);
        $this->assertStringNotContainsString('Client comptant', $toInvoice);
    }

    public function test_catalogue_quality_vat_barcodes_categories_brands_and_price_lists(): void
    {
        $brand = Brand::factory()->create(['br_title' => 'Jadever']);
        $this->product('Normal', 'N1', ['p_taxRate' => 20, 'brand_id' => $brand->id, 'p_ean13' => '5901234123457']);   // clé valide
        $this->product('Taux réduit', 'N2', ['p_taxRate' => 10, 'brand_id' => null, 'p_ean13' => '5901234123450']);                          // clé fausse
        $this->product('Sans code', 'N3', ['p_taxRate' => 20, 'brand_id' => null, 'p_ean13' => null]);

        $vat = $this->say('produits avec une TVA inhabituelle');
        $this->assertStringContainsString('10 % — 1 produit(s)', $vat);
        $this->assertStringContainsString('Taux réduit (N2) — 10 %', $vat);

        $ean = $this->say('codes-barres invalides');
        $this->assertStringContainsString('1 code(s)-barres invalide(s) sur 2', $ean);
        $this->assertStringContainsString('Taux réduit (N2)', $ean);
        $this->assertTrue(AnalysisAssistant::validEan13('5901234123457'));
        $this->assertFalse(AnalysisAssistant::validEan13('590123412345'));

        $noBrand = $this->say('produits sans marque');
        $this->assertStringContainsString('2 produit(s) actif(s) sans marque', $noBrand);
        $this->assertStringNotContainsString('Normal (N1)', $noBrand);

        $byCat = $this->say('produits par catégorie');
        $this->assertStringContainsString('3 actif(s), 0 inactif(s)', $byCat);

        $listId = DB::table('price_lists')->insertGetId(['name' => 'Revendeur', 'channel' => 'all', 'is_default' => false, 'is_active' => true, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('price_list_items')->insert(['price_list_id' => $listId, 'product_id' => Product::where('p_sku', 'N1')->value('id'), 'price_ht' => 10, 'price_ttc' => 12, 'min_qty' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $gaps = $this->say('produits absents de la liste de prix revendeur');
        $this->assertStringContainsString('2 produit(s) actif(s) absent(s) de la liste « Revendeur »', $gaps);
        $this->assertStringNotContainsString('Normal (N1)', $gaps);
        $this->assertStringContainsString('Listes existantes : Revendeur', $this->say('produits absents de la liste de prix inconnue'));
    }

    public function test_pending_movements_adjustments_history_and_agent_actions(): void
    {
        $p = $this->product('Perceuse', 'P1');
        $wh = \App\Models\Warehouse::factory()->create()->id;
        $mv = fn (string $reason, string $status, string $at, float $qty = 3) => DB::table('stock_mouvements')->insert(['product_id' => $p->id, 'warehouse_id' => $wh, 'direction' => 'out', 'reason' => $reason, 'quantity' => $qty, 'unit_cost' => 10, 'stock_before' => 0, 'stock_after' => 0, 'status' => $status, 'user_id' => $this->admin->id, 'created_at' => $at, 'updated_at' => $at]);
        $mv('sale', 'pending', '2026-10-12 10:00:00');
        $mv('inventory_adjustment', 'applied', '2026-10-10 10:00:00', 4);
        $mv('inventory_adjustment', 'cancelled', '2026-10-11 10:00:00', 99);

        $pending = $this->say('mouvements de stock en attente');
        $this->assertStringContainsString('1 mouvement(s) de stock en attente', $pending);
        $this->assertStringContainsString('Perceuse (P1)', $pending);

        $adj = $this->say("ajustements d'inventaire récents");
        $this->assertStringContainsString('1 ajustement(s) de stock', $adj);
        $this->assertStringContainsString('par Karim Admin (1)', $adj);
        $this->assertStringNotContainsString('99', $adj);

        $doc = $this->sale('InvoiceSale', '2026-10-05', 100, ThirdPartner::factory()->create(), null, null, 'FV-HIST');
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'Facture confirmée', 'subject_type' => 'App\\Models\\DocumentHeader', 'subject_id' => $doc->id, 'event' => 'updated',
            'causer_type' => 'App\\Models\\User', 'causer_id' => $this->admin->id, 'properties' => json_encode(['secret' => 'CACHE']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $history = $this->say('qui a modifié la facture FV-HIST');
        $this->assertStringContainsString('Historique de InvoiceSale FV-HIST', $history);
        $this->assertStringContainsString('Karim Admin — Facture confirmée', $history);
        $this->assertStringNotContainsString('CACHE', $history);
        $this->assertStringContainsString('Je ne trouve ni document', $this->say('qui a modifié la facture INTROUVABLE-9'));

        AgentAction::create(['agent_id' => \App\Models\Agent::where('domain', 'stocks')->value('id'), 'event_id' => null, 'action' => 'inventory_sheet_prepared', 'level' => 'approval', 'input' => [], 'result' => []]);
        $actions = $this->say("actions des agents aujourd'hui");
        $this->assertStringContainsString('1 action(s) d\'agents', $actions);
        $this->assertStringContainsString('inventory_sheet_prepared', $actions);
    }

    public function test_read_steps_run_inside_a_routine_and_a_read_phrase_is_a_routine_request(): void
    {
        $this->assertArrayHasKey('point', RoutineSteps::KNOWN);
        $this->assertSame(['point', 'echues'], RoutineSteps::sanitize(['point', 'echues', 'inconnue']));

        $routine = AgentRoutine::create(['name' => 'Point du matin', 'steps' => ['point', 'echues'], 'schedule' => ['frequency' => 'daily', 'time' => '08:00'], 'is_active' => true, 'created_by' => $this->admin->id]);
        $out = app(RoutineRunner::class)->run($routine, 'planifiée');

        $this->assertSame('ok', $out['status']);
        $this->assertStringContainsString('Le point du mercredi 14 octobre 2026', $out['body']);
        $this->assertStringContainsString('Aucune facture de vente échue', $out['body']);
        $this->assertTrue(OrchestratorMessage::where('user_id', $this->admin->id)->where('body', 'like', '%Point du matin%')->exists());   // le compte rendu arrive dans la conversation

        // « chaque matin à 8 h, résume la journée » est une demande de routine (et non une lecture immédiate).
        Setting::set('agents', 'orchestrator_ai_enabled', 'false');
        $this->assertStringContainsString('planifier une routine', $this->say('chaque matin à 8 h, résume la journée'));
    }
}
