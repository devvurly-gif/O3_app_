<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOperationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin     = User::factory()->admin()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product   = Product::factory()->create();
    }

    // ── Manual Entry ─────────────────────────────────────────────

    public function test_manual_entry_api(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/entree', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity'     => 50,
                'notes'        => 'Réception marchandise',
            ]);

        $response->assertCreated();

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();

        $this->assertEquals(50, $stock->stockLevel);
    }

    // ── Manual Exit ──────────────────────────────────────────────

    public function test_manual_exit_api(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 100,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/sortie', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity'     => 30,
            ]);

        $response->assertCreated();

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();

        $this->assertEquals(70, $stock->stockLevel);
    }

    // ── Adjustment ───────────────────────────────────────────────

    public function test_adjustment_api(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 80,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/ajustement', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'new_quantity' => 100,
            ]);

        $response->assertCreated();

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();

        $this->assertEquals(100, $stock->stockLevel);
    }

    // ── Transfer Execute ─────────────────────────────────────────

    public function test_execute_transfer_api(): void
    {
        $toWh = Warehouse::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 200,
        ]);

        $transfer = WarehouseTransfer::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id'   => $toWh->id,
            'product_id'        => $this->product->id,
            'quantity'           => 60,
            'status'            => 'pending',
            'user_id'           => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/warehouse-transfers/{$transfer->id}/execute");

        $response->assertOk()
                 ->assertJsonFragment(['status' => 'completed']);

        $this->assertEquals(140, WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first()->stockLevel);
        $this->assertEquals(60, WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $toWh->id)->first()->stockLevel);
    }

    // ── Authorization ────────────────────────────────────────────

    public function test_cashier_cannot_access_stock_endpoints(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/stock/entree', [
                 'product_id'   => $this->product->id,
                 'warehouse_id' => $this->warehouse->id,
                 'quantity'     => 10,
             ])
             ->assertForbidden();
    }

    public function test_warehouse_user_can_access_stock_endpoints(): void
    {
        $whUser = User::factory()->warehouse()->create();

        $this->actingAs($whUser, 'sanctum')
             ->postJson('/api/stock/entree', [
                 'product_id'   => $this->product->id,
                 'warehouse_id' => $this->warehouse->id,
                 'quantity'     => 10,
             ])
             ->assertCreated();
    }
}
