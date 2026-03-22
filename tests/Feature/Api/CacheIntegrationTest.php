<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Cache::flush();
    }

    public function test_brands_are_cached_on_index(): void
    {
        Brand::factory()->count(3)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/brands')
             ->assertOk();

        $this->assertNotNull(Cache::get(CacheService::brandsKey()));
    }

    public function test_brand_create_invalidates_cache(): void
    {
        Cache::put(CacheService::brandsKey(), 'stale', 900);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/brands', ['br_title' => 'New Brand'])
             ->assertCreated();

        $this->assertNull(Cache::get(CacheService::brandsKey()));
    }

    public function test_categories_are_cached_on_index(): void
    {
        Category::factory()->count(2)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/categories')
             ->assertOk();

        $this->assertNotNull(Cache::get(CacheService::categoriesKey()));
    }

    public function test_category_update_invalidates_cache(): void
    {
        $cat = Category::factory()->create();
        Cache::put(CacheService::categoriesKey(), 'stale', 900);

        $this->actingAs($this->admin, 'sanctum')
             ->putJson("/api/categories/{$cat->id}", ['ctg_title' => 'Updated'])
             ->assertOk();

        $this->assertNull(Cache::get(CacheService::categoriesKey()));
    }

    public function test_warehouses_are_cached_on_index(): void
    {
        Warehouse::factory()->count(2)->create();

        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/warehouses')
             ->assertOk();

        $this->assertNotNull(Cache::get(CacheService::warehousesKey()));
    }

    public function test_warehouse_delete_invalidates_cache(): void
    {
        $wh = Warehouse::factory()->create();
        Cache::put(CacheService::warehousesKey(), 'stale', 900);

        $this->actingAs($this->admin, 'sanctum')
             ->deleteJson("/api/warehouses/{$wh->id}")
             ->assertNoContent();

        $this->assertNull(Cache::get(CacheService::warehousesKey()));
    }

    public function test_dashboard_is_cached(): void
    {
        $this->actingAs($this->admin, 'sanctum')
             ->getJson('/api/dashboard')
             ->assertOk();

        $this->assertNotNull(Cache::get(CacheService::dashboardKey()));
    }

    public function test_admin_cache_flush_clears_all(): void
    {
        Cache::put(CacheService::brandsKey(), 'brands', 900);
        Cache::put(CacheService::categoriesKey(), 'categories', 900);
        Cache::put(CacheService::dashboardKey(), 'dashboard', 60);

        $this->actingAs($this->admin, 'sanctum')
             ->postJson('/api/cache/flush')
             ->assertOk()
             ->assertJsonFragment(['message' => 'Cache vidé avec succès.']);

        $this->assertNull(Cache::get(CacheService::brandsKey()));
        $this->assertNull(Cache::get(CacheService::categoriesKey()));
        $this->assertNull(Cache::get(CacheService::dashboardKey()));
    }

    public function test_non_admin_cannot_flush_cache(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier, 'sanctum')
             ->postJson('/api/cache/flush')
             ->assertForbidden();
    }
}
