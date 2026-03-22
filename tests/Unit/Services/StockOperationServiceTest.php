<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\StockMouvement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Models\WarehouseTransfer;
use App\Services\StockOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOperationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockOperationService $service;
    private User $user;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service   = app(StockOperationService::class);
        $this->user      = User::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product   = Product::factory()->create();
    }

    // ── Manual Entry ─────────────────────────────────────────────

    public function test_manual_entry_creates_stock_record(): void
    {
        $movement = $this->service->manualEntry(
            $this->product->id,
            $this->warehouse->id,
            100,
            25.50,
            $this->user->id,
            'Entrée initiale'
        );

        $this->assertInstanceOf(StockMouvement::class, $movement);
        $this->assertEquals('in', $movement->direction);
        $this->assertEquals('manual_entry', $movement->reason);
        $this->assertEquals(100, $movement->quantity);
        $this->assertEquals(0, $movement->stock_before);
        $this->assertEquals(100, $movement->stock_after);

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(100, $stock->stockLevel);
    }

    public function test_manual_entry_adds_to_existing_stock(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 50,
        ]);

        $movement = $this->service->manualEntry(
            $this->product->id,
            $this->warehouse->id,
            30,
            null,
            $this->user->id
        );

        $this->assertEquals(50, $movement->stock_before);
        $this->assertEquals(80, $movement->stock_after);

        $stock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(80, $stock->stockLevel);
    }

    // ── Manual Exit ──────────────────────────────────────────────

    public function test_manual_exit_decrements_stock(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 100,
        ]);

        $movement = $this->service->manualExit(
            $this->product->id,
            $this->warehouse->id,
            40,
            null,
            $this->user->id,
            'Sortie pour usage interne'
        );

        $this->assertEquals('out', $movement->direction);
        $this->assertEquals('manual_exit', $movement->reason);
        $this->assertEquals(100, $movement->stock_before);
        $this->assertEquals(60, $movement->stock_after);
    }

    // ── Inventory Adjustment ─────────────────────────────────────

    public function test_adjust_inventory_up(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 30,
        ]);

        $movement = $this->service->adjustInventory(
            $this->product->id,
            $this->warehouse->id,
            50,
            $this->user->id,
            'Correction inventaire'
        );

        $this->assertEquals('in', $movement->direction);
        $this->assertEquals('inventory_adjustment', $movement->reason);
        $this->assertEquals(20, $movement->quantity);
        $this->assertEquals(30, $movement->stock_before);
        $this->assertEquals(50, $movement->stock_after);
    }

    public function test_adjust_inventory_down(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 100,
        ]);

        $movement = $this->service->adjustInventory(
            $this->product->id,
            $this->warehouse->id,
            80,
            $this->user->id
        );

        $this->assertEquals('out', $movement->direction);
        $this->assertEquals(20, $movement->quantity);
    }

    public function test_adjust_inventory_zero_diff_throws(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stockLevel'   => 50,
        ]);

        $this->expectException(\DomainException::class);

        $this->service->adjustInventory(
            $this->product->id,
            $this->warehouse->id,
            50,
            $this->user->id
        );
    }

    // ── Transfer ─────────────────────────────────────────────────

    public function test_execute_transfer_moves_stock_between_warehouses(): void
    {
        $fromWh = Warehouse::factory()->create();
        $toWh   = Warehouse::factory()->create();

        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $fromWh->id,
            'stockLevel'   => 200,
        ]);

        $transfer = WarehouseTransfer::factory()->create([
            'from_warehouse_id' => $fromWh->id,
            'to_warehouse_id'   => $toWh->id,
            'product_id'        => $this->product->id,
            'quantity'           => 75,
            'status'            => 'pending',
            'user_id'           => $this->user->id,
        ]);

        $result = $this->service->executeTransfer($transfer);

        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->transferred_at);

        $sourceStock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $fromWh->id)->first();
        $targetStock = WarehouseHasStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $toWh->id)->first();

        $this->assertEquals(125, $sourceStock->stockLevel);
        $this->assertEquals(75, $targetStock->stockLevel);

        $this->assertDatabaseCount('stock_mouvements', 2);
    }

    public function test_execute_transfer_non_pending_throws(): void
    {
        $transfer = WarehouseTransfer::factory()->completed()->create();

        $this->expectException(\DomainException::class);
        $this->service->executeTransfer($transfer);
    }
}
