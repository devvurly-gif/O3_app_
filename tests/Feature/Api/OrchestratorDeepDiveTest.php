<?php

namespace Tests\Feature\Api;

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
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
 * Cinquième lot de lectures : fiches, marge réalisée, panier moyen, tendance, ventes par catégorie, devis, commandes,
 * retours, ruptures à venir, tickets annulés, flux et prévision de trésorerie. Lecture seule, sans modèle de langage.
 */
class OrchestratorDeepDiveTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Client Atlas', 'tp_Role' => 'customer', 'tp_phone' => '0600112233', 'tp_email' => 'atlas@test.ma', 'tp_Ice_Number' => '0012345', 'tp_status' => true]);
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
        return Product::factory()->create(array_merge(['p_title' => $title, 'p_sku' => $sku, 'p_status' => true, 'p_purchasePrice' => 60, 'p_salePrice' => 100, 'p_cost' => 0, 'category_id' => Category::factory()->create(['ctg_title' => "Cat {$sku}"])->id], $over));
    }

    private function doc(string $type, string $status, string $day, float $ttc, float $due = 0, ?string $dueAt = null, ?string $ref = null, ?ThirdPartner $tp = null): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => $type, 'status' => $status, 'issued_at' => $day, 'due_at' => $dueAt, 'thirdPartner_id' => ($tp ?? $this->atlas)->id, 'reference' => $ref ?? 'D-' . fake()->unique()->numerify('####')]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_tax' => round($ttc - $ttc / 1.2, 2), 'total_ttc' => $ttc, 'amount_paid' => $ttc - $due, 'amount_due' => $due]);

        return $d;
    }

    private function line(DocumentHeader $d, Product $p, float $qty, float $ht, float $price = 0): void
    {
        $l = DocumentLigne::factory()->create(['document_header_id' => $d->id, 'product_id' => $p->id, 'designation' => $p->p_title, 'quantity' => $qty, 'unit_price' => $price ?: $ht / $qty, 'discount_percent' => 0, 'status' => 'active', 'line_type' => 'product']);
        DB::table('document_lignes')->where('id', $l->id)->update(['total_ligne_ht' => $ht]);
    }

    public function test_product_third_party_and_document_cards(): void
    {
        $wh = Warehouse::factory()->create(['wh_title' => 'Dépôt principal']);
        $p = $this->product('Perceuse 18V', 'PRC18', ['p_ean13' => '5901234123457']);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $p->id, 'stockLevel' => 7, 'wh_average' => 55]);
        ProductImage::create(['product_id' => $p->id, 'url' => '/storage/a.jpg', 'title' => 'a', 'isPrimary' => true]);
        $inv = $this->doc('InvoiceSale', 'partial', '2026-10-05', 1200, 400, '2026-10-20', 'FV-0001');
        $this->line($inv, $p, 2, 1000, 500);
        Payment::factory()->create(['document_header_id' => $inv->id, 'amount' => 800, 'method' => 'cash', 'paid_at' => '2026-10-06']);
        $this->line($this->doc('InvoicePurchase', 'confirmed', '2026-09-01', 600), $p, 10, 500, 50);

        $card = $this->say('fiche du produit PRC18');
        $this->assertStringContainsString('Perceuse 18V (PRC18)', $card);
        $this->assertStringContainsString('Prix d\'achat 60,00 MAD, prix de vente 100,00 MAD', $card);
        $this->assertStringContainsString('marge 40 %', $card);
        $this->assertStringContainsString('Dépôt principal 7 — total 7', $card);
        $this->assertStringContainsString('Photos : 1 ; vendu sur 30 jours : 2', $card);
        $this->assertStringContainsString('Dernier achat : 50,00 MAD le 01/09/2026', $card);
        $this->assertStringContainsString('Perceuse 18V (PRC18)', $this->say('stock de perceuse'));
        $this->assertStringContainsString('Je ne trouve aucun produit', $this->say('fiche du produit zzzz'));

        $third = $this->say('fiche du client atlas');
        $this->assertStringContainsString('Client Atlas (client)', $third);
        $this->assertStringContainsString('0600112233, atlas@test.ma', $third);
        $this->assertStringContainsString('impayées : 1 pour 400,00 MAD', $third);
        $this->assertStringContainsString('FV-0001 — 05/10/2026 — 1 200,00 MAD, reste 400,00 MAD', $third);

        $doc = $this->say('montre la facture FV-0001');
        $this->assertStringContainsString('Facture de vente FV-0001 — partiellement payé', $doc);
        $this->assertStringContainsString('payé 800,00 MAD, reste 400,00 MAD', $doc);
        $this->assertStringContainsString('Perceuse 18V — 2 × 500,00 MAD = 1 000,00 MAD HT', $doc);
        $this->assertStringContainsString('06/10/2026 — cash — 800,00 MAD', $doc);
        $this->assertStringContainsString('Je ne trouve aucun document', $this->say('montre la facture ZZ-999'));
    }

    public function test_realized_margin_basket_trend_and_sales_by_category(): void
    {
        $a = $this->product('Perceuse', 'P1', ['p_cost' => 0, 'p_purchasePrice' => 60]);
        $b = $this->product('Disque', 'D1', ['p_cost' => 8, 'p_purchasePrice' => 99]);   // le coût prime sur le prix d'achat
        $d1 = $this->doc('InvoiceSale', 'confirmed', '2026-10-05', 1200);
        $this->line($d1, $a, 2, 200);     // coût 120 → marge 80
        $this->line($d1, $b, 10, 100);    // coût 80 → marge 20
        $d2 = $this->doc('TicketSale', 'paid', '2026-10-06', 240);
        $this->line($d2, $a, 1, 100);     // coût 60 → marge 40
        $this->doc('InvoiceSale', 'confirmed', '2026-09-10', 600);
        $this->doc('InvoiceSale', 'confirmed', '2026-08-10', 300);

        $margin = $this->say('marge réalisée du mois');
        $this->assertStringContainsString('Marge réalisée du mois', $margin);
        $this->assertStringContainsString('140,00 MAD sur 400,00 MAD HT, soit 35 %', $margin);   // 200+100+100 ; coût 120+80+60
        $this->assertStringContainsString('Perceuse — 120,00 MAD', $margin);

        $basket = $this->say('panier moyen du mois');
        $this->assertStringContainsString('Facture de ventes — 1 document(s), moyenne 1 200,00 MAD', $basket);
        $this->assertStringContainsString('Tous documents : 720,00 MAD en moyenne sur 2 vente(s)', $basket);

        $trend = $this->say('évolution du chiffre d\'affaires sur 3 mois');
        $this->assertStringContainsString('août 2026', $trend);
        $this->assertStringContainsString('300,00 MAD (1)', $trend);
        $this->assertStringContainsString('600,00 MAD (1) +100 %', $trend);
        $this->assertStringContainsString('1 440,00 MAD (2) +140 %', $trend);

        $byCat = $this->say('ventes du mois par catégorie');
        $this->assertStringContainsString('Cat P1 — 300,00 MAD (75 %), 3 vendu(s)', $byCat);
        $this->assertStringContainsString('Cat D1 — 100,00 MAD (25 %), 10 vendu(s)', $byCat);
    }

    public function test_quote_conversion_customer_orders_returns_and_cancelled_tickets(): void
    {
        $this->doc('QuoteSale', 'converted', '2026-10-02', 1000);
        $this->doc('QuoteSale', 'converted', '2026-10-03', 500);
        $this->doc('QuoteSale', 'cancelled', '2026-10-04', 200);
        $this->doc('QuoteSale', 'sent', '2026-10-05', 300);
        $this->doc('QuoteSale', 'draft', '2026-10-06', 9999);

        $quotes = $this->say('taux de transformation des devis');
        $this->assertStringContainsString('4 émis', $quotes);
        $this->assertStringContainsString('Transformés : 2 (1 500,00 MAD)', $quotes);
        $this->assertStringContainsString('Encore ouverts : 1', $quotes);
        $this->assertStringContainsString('50 % de tous les devis, 67 % des devis clos', $quotes);

        $this->doc('CustomerOrder', 'confirmed', '2026-10-01', 700, 0, null, 'CC-OPEN');
        $this->doc('CustomerOrder', 'converted', '2026-10-01', 700, 0, null, 'CC-DONE');
        $orders = $this->say('commandes clients en attente de livraison');
        $this->assertStringContainsString('CC-OPEN', $orders);
        $this->assertStringNotContainsString('CC-DONE', $orders);

        $this->doc('CreditNoteSale', 'confirmed', '2026-10-07', 150);
        $this->doc('ReturnSale', 'confirmed', '2026-10-08', 80);
        $returns = $this->say('retours et avoirs du mois');
        $this->assertStringContainsString('Avoir client — 1 document(s), 150,00 MAD', $returns);
        $this->assertStringContainsString('Retour client — 1 document(s), 80,00 MAD', $returns);

        $this->doc('TicketSale', 'cancelled', '2026-10-09', 45, 0, null, 'TK-VOID');
        $this->doc('TicketSale', 'paid', '2026-10-09', 99, 0, null, 'TK-OK');
        $tickets = $this->say('tickets annulés ce mois');
        $this->assertStringContainsString('1 ticket(s) annulé(s)', $tickets);
        $this->assertStringContainsString('TK-VOID', $tickets);
        $this->assertStringNotContainsString('TK-OK', $tickets);
    }

    public function test_runout_soon_cash_flow_and_forecast(): void
    {
        $wh = Warehouse::factory()->create();
        $fast = $this->product('Vite', 'V1');
        $slow = $this->product('Lente', 'L1');
        $none = $this->product('Épuisée', 'E1');
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $fast->id, 'stockLevel' => 10]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $slow->id, 'stockLevel' => 500]);
        WarehouseHasStock::factory()->create(['warehouse_id' => $wh->id, 'product_id' => $none->id, 'stockLevel' => 0]);
        $d = $this->doc('InvoiceSale', 'confirmed', '2026-10-10', 100);
        $this->line($d, $fast, 30, 100);    // 1 par jour : 10 jours de stock
        $this->line($d, $slow, 3, 100);     // 0,1 par jour : 5000 jours
        $this->line($d, $none, 15, 100);    // 0,5 par jour, stock nul

        $runout = $this->say('produits bientôt en rupture');
        $this->assertStringContainsString('2 produit(s) en rupture dans 14 jours ou moins', $runout);
        $this->assertStringContainsString('Épuisée (E1) — stock 0, 0,5 vendu(s) par jour → déjà en rupture', $runout);
        $this->assertStringContainsString('Vite (V1) — stock 10, 1 vendu(s) par jour → environ 10 jour(s)', $runout);
        $this->assertStringNotContainsString('Lente', $runout);

        $account = CashAccount::create(['ca_title' => 'Caisse', 'ca_code' => 'C1', 'ca_type' => 'cash', 'ca_initial_balance' => 1000, 'ca_status' => true]);
        $tx = fn (string $code, string $dir, float $amt, string $date, ?string $group = null) => DB::table('cash_transactions')->insert(['ct_code' => $code, 'cash_account_id' => $account->id, 'ct_direction' => $dir, 'ct_amount' => $amt, 'ct_date' => $date, 'ct_label' => $code, 'ct_status' => 'active', 'ct_transfer_group' => $group, 'created_at' => now(), 'updated_at' => now()]);
        $tx('T1', 'in', 5000, '2026-10-03');
        $tx('T2', 'out', 1800, '2026-10-04');
        $tx('T3', 'out', 700, '2026-10-05', '11111111-1111-1111-1111-111111111111');   // virement interne : sortie d'un compte…
        $tx('T4', 'in', 700, '2026-10-05', '11111111-1111-1111-1111-111111111111');    // …entrée de l'autre (le total ne bouge pas)

        $flow = $this->say('flux de trésorerie du mois');
        $this->assertStringContainsString('Entrées : 5 000,00 MAD (1)', $flow);
        $this->assertStringContainsString('Sorties : 1 800,00 MAD (1)', $flow);
        $this->assertStringContainsString('Solde net : +3 200,00 MAD', $flow);

        $this->doc('InvoiceSale', 'confirmed', '2026-10-01', 900, 900, '2026-10-20');        // à encaisser
        $this->doc('InvoiceSale', 'confirmed', '2026-08-01', 400, 400, '2026-08-30');        // échue : non comptée
        $this->doc('InvoicePurchase', 'confirmed', '2026-10-01', 1500, 1500, '2026-10-25');  // à payer
        DB::table('cash_recurrences')->insert(['cr_label' => 'Loyer', 'cr_direction' => 'out', 'cr_amount' => 300, 'cash_account_id' => $account->id, 'cr_frequency' => 'monthly', 'cr_anchor_day' => 1, 'cr_start_date' => '2026-01-01', 'cr_next_run_at' => '2026-11-01', 'cr_status' => true, 'created_at' => now(), 'updated_at' => now()]);

        $forecast = $this->say('prévision de trésorerie à 30 jours');
        $this->assertStringContainsString('Solde actuel des comptes : 4 200,00 MAD', $forecast);   // 1000 + 5000 - 1800
        $this->assertStringContainsString('+900,00 MAD', $forecast);
        $this->assertStringContainsString('-1 500,00 MAD', $forecast);
        $this->assertStringContainsString('+0,00 MAD / -300,00 MAD', $forecast);
        $this->assertStringContainsString('Solde prévisible : 3 300,00 MAD', $forecast);          // 4200 + 900 - 1500 - 300
        $this->assertStringContainsString('400,00 MAD de factures clients déjà échues', $forecast);
    }

    public function test_orders_and_earlier_commands_are_not_swallowed(): void
    {
        $this->product('Marteau', 'MRT1');
        $this->assertStringContainsString('agent stocks', mb_strtolower($this->say('prépare un inventaire')));
        $this->assertStringContainsString('Marge brute moyenne', $this->say('marge par catégorie'));      // lot 2, inchangé
        $this->assertStringContainsString('Ventes', $this->say('chiffre d\'affaires du mois'));           // lot 1, inchangé
        $this->assertStringContainsString('Historique de', $this->say('historique du produit MRT1'));   // lot 4, inchangé
    }
}
