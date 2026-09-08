<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\BulkSalePriceUpdater;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Revision en masse du prix de vente.
 *
 * L'operation reecrit le tarif de tout un catalogue : les garde-fous — compte
 * confirme, refus des prix negatifs, plafond du lot — comptent autant que le
 * calcul lui-meme, et sont testes ici au meme titre.
 */
class ProductBulkPriceTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** @param array<string, mixed> $payload */
    private function preview(array $payload)
    {
        return $this->actingAs($this->admin, 'sanctum')
                    ->postJson('/api/products/bulk-price/preview', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function apply(array $payload)
    {
        return $this->actingAs($this->admin, 'sanctum')
                    ->postJson('/api/products/bulk-price/apply', $payload);
    }

    // ── Chiffrage ────────────────────────────────────────────────

    public function test_preview_reports_the_change_without_writing_it(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('changed', 1)
             ->assertJsonPath('sample.0.current', 100)
             ->assertJsonPath('sample.0.new', 110);

        $this->assertEquals(100, (float) $product->fresh()->p_salePrice);
    }

    public function test_preview_counts_products_the_rule_leaves_untouched(): void
    {
        Product::factory()->create(['p_salePrice' => 100]);
        Product::factory()->create(['p_salePrice' => 0]);

        // +10 % sur 0 donne 0 : le produit est compte, pas modifie.
        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('matched', 2)
             ->assertJsonPath('changed', 1)
             ->assertJsonPath('unchanged', 1);
    }

    public function test_margin_skips_products_without_a_basis(): void
    {
        Product::factory()->create(['p_purchasePrice' => 80, 'p_salePrice' => 90]);
        Product::factory()->create(['p_purchasePrice' => 0,  'p_salePrice' => 90]);

        $this->preview(['mode' => 'margin', 'value' => 25])
             ->assertOk()
             ->assertJsonPath('matched', 2)
             ->assertJsonPath('changed', 1)
             ->assertJsonPath('skipped_no_basis', 1)
             ->assertJsonPath('sample.0.new', 100);
    }

    // ── Filtres ──────────────────────────────────────────────────

    public function test_filters_restrict_the_batch(): void
    {
        $ciblee = Category::factory()->create();
        $autre  = Category::factory()->create();

        Product::factory()->count(2)->create(['category_id' => $ciblee->id, 'p_salePrice' => 100]);
        Product::factory()->create(['category_id' => $autre->id, 'p_salePrice' => 100]);

        $this->preview([
            'mode'         => 'percent',
            'value'        => 10,
            'category_ids' => [$ciblee->id],
        ])->assertOk()->assertJsonPath('matched', 2);
    }

    public function test_inactive_products_can_be_left_out(): void
    {
        Product::factory()->create(['p_status' => true,  'p_salePrice' => 100]);
        Product::factory()->create(['p_status' => false, 'p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 10, 'status' => 'active'])
             ->assertOk()
             ->assertJsonPath('matched', 1);
    }

    public function test_the_brand_filter_narrows_the_batch(): void
    {
        $marque = Brand::factory()->create();

        Product::factory()->create(['brand_id' => $marque->id, 'p_salePrice' => 100]);
        Product::factory()->create(['p_salePrice' => 100]);

        $this->preview(['mode' => 'set', 'value' => 50, 'brand_ids' => [$marque->id]])
             ->assertOk()
             ->assertJsonPath('matched', 1);
    }

    // ── Arrondis ─────────────────────────────────────────────────

    public function test_rounding_to_the_nearest_dirham(): void
    {
        Product::factory()->create(['p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 7, 'rounding' => '1'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 107);
    }

    public function test_psychological_rounding_lands_below_the_round_figure(): void
    {
        Product::factory()->create(['p_salePrice' => 100]);

        // 100 + 0 % arrondi en .90 doit donner 99,90, pas 100,90.
        $this->preview(['mode' => 'amount', 'value' => 0, 'rounding' => 'end_90'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 99.9);
    }

    public function test_rounding_never_pushes_a_positive_price_below_zero(): void
    {
        Product::factory()->create(['p_salePrice' => 0.40]);

        $this->preview(['mode' => 'amount', 'value' => 0, 'rounding' => 'end_90'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 0);
    }

    // ── Application ──────────────────────────────────────────────

    public function test_apply_writes_the_new_prices(): void
    {
        $a = Product::factory()->create(['p_salePrice' => 100]);
        $b = Product::factory()->create(['p_salePrice' => 250]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 2])
             ->assertOk()
             ->assertJsonPath('updated', 2);

        $this->assertEquals(110, (float) $a->fresh()->p_salePrice);
        $this->assertEquals(275, (float) $b->fresh()->p_salePrice);
    }

    public function test_apply_refuses_when_the_batch_no_longer_matches_the_preview(): void
    {
        Product::factory()->count(3)->create(['p_salePrice' => 100]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 2])
             ->assertStatus(422)
             ->assertJsonPath('preview.matched', 3);

        $this->assertDatabaseCount('products', 3);
        $this->assertEquals(100, (float) Product::first()->p_salePrice);
    }

    public function test_apply_refuses_a_rule_that_would_produce_a_negative_price(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100]);

        $this->apply(['mode' => 'amount', 'value' => -150, 'expected_count' => 1])
             ->assertStatus(422)
             ->assertJsonPath('preview.negative', 1);

        $this->assertEquals(100, (float) $product->fresh()->p_salePrice);
    }

    public function test_apply_leaves_untouched_products_alone(): void
    {
        Product::factory()->create(['p_salePrice' => 100]);
        Product::factory()->create(['p_salePrice' => 0]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 2])
             ->assertOk()
             ->assertJsonPath('updated', 1)
             ->assertJsonPath('matched', 2);
    }

    public function test_the_change_lands_in_the_audit_trail(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 1])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Product::class,
            'subject_id'   => $product->id,
            'event'        => 'updated',
        ]);
    }

    // ── Permissions ──────────────────────────────────────────────

    public function test_a_cashier_cannot_reprice_the_catalogue(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100]);
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/products/bulk-price/apply', [
                 'mode' => 'percent', 'value' => 10, 'expected_count' => 1,
             ])
             ->assertForbidden();

        $this->assertEquals(100, (float) $product->fresh()->p_salePrice);
    }

    public function test_a_manager_can_reprice_the_catalogue(): void
    {
        Product::factory()->create(['p_salePrice' => 100]);
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'sanctum')
             ->postJson('/api/products/bulk-price/apply', [
                 'mode' => 'percent', 'value' => 10, 'expected_count' => 1,
             ])
             ->assertOk();
    }

    // ── Validation ───────────────────────────────────────────────

    public function test_an_unknown_mode_is_rejected(): void
    {
        $this->preview(['mode' => 'divide_by_two', 'value' => 2])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['mode']);
    }

    public function test_the_batch_ceiling_is_exposed_by_the_preview(): void
    {
        Product::factory()->create(['p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('max_products', BulkSalePriceUpdater::MAX_PRODUCTS);
    }

    // ── Prix d'achat, cout et marge ──────────────────────────────

    public function test_the_preview_carries_the_cost_side_so_the_admin_can_judge(): void
    {
        Product::factory()->create([
            'p_salePrice'     => 100,
            'p_purchasePrice' => 80,
            'p_cost'          => 85,
        ]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('costs_visible', true)
             ->assertJsonPath('sample.0.purchase', 80)
             ->assertJsonPath('sample.0.cost', 85)
             // 110 sur un achat a 80 : +37,5 %, contre +25 % avant.
             ->assertJsonPath('sample.0.margin', 37.5)
             ->assertJsonPath('sample.0.margin_before', 25);
    }

    public function test_products_falling_under_their_purchase_price_are_counted(): void
    {
        Product::factory()->create(['p_salePrice' => 100, 'p_purchasePrice' => 90]);
        Product::factory()->create(['p_salePrice' => 100, 'p_purchasePrice' => 50]);

        // −15 % ramene le premier a 85, sous son achat a 90 ; pas le second.
        $this->preview(['mode' => 'percent', 'value' => -15])
             ->assertOk()
             ->assertJsonPath('below_purchase', 1);
    }

    public function test_selling_under_purchase_price_is_a_warning_not_a_refusal(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100, 'p_purchasePrice' => 90]);

        // La vente a perte se decide, elle ne se bloque pas : c'est le prix
        // negatif qui est refuse, pas la marge negative.
        $this->apply(['mode' => 'percent', 'value' => -15, 'expected_count' => 1])
             ->assertOk();

        $this->assertEquals(85, (float) $product->fresh()->p_salePrice);
    }

    public function test_a_product_without_a_purchase_price_has_no_margin(): void
    {
        Product::factory()->create(['p_salePrice' => 100, 'p_purchasePrice' => 0]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('sample.0.margin', null)
             ->assertJsonPath('below_purchase', 0);
    }

    public function test_the_cost_side_is_hidden_from_a_role_without_view_cost(): void
    {
        Product::factory()->create(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        // Un role qui peut modifier les produits sans voir les couts : les
        // colonnes d'achat ne doivent pas voyager dans le JSON.
        $role = Role::create(['name' => 'tarificateur', 'display_name' => 'Tarificateur', 'is_system' => false]);
        $role->permissions()->sync([
            Permission::firstOrCreate(
                ['name' => 'products.update'],
                ['module' => 'products', 'action' => 'update', 'display_name' => 'Produits — Modifier']
            )->id,
        ]);

        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/products/bulk-price/preview', ['mode' => 'percent', 'value' => 10])
                         ->assertOk()
                         ->assertJsonPath('costs_visible', false)
                         ->assertJsonPath('below_purchase', null);

        $this->assertArrayNotHasKey('purchase', $response->json('sample.0'));
        $this->assertArrayNotHasKey('cost', $response->json('sample.0'));
        $this->assertArrayNotHasKey('margin', $response->json('sample.0'));
    }
}
