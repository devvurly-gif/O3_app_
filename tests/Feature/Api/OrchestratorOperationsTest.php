<?php

namespace Tests\Feature\Api;

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
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
 * Troisième lot de lectures : achats, ventes inhabituelles, trésorerie, activité, utilisateurs, boutique en ligne,
 * promotions. Lecture seule et sans modèle de langage.
 */
class OrchestratorOperationsTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;
    private ThirdPartner $bati;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Atlas', 'tp_Role' => 'supplier']);
        $this->bati = ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Bati', 'tp_Role' => 'supplier']);
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
        return Product::factory()->create(array_merge(['p_title' => $title, 'p_sku' => $sku, 'p_status' => true, 'category_id' => Category::factory()->create()->id], $over));
    }

    private function doc(string $type, string $status, string $day, float $ttc, float $due = 0, ?string $dueAt = null, ?string $ref = null, ?ThirdPartner $tp = null): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => $type, 'status' => $status, 'issued_at' => $day, 'due_at' => $dueAt, 'thirdPartner_id' => ($tp ?? $this->atlas)->id, 'reference' => $ref ?? 'D-' . fake()->unique()->numerify('####')]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc, 'amount_paid' => $ttc - $due, 'amount_due' => $due]);

        return $d;
    }

    private function line(DocumentHeader $d, Product $p, float $qty, float $price, float $discount = 0, float $ref = 0): void
    {
        DocumentLigne::factory()->create(['document_header_id' => $d->id, 'product_id' => $p->id, 'designation' => $p->p_title, 'quantity' => $qty, 'unit_price' => $price, 'discount_percent' => $discount, 'reference_price' => $ref, 'status' => 'active', 'line_type' => 'product']);
    }

    public function test_purchases_by_supplier_orders_pending_and_supplier_invoices_to_pay(): void
    {
        $this->doc('InvoicePurchase', 'confirmed', '2026-10-05', 1200, 1200, '2026-10-01', 'FA-LATE');
        $this->doc('InvoicePurchase', 'confirmed', '2026-10-08', 600, 600, '2026-10-18', 'FA-SOON', $this->bati);
        $this->doc('InvoicePurchase', 'confirmed', '2026-10-08', 300, 300, '2026-12-30', 'FA-FAR', $this->bati);
        $this->doc('InvoicePurchase', 'draft', '2026-10-09', 9999);
        $this->doc('PurchaseOrder', 'sent', '2026-10-02', 450, 0, null, 'BC-OPEN');
        $this->doc('PurchaseOrder', 'converted', '2026-10-02', 450, 0, null, 'BC-DONE');

        $purchases = $this->say('achats du mois par fournisseur');
        $this->assertStringContainsString('3 facture(s), 2 100,00 MAD TTC', $purchases);
        $this->assertStringContainsString('Fournisseur Atlas — 1 facture(s), 1 200,00 MAD', $purchases);
        $this->assertStringNotContainsString('9 999', $purchases);

        $orders = $this->say('bons de commande en attente de réception');
        $this->assertStringContainsString('BC-OPEN', $orders);
        $this->assertStringNotContainsString('BC-DONE', $orders);

        $due = $this->say('factures fournisseurs à payer');
        $this->assertStringContainsString('3 facture(s) fournisseur à payer, 2 100,00 MAD dus, dont 1 échue(s)', $due);
        $this->assertLessThan(strpos($due, 'FA-SOON'), strpos($due, 'FA-LATE'));   // les plus proches d'abord

        $week = $this->say('échéances fournisseurs cette semaine');
        $this->assertStringContainsString('FA-LATE', $week);
        $this->assertStringContainsString('FA-SOON', $week);
        $this->assertStringNotContainsString('FA-FAR', $week);
    }

    public function test_purchase_price_increases_products_without_supplier_and_cheapest_supplier(): void
    {
        $up = $this->product('Perceuse', 'PRC1');
        $flat = $this->product('Disque', 'DSQ1');
        $this->line($this->doc('InvoicePurchase', 'confirmed', '2026-08-15', 100), $up, 1, 100);
        $this->line($this->doc('InvoicePurchase', 'confirmed', '2026-10-05', 100), $up, 1, 130);
        $this->line($this->doc('InvoicePurchase', 'confirmed', '2026-08-15', 100), $flat, 1, 50);
        $this->line($this->doc('InvoicePurchase', 'confirmed', '2026-10-05', 100), $flat, 1, 51);   // +2 % : sous le seuil

        $increase = $this->say("prix d'achat en hausse");
        $this->assertStringContainsString('Perceuse (PRC1) — 100,00 MAD → 130,00 MAD (+30 %)', $increase);
        $this->assertStringNotContainsString('Disque', $increase);

        DB::table('product_suppliers')->insert([
            ['product_id' => $up->id, 'third_partner_id' => $this->atlas->id, 'purchase_price' => 120, 'priority' => 1, 'lead_time_days' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $up->id, 'third_partner_id' => $this->bati->id, 'purchase_price' => 95, 'priority' => 2, 'lead_time_days' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $none = $this->say('produits sans fournisseur');
        $this->assertStringContainsString('Disque (DSQ1)', $none);
        $this->assertStringNotContainsString('Perceuse (PRC1)', $none);

        $cheapest = $this->say('fournisseur le moins cher pour perceuse');
        $this->assertLessThan(strpos($cheapest, 'Fournisseur Atlas'), strpos($cheapest, 'Fournisseur Bati'));
        $this->assertStringContainsString('Fournisseur Bati — 95,00 MAD', $cheapest);
        $this->assertStringContainsString('délai 3 j', $cheapest);
    }

    public function test_discounts_prices_below_reference_and_cancelled_invoices(): void
    {
        $p = $this->product('Perceuse', 'PRC1');
        $d = $this->doc('InvoiceSale', 'confirmed', '2026-10-06', 900, 0, null, 'FV-REMISE');
        $this->line($d, $p, 2, 500, 10, 520);                                // 100 de remise, vendu sous la référence
        $this->line($this->doc('InvoiceSale', 'confirmed', '2026-10-07', 100), $p, 1, 100, 0, 100);   // sans remise
        $this->doc('InvoiceSale', 'cancelled', '2026-10-09', 750, 0, null, 'FV-ANNULEE');

        $discounts = $this->say('remises accordées ce mois');
        $this->assertStringContainsString('1 ligne(s) remisée(s), 100,00 MAD au total, remise maximale 10 %', $discounts);
        $this->assertStringContainsString('FV-REMISE', $discounts);

        $below = $this->say('lignes vendues sous le prix de référence');
        $this->assertStringContainsString('1 ligne(s) vendue(s) sous le prix de référence', $below);
        $this->assertStringContainsString('500,00 MAD au lieu de 520,00 MAD', $below);

        $cancelled = $this->say('factures annulées ce mois');
        $this->assertStringContainsString('FV-ANNULEE', $cancelled);
        $this->assertStringNotContainsString('FV-REMISE', $cancelled);
    }

    public function test_expenses_by_category_without_receipt_and_recurrences(): void
    {
        $account = CashAccount::create(['ca_title' => 'Caisse', 'ca_code' => 'C1', 'ca_type' => 'cash', 'ca_initial_balance' => 0, 'ca_status' => true]);
        $loyer = DB::table('cash_categories')->insertGetId(['cc_title' => 'Loyer', 'cc_code' => 'LOY', 'cc_direction' => 'out', 'cc_status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tx = fn (string $code, int|null $cat, float $amt, string $date, ?string $file, string $status = 'active', string $dir = 'out', ?string $group = null) => DB::table('cash_transactions')->insert([
            'ct_code' => $code, 'cash_account_id' => $account->id, 'cash_category_id' => $cat, 'ct_direction' => $dir, 'ct_amount' => $amt, 'ct_date' => $date, 'ct_label' => "Op {$code}",
            'ct_status' => $status, 'ct_attachment_path' => $file, 'ct_transfer_group' => $group, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tx('T1', $loyer, 3000, '2026-10-02', 'loyer.pdf');
        $tx('T2', null, 200, '2026-10-05', null);
        $tx('T3', $loyer, 500, '2026-09-20', null);                // mois précédent
        $tx('T4', null, 999, '2026-10-06', null, 'cancelled');     // annulée
        $tx('T5', null, 777, '2026-10-07', null, 'active', 'out', '11111111-1111-1111-1111-111111111111');   // virement interne
        $tx('T6', null, 888, '2026-10-08', null, 'active', 'in');  // entrée

        $byCat = $this->say('dépenses du mois par catégorie');
        $this->assertStringContainsString('3 200,00 MAD (2 opération(s))', $byCat);
        $this->assertStringContainsString('Loyer — 1 opération(s), 3 000,00 MAD', $byCat);
        $this->assertStringContainsString('Sans catégorie — 1 opération(s), 200,00 MAD', $byCat);

        $noReceipt = $this->say('dépenses sans justificatif');
        $this->assertStringContainsString('1 dépense(s) sans justificatif', $noReceipt);
        $this->assertStringContainsString('Op T2', $noReceipt);
        $this->assertStringNotContainsString('Op T1', $noReceipt);

        DB::table('cash_recurrences')->insert([
            ['cr_label' => 'Loyer mensuel', 'cr_direction' => 'out', 'cr_amount' => 3000, 'cash_account_id' => $account->id, 'cr_frequency' => 'monthly', 'cr_anchor_day' => 1, 'cr_start_date' => '2026-01-01', 'cr_next_run_at' => '2026-11-01', 'cr_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['cr_label' => 'Trop loin', 'cr_direction' => 'out', 'cr_amount' => 10, 'cash_account_id' => $account->id, 'cr_frequency' => 'yearly', 'cr_anchor_day' => 1, 'cr_start_date' => '2026-01-01', 'cr_next_run_at' => '2027-03-01', 'cr_status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $rec = $this->say('opérations récurrentes des 30 prochains jours');
        $this->assertStringContainsString('Loyer mensuel', $rec);
        $this->assertStringContainsString('3 000,00 MAD à payer', $rec);
        $this->assertStringNotContainsString('Trop loin', $rec);
    }

    public function test_activity_users_online_gaps_and_promotions(): void
    {
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'Produit modifié', 'subject_type' => 'App\\Models\\Product', 'subject_id' => 7, 'event' => 'updated',
            'causer_type' => 'App\\Models\\User', 'causer_id' => $this->admin->id, 'properties' => json_encode(['secret' => 'NE-DOIT-PAS-APPARAITRE']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activity = $this->say('activité récente');
        $this->assertStringContainsString('Karim Admin — Produit modifié (Product #7)', $activity);
        $this->assertStringNotContainsString('NE-DOIT-PAS-APPARAITRE', $activity);
        $this->assertStringContainsString('Karim Admin', $this->say('activité de karim'));
        $this->assertStringContainsString('Aucune activité trouvée', $this->say('activité de personne'));

        User::factory()->create(['name' => 'Ancien Vendeur', 'is_active' => false]);
        $users = $this->say('utilisateurs inactifs');
        $this->assertStringContainsString('Ancien Vendeur', $users);
        $this->assertStringContainsString('compte(s)', $users);

        $warehouse = Warehouse::factory()->create();
        $inStock = $this->product('En stock', 'S1', ['is_ecom' => true, 'p_slug' => 's1']);
        $noStock = $this->product('Sans stock', 'S2', ['is_ecom' => true, 'p_slug' => 's2']);
        WarehouseHasStock::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $inStock->id, 'stockLevel' => 5]);
        ProductImage::create(['product_id' => $inStock->id, 'url' => '/storage/a.jpg', 'title' => 'a', 'isPrimary' => true]);
        $online = $this->say('produits en ligne sans stock ou sans photo');
        $this->assertStringContainsString('2 produit(s) actif(s) sur la boutique en ligne : 1 sans stock, 1 sans photo', $online);
        $this->assertStringContainsString('Sans stock (S2)', $online);

        $promo = fn (string $name, string $slug, ?string $ends, bool $active = true) => DB::table('promotions')->insert(['name' => $name, 'slug' => $slug, 'type' => 'percentage', 'value' => 15, 'starts_at' => '2026-10-01 00:00:00', 'ends_at' => $ends, 'is_active' => $active, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $promo('Rentrée', 'rentree', '2026-10-17 23:59:00');
        $promo('Hiver', 'hiver', '2026-12-31 23:59:00');
        $promo('Finie', 'finie', '2026-10-01 00:00:00');
        $promo('Coupée', 'coupee', '2026-12-31 23:59:00', false);

        $active = $this->say('promotions actives');
        $this->assertStringContainsString('2 promotion(s) active(s)', $active);
        $this->assertStringNotContainsString('Finie', $active);
        $this->assertStringNotContainsString('Coupée', $active);
        $ending = $this->say('promotions qui se terminent cette semaine');
        $this->assertStringContainsString('Rentrée', $ending);
        $this->assertStringNotContainsString('Hiver', $ending);
    }

    public function test_orders_and_previous_commands_are_not_swallowed(): void
    {
        $this->assertStringContainsString('agent stocks', mb_strtolower($this->say('prépare un inventaire')));
        $this->assertStringNotContainsString('Achats', $this->say('publie les produits sur le website'));
        $this->assertStringContainsString('Ventes', $this->say('chiffre d\'affaires du mois'));
    }
}
