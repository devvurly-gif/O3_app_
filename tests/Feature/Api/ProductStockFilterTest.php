<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the `in_stock` filter on GET /api/products.
 *
 *   ?in_stock=1 → only products with at least one warehouse stockLevel > 0
 *   ?in_stock=0 → only products that are out of stock everywhere
 *                 (zero in every warehouse OR no warehouse row at all)
 *   no param    → returns everything (no stock filter applied)
 *
 * Regression: the previous `array_filter($filters)` dropped any falsy value
 * from the query string, so `in_stock=0` was silently stripped and returned
 * the full list. The preservation callback `fn ($v) => $v !== null` keeps
 * boolean false intact.
 */
class ProductStockFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin     = User::factory()->admin()->create();
        $this->warehouse = Warehouse::factory()->create();
    }

    private function productWithStock(float $stockLevel): Product
    {
        $product = Product::factory()->create();
        WarehouseHasStock::factory()->create([
            'product_id'   => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => $stockLevel,
        ]);
        return $product;
    }

    private function productWithoutWarehouseRow(): Product
    {
        // No warehouse_has_stock row at all.
        return Product::factory()->create();
    }

    private function index(array $query)
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/products?' . http_build_query($query));
    }

    private function idsFromResponse($response): array
    {
        return collect($response->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_in_stock_true_returns_only_products_with_positive_stock(): void
    {
        $inStock        = $this->productWithStock(5);
        $outOfStock     = $this->productWithStock(0);
        $noWarehouseRow = $this->productWithoutWarehouseRow();

        $response = $this->index(['in_stock' => 1])->assertOk();
        $ids      = $this->idsFromResponse($response);

        $this->assertEquals([$inStock->id], $ids);
        $this->assertNotContains($outOfStock->id, $ids);
        $this->assertNotContains($noWarehouseRow->id, $ids);
    }

    public function test_in_stock_false_returns_only_rupture_products(): void
    {
        $inStock        = $this->productWithStock(12);
        $outOfStock     = $this->productWithStock(0);
        $noWarehouseRow = $this->productWithoutWarehouseRow();

        $response = $this->index(['in_stock' => 0])->assertOk();
        $ids      = $this->idsFromResponse($response);

        // Both zero-stock and no-row products are "rupture".
        $this->assertContains($outOfStock->id, $ids);
        $this->assertContains($noWarehouseRow->id, $ids);
        $this->assertNotContains($inStock->id, $ids);
    }

    public function test_no_in_stock_param_returns_all_products(): void
    {
        $this->productWithStock(5);
        $this->productWithStock(0);
        $this->productWithoutWarehouseRow();

        $response = $this->index([])->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_product_with_multiple_warehouses_is_in_stock_if_any_positive(): void
    {
        $w2 = Warehouse::factory()->create();
        $p  = Product::factory()->create();

        // w1 empty, w2 has 3 units → must count as in-stock.
        WarehouseHasStock::factory()->create([
            'product_id'   => $p->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 0,
        ]);
        WarehouseHasStock::factory()->create([
            'product_id'   => $p->id,
            'warehouse_id' => $w2->id,
            'stockLevel'   => 3,
        ]);

        $ids = $this->idsFromResponse($this->index(['in_stock' => 1])->assertOk());
        $this->assertContains($p->id, $ids);

        $ids = $this->idsFromResponse($this->index(['in_stock' => 0])->assertOk());
        $this->assertNotContains($p->id, $ids);
    }
}
