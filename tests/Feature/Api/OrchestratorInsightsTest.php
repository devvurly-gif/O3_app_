<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
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
 * Deuxième lot de lectures : stock, catalogue et tiers. Lecture seule et sans modèle de langage.
 */
class OrchestratorInsightsTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Warehouse $main;
    private Warehouse $second;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->main = Warehouse::factory()->create(['wh_title' => 'Dépôt principal', 'wh_status' => true]);
        $this->second = Warehouse::factory()->create(['wh_title' => 'Magasin', 'wh_status' => true]);
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

    private function product(string $title, string $sku, float $sale = 100, float $buy = 60, array $over = []): Product
    {
        return Product::factory()->create(array_merge(['p_title' => $title, 'p_sku' => $sku, 'p_salePrice' => $sale, 'p_purchasePrice' => $buy, 'p_status' => true, 'category_id' => Category::factory()->create(['ctg_title' => "Cat {$sku}"])->id], $over));
    }

    private function stock(Product $p, float $qty, ?Warehouse $w = null, float $avg = 10): void
    {
        WarehouseHasStock::factory()->create(['warehouse_id' => ($w ?? $this->main)->id, 'product_id' => $p->id, 'stockLevel' => $qty, 'wh_average' => $avg]);
    }

    private function movement(Product $p, string $reason, string $direction, float $qty, string $at, float $cost = 10, string $status = 'applied'): void
    {
        DB::table('stock_mouvements')->insert([
            'product_id' => $p->id, 'warehouse_id' => $this->main->id, 'direction' => $direction, 'reason' => $reason, 'quantity' => $qty, 'unit_cost' => $cost,
            'stock_before' => 0, 'stock_after' => $qty, 'status' => $status, 'user_id' => $this->admin->id, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function sale(Product $p, string $day, string $status = 'confirmed', ?ThirdPartner $tp = null): void
    {
        $d = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => $status, 'issued_at' => $day, 'thirdPartner_id' => ($tp ?? ThirdPartner::factory()->create())->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ttc' => 120, 'total_ht' => 100]);
        DocumentLigne::factory()->create(['document_header_id' => $d->id, 'product_id' => $p->id, 'quantity' => 1, 'status' => 'active', 'line_type' => 'product']);
    }

    public function test_stock_value_negative_stock_and_dormant_products(): void
    {
        $a = $this->product('Perceuse', 'PRC1');
        $b = $this->product('Disque', 'DSQ1');
        $neg = $this->product('Négatif', 'NEG1');
        $this->stock($a, 10, $this->main, 20);
        $this->stock($a, 5, $this->second, 20);
        $this->stock($b, 100, $this->main, 2);
        $this->stock($neg, -3, $this->main, 5);
        $this->movement($b, 'sale', 'out', 1, '2026-10-10 10:00:00');       // récent
        $this->movement($a, 'purchase', 'in', 10, '2026-05-01 10:00:00');   // ancien : Perceuse dort

        $value = $this->say('valeur du stock');
        $this->assertStringContainsString('Dépôt principal — 2 produit(s), 110 pièce(s), 400,00 MAD', $value);   // 10×20 + 100×2
        $this->assertStringContainsString('Magasin — 1 produit(s), 5 pièce(s), 100,00 MAD', $value);
        $this->assertStringContainsString('Total : 500,00 MAD', $value);

        $negative = $this->say('produits à stock négatif');
        $this->assertStringContainsString('1 produit(s) à stock négatif', $negative);
        $this->assertStringContainsString('Négatif (NEG1) — -3 pièce(s)', $negative);

        $dormant = $this->say('produits dormants');
        $this->assertStringContainsString('Perceuse (PRC1)', $dormant);
        $this->assertStringNotContainsString('Disque (DSQ1)', $dormant);
        $this->assertStringContainsString('300,00 MAD immobilisés', $dormant);
    }

    public function test_pending_transfers_and_movements_and_losses(): void
    {
        $p = $this->product('Perceuse', 'PRC1');
        DB::table('warehouse_transfers')->insert(['from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->second->id, 'product_id' => $p->id, 'quantity' => 4, 'status' => 'pending', 'user_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouse_transfers')->insert(['from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->second->id, 'product_id' => $p->id, 'quantity' => 9, 'status' => 'completed', 'user_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->movement($p, 'loss', 'out', 3, '2026-10-05 10:00:00', 50);
        $this->movement($p, 'sale', 'out', 7, '2026-10-14 08:00:00');
        $this->movement($p, 'loss', 'out', 99, '2026-10-06 10:00:00', 1, 'cancelled');

        $t = $this->say('transferts en attente');
        $this->assertStringContainsString('1 transfert(s) en attente', $t);
        $this->assertStringContainsString('Dépôt principal → Magasin', $t);
        $this->assertStringNotContainsString('× 9', $t);

        $loss = $this->say('pertes du mois');
        $this->assertStringContainsString('perte (sortie) — 1 mouvement(s), 3 pièce(s)', $loss);
        $this->assertStringContainsString('150,00 MAD', $loss);
        $this->assertStringNotContainsString('99', $loss);

        $today = $this->say('mouvements de stock du jour');
        $this->assertStringContainsString('vente (sortie) — 1 mouvement(s), 7 pièce(s)', $today);
        $this->assertStringNotContainsString('perte', $today);
    }

    public function test_duplicates_margins_and_products_never_sold(): void
    {
        $this->product('Perceuse 18V', 'A1', 100, 60, ['p_ean13' => '1111111111116']);
        $this->product('Perceuse 18V', 'A2', 100, 60, ['p_ean13' => '1111111111116']);
        $cheap = $this->product('Marteau', 'M1', 50, 80);              // sous le prix d'achat
        $sold = $this->product('Tournevis', 'T1', 40, 20);
        $this->stock($cheap, 12);
        $this->sale($sold, '2026-10-01');
        $this->sale($cheap, '2026-03-01');                              // vente ancienne

        $dup = $this->say('doublons de produits');
        $this->assertStringContainsString('1 code(s)-barres partagé(s) et 1 titre(s) identique(s)', $dup);
        $this->assertStringContainsString('A1, A2', $dup);

        $margins = $this->say('marge par catégorie');
        $this->assertStringContainsString('sur 4 produit(s) actif(s), dont 1 vendu(s) sous le prix d\'achat', $margins);
        $this->assertStringContainsString('Cat M1 — -60 %', $margins);

        $never = $this->say('produits jamais vendus depuis 90 jours');
        $this->assertStringContainsString('Marteau (M1) — stock 12', $never);
        $this->assertStringNotContainsString('Tournevis', $never);
    }

    public function test_inactive_customers_credit_limits_and_incomplete_third_parties(): void
    {
        $active = ThirdPartner::factory()->create(['tp_title' => 'Client actif', 'tp_Role' => 'customer', 'tp_status' => true, 'tp_phone' => '0600000001', 'tp_email' => 'a@a.ma', 'tp_Ice_Number' => '001']);
        $sleepy = ThirdPartner::factory()->create(['tp_title' => 'Client endormi', 'tp_Role' => 'customer', 'tp_status' => true, 'tp_phone' => null, 'tp_email' => null, 'tp_Ice_Number' => null]);
        ThirdPartner::factory()->create(['tp_title' => 'Client jamais servi', 'tp_Role' => 'customer', 'tp_status' => true, 'tp_phone' => '0600000003', 'tp_email' => 'c@c.ma', 'tp_Ice_Number' => '003']);
        ThirdPartner::factory()->create(['tp_title' => 'Fournisseur Atlas', 'tp_Role' => 'supplier', 'tp_status' => true]);
        $p = $this->product('Perceuse', 'P1');
        $this->sale($p, '2026-10-10', 'confirmed', $active);
        $this->sale($p, '2026-06-01', 'confirmed', $sleepy);
        DB::table('third_partners')->where('id', $sleepy->id)->update(['encours_actuel' => 5000, 'seuil_credit' => 3000]);
        DB::table('third_partners')->where('id', $active->id)->update(['encours_actuel' => 100, 'seuil_credit' => 3000]);

        $inactive = $this->say('clients inactifs depuis 60 jours');
        $this->assertStringContainsString('Client endormi — dernier achat le 01/06/2026', $inactive);
        $this->assertStringContainsString('dont 1 sans aucun achat', $inactive);
        $this->assertStringNotContainsString('Client actif', $inactive);
        $this->assertStringNotContainsString('Fournisseur Atlas', $inactive);

        $credit = $this->say('clients qui dépassent leur seuil de crédit');
        $this->assertStringContainsString('Client endormi — encours 5 000,00 MAD pour un seuil de 3 000,00 MAD (+2 000,00 MAD)', $credit);
        $this->assertStringNotContainsString('Client actif', $credit);

        $incomplete = $this->say('clients sans téléphone, e-mail ou ICE');
        $this->assertStringContainsString('Fiches de', $incomplete);
        $this->assertStringContainsString('• sans téléphone : 1', $incomplete);
        $this->assertStringContainsString('Client endormi', $incomplete);
    }

    public function test_duplicate_third_parties_and_commands_are_not_swallowed(): void
    {
        ThirdPartner::factory()->create(['tp_title' => 'Atlas A', 'tp_Ice_Number' => '123456', 'tp_phone' => '0611']);
        ThirdPartner::factory()->create(['tp_title' => 'Atlas B', 'tp_Ice_Number' => '123456', 'tp_phone' => '0622']);

        $dup = $this->say('doublons de clients');
        $this->assertStringContainsString('ICE 123456 — 2 fiches', $dup);

        // Les ordres et les anciennes commandes gardent leur sens.
        $this->assertStringContainsString('agent stocks', mb_strtolower($this->say('prépare un inventaire')));
        $this->assertStringNotContainsString('Valeur du stock', $this->say('révise les prix des fiches produits avec une marge de 25 %'));
    }
}
