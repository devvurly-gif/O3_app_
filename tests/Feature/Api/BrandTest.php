<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_index_returns_all_brands(): void
    {
        Brand::factory()->count(5)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/brands')
             ->assertOk()
             ->assertJsonCount(5);
    }

    public function test_show_returns_brand_with_products(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson("/api/brands/{$brand->id}")
             ->assertOk()
             ->assertJsonFragment(['br_title' => $brand->br_title]);
    }

    public function test_store_creates_brand(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/brands', ['br_title' => 'Nike'])
             ->assertCreated();

        $this->assertDatabaseHas('brands', ['br_title' => 'Nike']);
    }

    public function test_store_validates_required_title(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/brands', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['br_title']);
    }

    public function test_update_modifies_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/brands/{$brand->id}", ['br_title' => 'Adidas'])
             ->assertOk();

        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'br_title' => 'Adidas']);
    }

    public function test_delete_removes_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/brands/{$brand->id}")
             ->assertNoContent();
    }

    public function test_cashier_cannot_create_brand(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/brands', ['br_title' => 'Test'])
             ->assertForbidden();
    }

    public function test_any_user_can_list_brands(): void
    {
        $warehouse = User::factory()->warehouse()->create();

        $this->actingAs($warehouse, 'sanctum')
             ->getJson('/api/brands')
             ->assertOk();
    }
}
