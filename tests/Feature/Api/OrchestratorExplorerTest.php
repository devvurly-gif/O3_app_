<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Sixième lot de lectures : recherche, documents et achats d'un tiers, mouvements d'un produit, brouillons oubliés,
 * derniers documents, nouveautés, comparaison de périodes, jours et heures, stock par catégorie, villes, connexions.
 * Lecture seule, sans modèle de langage.
 */
class OrchestratorExplorerTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));   // mercredi 14/10
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas', 'tp_Role' => 'customer', 'tp_city' => 'Casablanca', 'tp_status' => true]);
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

    private function doc(string $type, string $status, string $day, float $ttc, float $due = 0, ?string $ref = null, ?ThirdPartner $tp = null): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => $type, 'status' => $status, 'issued_at' => $day, 'thirdPartner_id' => ($tp ?? $this->atlas)->id, 'reference' => $ref ?? 'D-' . fake()->unique()->numerify('####')]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc, 'amount_paid' => $ttc - $due, 'amount_due' => $due]);

        return $d;
    }

    public function test_search_third_party_documents_and_products_bought(): void
    {
        $p = $this->product('Perceuse 18V', 'PRC18');
        $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 1200, 1200, 'FV-0001');
        $paid = $this->doc('InvoiceSale', 'paid', '2026-09-01', 500, 0, 'FV-0002');
        $this->doc('InvoiceSale', 'cancelled', '2026-09-02', 999, 0, 'FV-0003');
        $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 321, 321, 'FV-AUTRE', ThirdPartner::factory()->create(['tp_title' => 'Autre Client']));
        $line = DocumentLigne::factory()->create(['document_header_id' => $paid->id, 'product_id' => $p->id, 'designation' => 'Perceuse 18V', 'quantity' => 3, 'status' => 'active', 'line_type' => 'product']);
        DB::table('document_lignes')->where('id', $line->id)->update(['total_ligne_ht' => 900]);

        $search = $this->say('cherche perceuse');
        $this->assertStringContainsString('Produits :', $search);
        $this->assertStringContainsString('Perceuse 18V (PRC18)', $search);
        $this->assertStringNotContainsString('Tiers :', $search);
        $this->assertStringContainsString('Client Atlas (client)', $this->say('trouve atlas'));
        $this->assertStringContainsString('Facture FV-0001 — confirmé — Client Atlas', $this->say('cherche FV-0001'));
        $this->assertStringContainsString('Aucun produit, tiers ni document', $this->say('cherche zzzzz'));

        $docs = $this->say('factures du client atlas');
        $this->assertStringContainsString('2 document(s) de Client Atlas', $docs);          // l'annulée et celle d'un autre client sont exclues
        $this->assertStringContainsString('FV-0001', $docs);
        $this->assertStringNotContainsString('FV-0003', $docs);
        $this->assertStringNotContainsString('FV-AUTRE', $docs);

        $unpaid = $this->say('factures impayées du client atlas');
        $this->assertStringContainsString('1 document(s) impayé(s) de Client Atlas, 1 200,00 MAD dus', $unpaid);
        $this->assertStringNotContainsString('FV-0002', $unpaid);

        $bought = $this->say('produits achetés par le client atlas');
        $this->assertStringContainsString('Perceuse 18V — 3 pièce(s), 900,00 MAD', $bought);
        $this->assertStringContainsString('Je ne trouve aucun tiers', $this->say('factures du client inexistant'));
    }

    public function test_product_movements_stock_value_by_category_and_stale_drafts(): void
    {
        $wh = Warehouse::factory()->create(['wh_title' => 'Dépôt']);
        $p = $this->product('Perceuse', 'PRC1');
        $q = $this->product('Marteau', 'MRT1');
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $p->id, 'stockLevel' => 10, 'wh_average' => 30]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $q->id, 'stockLevel' => 20, 'wh_average' => 5]);
        DB::table('stock_mouvements')->insert([
            ['product_id' => $p->id, 'warehouse_id' => $wh->id, 'direction' => 'out', 'reason' => 'sale', 'quantity' => 2, 'unit_cost' => 30, 'stock_before' => 12, 'stock_after' => 10, 'status' => 'applied', 'user_id' => $this->admin->id, 'document_reference' => 'FV-0001', 'created_at' => '2026-10-10 09:00:00', 'updated_at' => now()],
            ['product_id' => $p->id, 'warehouse_id' => $wh->id, 'direction' => 'in', 'reason' => 'purchase', 'quantity' => 99, 'unit_cost' => 30, 'stock_before' => 0, 'stock_after' => 99, 'status' => 'cancelled', 'user_id' => $this->admin->id, 'document_reference' => null, 'created_at' => '2026-10-11 09:00:00', 'updated_at' => now()],
        ]);

        $moves = $this->say('mouvements du produit PRC1');
        $this->assertStringContainsString('-2 (sale) — Dépôt — FV-0001 — stock après : 10 — Karim Admin', $moves);
        $this->assertStringNotContainsString('99', $moves);

        $byCat = $this->say('valeur du stock par catégorie');
        $this->assertStringContainsString('Cat PRC1 — 300,00 MAD (75 %), 1 produit(s)', $byCat);
        $this->assertStringContainsString('Cat MRT1 — 100,00 MAD (25 %), 1 produit(s)', $byCat);

        $old = $this->doc('QuoteSale', 'draft', '2026-09-20', 100, 0, 'DV-OLD');
        DB::table('document_headers')->where('id', $old->id)->update(['created_at' => '2026-09-20 10:00:00']);
        $this->doc('QuoteSale', 'draft', '2026-10-13', 100, 0, 'DV-NEW');
        $drafts = $this->say('brouillons anciens');
        $this->assertStringContainsString('1 brouillon(s) de plus de 7 jour(s)', $drafts);
        $this->assertStringContainsString('Devis DV-OLD', $drafts);
        $this->assertStringNotContainsString('DV-NEW', $drafts);
    }

    public function test_latest_documents_newcomers_and_comparison(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-10-02', 1200);        // ce mois
        $this->doc('InvoiceSale', 'confirmed', '2026-10-09', 300);
        $this->doc('InvoiceSale', 'confirmed', '2026-09-03', 600);         // mois dernier, jours 1 → 14
        $this->doc('InvoiceSale', 'confirmed', '2026-09-20', 5000);        // mois dernier, hors fenêtre comparable
        $paid = $this->doc('InvoiceSale', 'paid', '2026-10-03', 100, 0, 'FV-PAY');
        Payment::factory()->create(['document_header_id' => $paid->id, 'amount' => 100, 'method' => 'cash', 'paid_at' => '2026-10-03']);

        $latest = $this->say('derniers documents créés');
        $this->assertStringContainsString('Les 5 derniers documents créés', $latest);
        $this->assertStringContainsString('Facture FV-PAY — payé', $latest);

        $compare = $this->say('compare ce mois au mois dernier');
        $this->assertStringContainsString('ce mois (01/10 → 14/10) et le mois dernier (même nombre de jours) (01/09/2026 → 14/09/2026)', $compare);
        $this->assertStringContainsString('Chiffre d\'affaires TTC : 1 600,00 MAD contre 600,00 MAD (+167 %)', $compare);
        $this->assertStringContainsString('Nombre de ventes : 3 contre 1 (+200 %)', $compare);
        $this->assertStringContainsString('Encaissements : 100,00 MAD contre 0,00 MAD (nouveau)', $compare);

        $this->product('Nouveau produit', 'NEW1');
        ThirdPartner::factory()->create(['tp_title' => 'Nouveau Client', 'tp_Role' => 'customer']);
        $this->assertStringContainsString('Nouveau produit (NEW1)', $this->say('nouveaux produits du mois'));
        $this->assertStringContainsString('Nouveau Client (client)', $this->say('nouveaux clients du mois'));
    }

    public function test_weekday_peak_hours_cities_and_last_logins(): void
    {
        $this->doc('InvoiceSale', 'confirmed', '2026-10-12', 100);        // lundi
        $this->doc('InvoiceSale', 'confirmed', '2026-10-13', 300);        // mardi
        $weekday = $this->say('ventes par jour de la semaine');
        $this->assertStringContainsString('Lundi', $weekday);
        $this->assertMatchesRegularExpression('/Mardi\s+█+ 300,00 MAD \(1\)/u', $weekday);
        $this->assertMatchesRegularExpression('/Samedi\s+ 0,00 MAD \(0\)/u', $weekday);

        $ticket = $this->doc('TicketSale', 'paid', '2026-10-14', 50);
        DB::table('document_headers')->where('id', $ticket->id)->update(['created_at' => '2026-10-14 08:30:00']);   // heure de la base ; l'entreprise est à +1 h
        $peak = $this->say('heures de pointe');
        $this->assertMatchesRegularExpression('/\d\d h █+ 1 ticket\(s\), 50,00 MAD/u', $peak);

        ThirdPartner::factory()->create(['tp_title' => 'Client Rabat', 'tp_Role' => 'customer', 'tp_city' => 'Casablanca', 'tp_status' => true]);
        $cities = $this->say('clients par ville');
        $this->assertStringContainsString('Casablanca — 2 (2 client(s), 0 fournisseur(s))', $cities);

        DB::table('personal_access_tokens')->insert(['tokenable_type' => User::class, 'tokenable_id' => $this->admin->id, 'name' => 'web', 'token' => hash('sha256', 'secret-token-value'), 'abilities' => '["*"]', 'last_used_at' => '2026-10-14 09:00:00', 'created_at' => now(), 'updated_at' => now()]);
        $logins = $this->say('dernières connexions');
        $this->assertStringContainsString('Karim Admin — 14/10/2026 09:00', $logins);
        $this->assertStringNotContainsString('secret-token-value', $logins);
        $this->assertStringNotContainsString(hash('sha256', 'secret-token-value'), $logins);
    }

    public function test_orders_and_earlier_commands_are_not_swallowed(): void
    {
        $this->product('Marteau', 'MRT1');
        $this->assertStringContainsString('agent stocks', mb_strtolower($this->say('prépare un inventaire')));
        $this->assertStringContainsString('Jadever', $this->say('cherche les photos Jadever'));                // la recherche de photos garde son sens, elle n'est pas une recherche de mot
        $this->assertStringContainsString('Aucun stock en entrepôt', $this->say('valeur du stock'));          // lot 2, inchangé
        $this->assertStringContainsString('Client Atlas (client)', $this->say('fiche du client atlas'));        // lot 5, inchangé
    }
}
