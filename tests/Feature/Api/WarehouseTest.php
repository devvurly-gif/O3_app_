<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_returns_all_warehouses(): void
    {
        Warehouse::factory()->count(3)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/warehouses')
             ->assertOk()
             ->assertJsonCount(3);
    }

    public function test_show_returns_warehouse_with_stocks(): void
    {
        $wh = Warehouse::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson("/api/warehouses/{$wh->id}")
             ->assertOk()
             ->assertJsonFragment(['wh_title' => $wh->wh_title]);
    }

    public function test_store_creates_warehouse(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/warehouses', ['wh_title' => 'Depot Central'])
             ->assertCreated();

        $this->assertDatabaseHas('warehouses', ['wh_title' => 'Depot Central']);
    }

    public function test_store_validates_required_title(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/warehouses', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['wh_title']);
    }

    public function test_update_modifies_warehouse(): void
    {
        $wh = Warehouse::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/warehouses/{$wh->id}", ['wh_title' => 'Nouveau Nom'])
             ->assertOk();

        $this->assertDatabaseHas('warehouses', ['id' => $wh->id, 'wh_title' => 'Nouveau Nom']);
    }

    public function test_delete_removes_warehouse(): void
    {
        $wh = Warehouse::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/warehouses/{$wh->id}")
             ->assertNoContent();
    }

    public function test_cashier_cannot_create_warehouse(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/warehouses', ['wh_title' => 'Interdit'])
             ->assertForbidden();
    }

    public function test_any_user_can_list_warehouses(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->getJson('/api/warehouses')
             ->assertOk();
    }
}
