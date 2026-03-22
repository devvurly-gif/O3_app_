<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ── Index ────────────────────────────────────────────────────

    public function test_index_returns_paginated_products(): void
    {
        Product::factory()->count(20)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/products?per_page=10');

        $response->assertOk()
                 ->assertJsonStructure(['data', 'current_page', 'last_page', 'total'])
                 ->assertJsonCount(10, 'data');
    }

    public function test_index_filters_by_search(): void
    {
        Product::factory()->create(['p_title' => 'Widget Alpha']);
        Product::factory()->create(['p_title' => 'Gadget Beta']);

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/products?search=Widget');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Widget Alpha', $response->json('data.0.p_title'));
    }

    public function test_index_filters_by_category(): void
    {
        $cat = Category::factory()->create();
        Product::factory()->create(['category_id' => $cat->id]);
        Product::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->getJson('/api/products?category_id=' . $cat->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ── Show ─────────────────────────────────────────────────────

    public function test_show_returns_single_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson("/api/products/{$product->id}")
             ->assertOk()
             ->assertJsonFragment(['p_title' => $product->p_title]);
    }

    // ── Store ────────────────────────────────────────────────────

    public function test_store_creates_product(): void
    {
        $category = Category::factory()->create();
        $brand    = Brand::factory()->create();

        $payload = [
            'p_title'         => 'Nouveau Produit',
            'p_sku'           => 'SKU-TEST-001',
            'p_purchasePrice' => 100.00,
            'p_salePrice'     => 200.00,
            'p_cost'          => 50.00,
            'p_taxRate'       => 20,
            'p_unit'          => 'pcs',
            'category_id'     => $category->id,
            'brand_id'        => $brand->id,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
                         ->postJson('/api/products', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['p_title' => 'Nouveau Produit']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/products', [])
             ->assertUnprocessable();
    }

    // ── Update ───────────────────────────────────────────────────

    public function test_update_modifies_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/products/{$product->id}", [
                 'p_title' => 'Titre Modifié',
             ])
             ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'p_title' => 'Titre Modifié']);
    }

    // ── Delete ───────────────────────────────────────────────────

    public function test_delete_soft_deletes_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/products/{$product->id}")
             ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // ── Authorization ────────────────────────────────────────────

    public function test_cashier_cannot_create_product(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/products', ['p_title' => 'Test'])
             ->assertForbidden();
    }

    public function test_any_authenticated_user_can_list_products(): void
    {
        $warehouse = User::factory()->warehouse()->create();

        $this->actingAs($warehouse, 'sanctum')
             ->getJson('/api/products')
             ->assertOk();
    }
}
