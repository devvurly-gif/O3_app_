<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use App\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $fromWh;
    private Warehouse $toWh;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->admin()->create();
        $this->fromWh  = Warehouse::factory()->create();
        $this->toWh    = Warehouse::factory()->create();
        $this->product = Product::factory()->create();
    }

    public function test_index_returns_paginated_transfers(): void
    {
        WarehouseTransfer::factory()->count(3)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/warehouse-transfers')
             ->assertOk()
             ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_store_creates_pending_transfer(): void
    {
        $payload = [
            'from_warehouse_id' => $this->fromWh->id,
            'to_warehouse_id'   => $this->toWh->id,
            'product_id'        => $this->product->id,
            'quantity'          => 25,
            'notes'             => 'Test transfer',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->postJson('/api/warehouse-transfers', $payload);

        $response->assertCreated()
                 ->assertJsonFragment(['status' => 'pending']);

        $this->assertDatabaseHas('warehouse_transfers', [
            'from_warehouse_id' => $this->fromWh->id,
            'to_warehouse_id'   => $this->toWh->id,
            'quantity'          => 25,
            'status'            => 'pending',
        ]);
    }

    public function test_store_rejects_same_warehouse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/warehouse-transfers', [
                 'from_warehouse_id' => $this->fromWh->id,
                 'to_warehouse_id'   => $this->fromWh->id,
                 'product_id'        => $this->product->id,
                 'quantity'          => 10,
             ])
             ->assertUnprocessable();
    }

    public function test_execute_moves_stock(): void
    {
        WarehouseHasStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->fromWh->id,
            'stockLevel'   => 100,
        ]);

        $transfer = WarehouseTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWh->id,
            'to_warehouse_id'   => $this->toWh->id,
            'product_id'        => $this->product->id,
            'quantity'          => 40,
            'status'            => 'pending',
            'user_id'           => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson("/api/warehouse-transfers/{$transfer->id}/execute")
             ->assertOk()
             ->assertJsonFragment(['status' => 'completed']);

        $this->assertEquals(60, WarehouseHasStock::where([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->fromWh->id,
        ])->first()->stockLevel);

        $this->assertEquals(40, WarehouseHasStock::where([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->toWh->id,
        ])->first()->stockLevel);
    }

    public function test_cannot_execute_completed_transfer(): void
    {
        $transfer = WarehouseTransfer::factory()->completed()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson("/api/warehouse-transfers/{$transfer->id}/execute")
             ->assertUnprocessable();
    }

    public function test_cancel_sets_status_cancelled(): void
    {
        $transfer = WarehouseTransfer::factory()->pending()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson("/api/warehouse-transfers/{$transfer->id}/cancel")
             ->assertOk();

        $this->assertDatabaseHas('warehouse_transfers', [
            'id'     => $transfer->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_completed_transfer(): void
    {
        $transfer = WarehouseTransfer::factory()->completed()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson("/api/warehouse-transfers/{$transfer->id}/cancel")
             ->assertUnprocessable();
    }

    public function test_delete_only_works_for_pending(): void
    {
        $transfer = WarehouseTransfer::factory()->pending()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/warehouse-transfers/{$transfer->id}")
             ->assertNoContent();
    }

    public function test_cashier_cannot_manage_transfers(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/warehouse-transfers', [
                 'from_warehouse_id' => $this->fromWh->id,
                 'to_warehouse_id'   => $this->toWh->id,
                 'product_id'        => $this->product->id,
                 'quantity'          => 10,
             ])
             ->assertForbidden();
    }

    public function test_warehouse_user_can_create_transfer(): void
    {
        $whUser = User::factory()->warehouse()->create();

        $this->actingAs($whUser, 'sanctum')
             ->postJson('/api/warehouse-transfers', [
                 'from_warehouse_id' => $this->fromWh->id,
                 'to_warehouse_id'   => $this->toWh->id,
                 'product_id'        => $this->product->id,
                 'quantity'          => 5,
             ])
             ->assertCreated();
    }
}
