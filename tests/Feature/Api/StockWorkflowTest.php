<?php

namespace Tests\Feature\Api;

use App\Models\DocumentHeader;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockWorkflowTest extends TestCase
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

    // ── Entry → Exit workflow ─────────────────────────────────────

    public function test_entry_then_exit_updates_stock_correctly(): void
    {
        // Entry: +100
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/entree', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity'     => 100,
                'notes'        => 'Entrée initiale',
            ])
            ->assertCreated();

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(100, $stock->stockLevel);

        // Exit: -40
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/sortie', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity'     => 40,
            ])
            ->assertCreated();

        $stock->refresh();
        $this->assertEquals(60, $stock->stockLevel);
    }

    // ── Adjustment ────────────────────────────────────────────────

    public function test_adjustment_sets_stock_to_exact_quantity(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 50,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/ajustement', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'new_quantity' => 75,
            ])
            ->assertCreated();

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(75, $stock->stockLevel);
    }

    // ── Transfer workflow ─────────────────────────────────────────

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        $toWh = Warehouse::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 200,
        ]);

        // Create transfer
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/warehouse-transfers', [
                'from_warehouse_id' => $this->warehouse->id,
                'to_warehouse_id'   => $toWh->id,
                'product_id'        => $this->product->id,
                'quantity'          => 80,
            ]);

        $response->assertCreated();
        $transferId = $response->json('id');

        // Execute transfer
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/warehouse-transfers/{$transferId}/execute")
            ->assertOk()
            ->assertJsonFragment(['status' => 'completed']);

        // Verify stock levels
        $fromStock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();
        $toStock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $toWh->id)->first();

        $this->assertEquals(120, $fromStock->stockLevel);
        $this->assertEquals(80, $toStock->stockLevel);
    }

    public function test_transfer_cancel_keeps_original_stock(): void
    {
        $toWh = Warehouse::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 100,
        ]);

        $transfer = WarehouseTransfer::factory()->create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id'   => $toWh->id,
            'product_id'        => $this->product->id,
            'quantity'          => 50,
            'status'            => 'pending',
            'user_id'           => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/warehouse-transfers/{$transfer->id}/cancel")
            ->assertOk();

        // Stock unchanged
        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(100, $stock->stockLevel);
    }

    // ── Stock movements are tracked ───────────────────────────────

    public function test_entry_creates_stock_movement(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/entree', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity'     => 25,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('stock_mouvements', [
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity'     => 25,
            'direction'    => 'in',
        ]);
    }

    // ── Validation ────────────────────────────────────────────────

    public function test_exit_fails_when_stock_insufficient(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 10,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/sortie', [
                'product_id'   => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity'     => 100,
            ])
            ->assertStatus(422);
    }

    public function test_entry_requires_valid_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/stock/entree', [])
            ->assertUnprocessable();
    }

    // ── Authorization ─────────────────────────────────────────────

    public function test_cashier_cannot_do_stock_entry(): void
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

    public function test_warehouse_user_can_do_stock_operations(): void
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
