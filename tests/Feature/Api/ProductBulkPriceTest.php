<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\WarehouseHasStock;
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

    /**
     * Un produit en stock.
     *
     * Le perimetre ne retient que le stock positif, sans option : un produit
     * sans ligne de stock n'existe pas pour cet ecran, il faut donc lui en
     * donner une des qu'on veut le voir dans un chiffrage.
     *
     * @param array<string, mixed> $attributes
     */
    private function stocked(array $attributes = [], float $stock = 10): Product
    {
        $product = Product::factory()->create($attributes);

        WarehouseHasStock::factory()->create([
            'product_id' => $product->id,
            'stockLevel' => $stock,
        ]);

        return $product;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return list<Product>
     */
    private function stockedMany(int $count, array $attributes = []): array
    {
        return array_map(fn () => $this->stocked($attributes), range(1, $count));
    }

    /**
     * Ces deux helpers desactivent l'arrondi sauf mention contraire.
     *
     * L'API arrondit aux 10 DH par defaut, ce qui est le bon reglage pour un
     * tarif mais masquerait les calculs qu'on veut verifier ici : 110 et 114,29
     * deviendraient tous les deux 110. Les tests d'arrondi, eux, passent leur
     * valeur — `+` sur les tableaux laisse une cle deja posee intacte — et un
     * test dedie couvre le defaut lui-meme.
     *
     * @param array<string, mixed> $payload
     */
    private function preview(array $payload)
    {
        return $this->actingAs($this->admin, 'sanctum')
                    ->postJson('/api/products/bulk-price/preview', $payload + ['rounding' => 'none']);
    }

    /** @param array<string, mixed> $payload */
    private function apply(array $payload)
    {
        return $this->actingAs($this->admin, 'sanctum')
                    ->postJson('/api/products/bulk-price/apply', $payload + ['rounding' => 'none']);
    }

    // ── Chiffrage ────────────────────────────────────────────────

    public function test_preview_reports_the_change_without_writing_it(): void
    {
        $product = $this->stocked(['p_salePrice' => 100]);

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
        $this->stocked(['p_salePrice' => 100]);
        $this->stocked(['p_salePrice' => 0]);

        // +10 % sur 0 donne 0 : le produit est compte, pas modifie.
        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('matched', 2)
             ->assertJsonPath('changed', 1)
             ->assertJsonPath('unchanged', 1);
    }

    public function test_margin_skips_products_without_a_basis(): void
    {
        $this->stocked(['p_purchasePrice' => 80, 'p_salePrice' => 90]);
        $this->stocked(['p_purchasePrice' => 0,  'p_salePrice' => 90]);

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

        $this->stockedMany(2, ['category_id' => $ciblee->id, 'p_salePrice' => 100]);
        $this->stocked(['category_id' => $autre->id, 'p_salePrice' => 100]);

        $this->preview([
            'mode'         => 'percent',
            'value'        => 10,
            'category_ids' => [$ciblee->id],
        ])->assertOk()->assertJsonPath('matched', 2);
    }

    public function test_inactive_products_can_be_left_out(): void
    {
        $this->stocked(['p_status' => true,  'p_salePrice' => 100]);
        $this->stocked(['p_status' => false, 'p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 10, 'status' => 'active'])
             ->assertOk()
             ->assertJsonPath('matched', 1);
    }

    public function test_the_brand_filter_narrows_the_batch(): void
    {
        $marque = Brand::factory()->create();

        $this->stocked(['brand_id' => $marque->id, 'p_salePrice' => 100]);
        $this->stocked(['p_salePrice' => 100]);

        $this->preview(['mode' => 'set', 'value' => 50, 'brand_ids' => [$marque->id]])
             ->assertOk()
             ->assertJsonPath('matched', 1);
    }

    // ── Arrondis ─────────────────────────────────────────────────

    public function test_rounding_to_the_nearest_dirham(): void
    {
        $this->stocked(['p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 7, 'rounding' => '1'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 107);
    }

    public function test_psychological_rounding_lands_below_the_round_figure(): void
    {
        $this->stocked(['p_salePrice' => 100]);

        // 100 + 0 % arrondi en .90 doit donner 99,90, pas 100,90.
        $this->preview(['mode' => 'amount', 'value' => 0, 'rounding' => 'end_90'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 99.9);
    }

    public function test_rounding_never_pushes_a_positive_price_below_zero(): void
    {
        $this->stocked(['p_salePrice' => 0.40]);

        $this->preview(['mode' => 'amount', 'value' => 0, 'rounding' => 'end_90'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 0);
    }

    // ── Application ──────────────────────────────────────────────

    public function test_apply_writes_the_new_prices(): void
    {
        $a = $this->stocked(['p_salePrice' => 100]);
        $b = $this->stocked(['p_salePrice' => 250]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 2])
             ->assertOk()
             ->assertJsonPath('updated', 2);

        $this->assertEquals(110, (float) $a->fresh()->p_salePrice);
        $this->assertEquals(275, (float) $b->fresh()->p_salePrice);
    }

    public function test_apply_refuses_when_the_batch_no_longer_matches_the_preview(): void
    {
        $this->stockedMany(3, ['p_salePrice' => 100]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 2])
             ->assertStatus(422)
             ->assertJsonPath('preview.matched', 3);

        $this->assertDatabaseCount('products', 3);
        $this->assertEquals(100, (float) Product::first()->p_salePrice);
    }

    public function test_apply_refuses_a_rule_that_would_produce_a_negative_price(): void
    {
        $product = $this->stocked(['p_salePrice' => 100]);

        $this->apply(['mode' => 'amount', 'value' => -150, 'expected_count' => 1])
             ->assertStatus(422)
             ->assertJsonPath('preview.negative', 1);

        $this->assertEquals(100, (float) $product->fresh()->p_salePrice);
    }

    public function test_apply_leaves_untouched_products_alone(): void
    {
        $this->stocked(['p_salePrice' => 100]);
        $this->stocked(['p_salePrice' => 0]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 2])
             ->assertOk()
             ->assertJsonPath('updated', 1)
             ->assertJsonPath('matched', 2);
    }

    public function test_the_change_lands_in_the_audit_trail(): void
    {
        $product = $this->stocked(['p_salePrice' => 100]);

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
        $product = $this->stocked(['p_salePrice' => 100]);
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
        $this->stocked(['p_salePrice' => 100]);
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
        $this->stocked(['p_salePrice' => 100]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('max_products', BulkSalePriceUpdater::MAX_PRODUCTS);
    }

    // ── Prix d'achat, cout et marge ──────────────────────────────

    public function test_the_preview_carries_the_cost_side_so_the_admin_can_judge(): void
    {
        $this->stocked([
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
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 90]);
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 50]);

        // −15 % ramene le premier a 85, sous son achat a 90 ; pas le second.
        $this->preview(['mode' => 'percent', 'value' => -15])
             ->assertOk()
             ->assertJsonPath('below_purchase', 1);
    }

    public function test_selling_under_purchase_price_is_a_warning_not_a_refusal(): void
    {
        $product = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 90]);

        // La vente a perte se decide, elle ne se bloque pas : c'est le prix
        // negatif qui est refuse, pas la marge negative.
        $this->apply(['mode' => 'percent', 'value' => -15, 'expected_count' => 1])
             ->assertOk();

        $this->assertEquals(85, (float) $product->fresh()->p_salePrice);
    }

    public function test_a_product_without_a_purchase_price_has_no_margin(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 0]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('sample.0.margin', null)
             ->assertJsonPath('below_purchase', 0);
    }

    public function test_the_cost_side_is_hidden_from_a_role_without_view_cost(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

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

    // ── Base de calcul ───────────────────────────────────────────

    public function test_a_percentage_can_be_applied_to_the_purchase_price(): void
    {
        $this->stocked(['p_salePrice' => 500, 'p_purchasePrice' => 80]);

        $this->preview(['mode' => 'percent', 'value' => 25, 'basis' => 'purchase'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 100);
    }

    public function test_a_percentage_can_be_applied_to_the_cost_price(): void
    {
        $this->stocked(['p_salePrice' => 500, 'p_purchasePrice' => 80, 'p_cost' => 90]);

        $this->preview(['mode' => 'percent', 'value' => 20, 'basis' => 'cost'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 108);
    }

    public function test_a_fixed_amount_can_be_applied_to_the_purchase_price(): void
    {
        $this->stocked(['p_salePrice' => 500, 'p_purchasePrice' => 80]);

        $this->preview(['mode' => 'amount', 'value' => 30, 'basis' => 'purchase'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 110);
    }

    public function test_the_sale_price_stays_the_default_basis(): void
    {
        $this->stocked(['p_salePrice' => 200, 'p_purchasePrice' => 80]);

        // Sans `basis`, on part du prix de vente : 200 + 10 % = 220.
        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 220);
    }

    public function test_a_cost_basis_at_zero_is_skipped_not_zeroed(): void
    {
        $this->stocked(['p_salePrice' => 300, 'p_purchasePrice' => 0]);

        // Sans prix d'achat, appliquer une marge donnerait 0 : on ignore le
        // produit plutot que de brader un article dont on ignore le cout.
        $this->preview(['mode' => 'percent', 'value' => 25, 'basis' => 'purchase'])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('changed', 0)
             ->assertJsonPath('skipped_no_basis', 1);
    }

    public function test_a_sale_basis_at_zero_is_not_skipped(): void
    {
        $this->stocked(['p_salePrice' => 0, 'p_purchasePrice' => 50]);

        // Un produit non tarife reste a zero : c'est un cas normal, pas une
        // base manquante.
        $this->preview(['mode' => 'percent', 'value' => 25])
             ->assertOk()
             ->assertJsonPath('skipped_no_basis', 0)
             ->assertJsonPath('unchanged', 1);
    }

    public function test_the_legacy_margin_mode_still_maps_to_a_purchase_percentage(): void
    {
        $this->stocked(['p_salePrice' => 500, 'p_purchasePrice' => 80]);

        // `margin` n'est plus propose par l'ecran mais reste accepte : il doit
        // rendre exactement ce que rend `percent` sur le prix d'achat.
        $this->preview(['mode' => 'margin', 'value' => 25])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 100);
    }

    public function test_an_unknown_basis_is_rejected(): void
    {
        $this->preview(['mode' => 'percent', 'value' => 10, 'basis' => 'prix_du_voisin'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['basis']);
    }

    public function test_applying_on_the_purchase_price_writes_the_new_prices(): void
    {
        $product = $this->stocked(['p_salePrice' => 500, 'p_purchasePrice' => 80]);

        $this->apply([
            'mode' => 'percent', 'value' => 25, 'basis' => 'purchase', 'expected_count' => 1,
        ])->assertOk()->assertJsonPath('updated', 1);

        $this->assertEquals(100, (float) $product->fresh()->p_salePrice);
    }

    // ── Perimetre : stock positif obligatoire ────────────────────

    public function test_only_products_left_in_stock_enter_the_batch(): void
    {
        $enStock = Product::factory()->create(['p_salePrice' => 100]);
        $epuise  = Product::factory()->create(['p_salePrice' => 100]);
        Product::factory()->create(['p_salePrice' => 100]); // aucune ligne de stock

        WarehouseHasStock::factory()->create(['product_id' => $enStock->id, 'stockLevel' => 4]);
        WarehouseHasStock::factory()->create(['product_id' => $epuise->id,  'stockLevel' => 0]);

        // Aucun drapeau a passer : la regle ne se desactive pas.
        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('sample.0.id', $enStock->id);
    }

    public function test_the_quantity_in_stock_is_reported_on_each_line(): void
    {
        $this->stocked(['p_salePrice' => 100], 7.5);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('sample.0.stock', 7.5);
    }

    public function test_stock_is_summed_across_warehouses(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100]);

        // Un depot en negatif, un autre qui compense : le total decide, et
        // c'est le total qui s'affiche.
        WarehouseHasStock::factory()->create(['product_id' => $product->id, 'stockLevel' => -2]);
        WarehouseHasStock::factory()->create(['product_id' => $product->id, 'stockLevel' => 5]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('sample.0.stock', 3);
    }

    public function test_a_product_in_negative_stock_overall_is_left_out(): void
    {
        $product = Product::factory()->create(['p_salePrice' => 100]);
        WarehouseHasStock::factory()->create(['product_id' => $product->id, 'stockLevel' => -3]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('matched', 0);
    }

    public function test_applying_never_touches_a_product_out_of_stock(): void
    {
        $enStock = $this->stocked(['p_salePrice' => 100], 7);
        $epuise  = Product::factory()->create(['p_salePrice' => 100]);

        WarehouseHasStock::factory()->create(['product_id' => $epuise->id, 'stockLevel' => 0]);

        $this->apply(['mode' => 'percent', 'value' => 10, 'expected_count' => 1])
             ->assertOk()
             ->assertJsonPath('updated', 1);

        $this->assertEquals(110, (float) $enStock->fresh()->p_salePrice);
        $this->assertEquals(100, (float) $epuise->fresh()->p_salePrice);
    }

    // ── Export ───────────────────────────────────────────────────

    public function test_the_preview_can_be_downloaded_as_a_spreadsheet(): void
    {
        $this->stockedMany(3, ['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->post('/api/products/bulk-price/export', ['mode' => 'percent', 'value' => 10]);

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type') ?? ''
        );
        $this->assertStringContainsString(
            'revision_prix_',
            $response->headers->get('content-disposition') ?? ''
        );
    }

    public function test_the_export_covers_the_whole_batch_not_just_the_sample(): void
    {
        // Le chiffrage plafonne l'echantillon a SAMPLE_SIZE ; la feuille, non.
        $this->stockedMany(BulkSalePriceUpdater::SAMPLE_SIZE + 5, ['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $rows = iterator_to_array(
            app(BulkSalePriceUpdater::class)->rows(['status' => 'all'], ['mode' => 'percent', 'value' => 10], true)
        );

        $this->assertCount(BulkSalePriceUpdater::SAMPLE_SIZE + 5, $rows);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonCount(BulkSalePriceUpdater::SAMPLE_SIZE, 'sample');
    }

    public function test_the_export_obeys_the_same_permission_as_the_rest(): void
    {
        $this->stocked(['p_salePrice' => 100]);
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->post('/api/products/bulk-price/export', ['mode' => 'percent', 'value' => 10])
             ->assertForbidden();
    }

    // ── Les deux lectures de la marge ────────────────────────────

    public function test_the_margin_is_reported_on_the_sale_price_as_well(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        // Passe a 120 : gagne 40 sur un achat a 80, soit 50 % sur achat, mais
        // 33,3 % de ce qu'encaisse la caisse. Avant : 25 % et 20 %.
        $this->preview(['mode' => 'percent', 'value' => 20])
             ->assertOk()
             ->assertJsonPath('sample.0.margin', 50)
             ->assertJsonPath('sample.0.margin_before', 25)
             ->assertJsonPath('sample.0.margin_sale', 33.3)
             ->assertJsonPath('sample.0.margin_sale_before', 20);
    }

    public function test_the_sale_margin_turns_negative_below_the_purchase_price(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 90]);

        // 85 pour un achat a 90 : on perd 5, soit -5,9 % du prix encaisse.
        $this->preview(['mode' => 'percent', 'value' => -15])
             ->assertOk()
             ->assertJsonPath('sample.0.margin_sale', -5.9)
             ->assertJsonPath('below_purchase', 1);
    }

    public function test_neither_margin_is_computed_without_a_purchase_price(): void
    {
        // Rapporter 110 a un achat inconnu afficherait « 100 % de marge » sur
        // un article dont on ignore justement le cout.
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 0]);

        $this->preview(['mode' => 'percent', 'value' => 10])
             ->assertOk()
             ->assertJsonPath('sample.0.margin', null)
             ->assertJsonPath('sample.0.margin_sale', null);
    }

    public function test_the_sale_margin_is_hidden_without_view_cost(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $role = Role::create(['name' => 'tarificateur_2', 'display_name' => 'Tarificateur', 'is_system' => false]);
        $role->permissions()->sync([
            Permission::firstOrCreate(
                ['name' => 'products.update'],
                ['module' => 'products', 'action' => 'update', 'display_name' => 'Produits — Modifier']
            )->id,
        ]);

        $response = $this->actingAs(User::factory()->create(['role_id' => $role->id]), 'sanctum')
                         ->postJson('/api/products/bulk-price/preview', ['mode' => 'percent', 'value' => 10])
                         ->assertOk();

        $this->assertArrayNotHasKey('margin_sale', $response->json('sample.0'));
        $this->assertArrayNotHasKey('margin_sale_before', $response->json('sample.0'));
    }

    // ── Filtre sur la marge actuelle ─────────────────────────────

    public function test_a_minimum_margin_narrows_the_batch(): void
    {
        // 100 achete 80 : 20 % sur vente. 100 achete 50 : 50 %.
        $faible = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);
        $forte  = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 50]);

        $this->preview(['mode' => 'percent', 'value' => 5, 'margin_min' => 30])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('sample.0.id', $forte->id);

        $this->assertNotNull($faible->id);
    }

    public function test_a_maximum_margin_isolates_the_products_to_lift(): void
    {
        $faible = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 50]);

        // Le cas utile : trouver ce qui passe sous un taux cible.
        $this->preview(['mode' => 'percent', 'value' => 5, 'margin_max' => 30])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('sample.0.id', $faible->id);
    }

    public function test_both_bounds_can_frame_a_band(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 90]); // 10 %
        $dansLaBande = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 75]); // 25 %
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 50]); // 50 %

        $this->preview(['mode' => 'percent', 'value' => 5, 'margin_min' => 20, 'margin_max' => 40])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('sample.0.id', $dansLaBande->id);
    }

    public function test_a_product_without_a_known_margin_leaves_the_batch_when_bounded(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 0]);

        // Sans borne il est la ; des qu'on borne, sa marge est inconnue et il
        // ne peut pas etre juge.
        $this->preview(['mode' => 'percent', 'value' => 5])
             ->assertOk()
             ->assertJsonPath('matched', 1);

        $this->preview(['mode' => 'percent', 'value' => 5, 'margin_min' => -1000])
             ->assertOk()
             ->assertJsonPath('matched', 0);
    }

    // ── Marge cible depuis le prix d'achat ───────────────────────

    public function test_a_target_margin_prices_from_the_purchase_price(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        // 80 / (1 - 0,30) = 114,29 — et la marge obtenue vaut bien 30 %.
        $this->preview(['mode' => 'target_margin', 'value' => 30])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 114.29)
             ->assertJsonPath('sample.0.margin_sale', 30);
    }

    public function test_a_target_margin_is_not_a_markup_on_the_purchase_price(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        // Le piege que ce mode existe pour eviter : +30 % sur l'achat donne
        // 104, qui ne laisse que 23,1 % de marge sur la vente.
        $this->preview(['mode' => 'percent', 'value' => 30, 'basis' => 'purchase'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 104)
             ->assertJsonPath('sample.0.margin_sale', 23.1);
    }

    public function test_a_target_margin_can_start_from_the_cost_price(): void
    {
        $this->stocked(['p_salePrice' => 200, 'p_purchasePrice' => 80, 'p_cost' => 90]);

        // 90 / (1 - 0,25) = 120.
        $this->preview(['mode' => 'target_margin', 'value' => 25, 'basis' => 'cost'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 120);
    }

    public function test_a_target_margin_falls_back_to_the_purchase_price(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        // `sale` n'a pas de sens ici : le calcul se mordrait la queue.
        $this->preview(['mode' => 'target_margin', 'value' => 30, 'basis' => 'sale'])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 114.29);
    }

    public function test_a_target_margin_of_one_hundred_percent_is_refused(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $this->preview(['mode' => 'target_margin', 'value' => 100])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['value']);
    }

    public function test_a_target_margin_skips_products_without_a_cost(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 0]);

        $this->preview(['mode' => 'target_margin', 'value' => 30])
             ->assertOk()
             ->assertJsonPath('matched', 1)
             ->assertJsonPath('skipped_no_basis', 1);
    }

    public function test_a_target_margin_can_be_applied(): void
    {
        $product = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $this->apply(['mode' => 'target_margin', 'value' => 30, 'expected_count' => 1])
             ->assertOk()
             ->assertJsonPath('updated', 1);

        $this->assertEquals(114.29, (float) $product->fresh()->p_salePrice);
    }
    // ── Arrondi par defaut ───────────────────────────────────────

    public function test_the_api_rounds_to_the_nearest_ten_when_nothing_is_asked(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        // 100 + 7 % = 107, arrondi aux 10 DH : 110.
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/products/bulk-price/preview', ['mode' => 'percent', 'value' => 7])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 110);
    }

    public function test_the_default_rounding_also_applies_when_writing(): void
    {
        $product = $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/products/bulk-price/apply', [
                 'mode' => 'percent', 'value' => 7, 'expected_count' => 1,
             ])
             ->assertOk();

        $this->assertEquals(110, (float) $product->fresh()->p_salePrice);
    }

    public function test_the_default_can_be_turned_off(): void
    {
        $this->stocked(['p_salePrice' => 100, 'p_purchasePrice' => 80]);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/products/bulk-price/preview', [
                 'mode' => 'percent', 'value' => 7, 'rounding' => 'none',
             ])
             ->assertOk()
             ->assertJsonPath('sample.0.new', 107);
    }
}
