<?php

namespace Tests\Unit\Services;

use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_remember_stores_and_retrieves_value(): void
    {
        $value = CacheService::remember('test:key', 60, fn () => 'hello');

        $this->assertEquals('hello', $value);
        $this->assertEquals('hello', Cache::get('test:key'));
    }

    public function test_remember_returns_cached_value_on_second_call(): void
    {
        $callCount = 0;
        $callback  = function () use (&$callCount) {
            $callCount++;
            return 'computed';
        };

        CacheService::remember('test:counter', 60, $callback);
        CacheService::remember('test:counter', 60, $callback);

        $this->assertEquals(1, $callCount);
    }

    public function test_forget_removes_key(): void
    {
        Cache::put('test:remove', 'value', 60);

        CacheService::forget('test:remove');

        $this->assertNull(Cache::get('test:remove'));
    }

    public function test_forget_multiple_keys(): void
    {
        Cache::put('key1', 'a', 60);
        Cache::put('key2', 'b', 60);

        CacheService::forget('key1', 'key2');

        $this->assertNull(Cache::get('key1'));
        $this->assertNull(Cache::get('key2'));
    }

    public function test_track_and_forget_by_prefix(): void
    {
        CacheService::trackKey('products', 'products:page:abc');
        CacheService::trackKey('products', 'products:page:def');
        Cache::put('products:page:abc', 'data1', 60);
        Cache::put('products:page:def', 'data2', 60);

        CacheService::forgetByPrefix('products');

        $this->assertNull(Cache::get('products:page:abc'));
        $this->assertNull(Cache::get('products:page:def'));
    }

    public function test_dashboard_key_is_consistent(): void
    {
        $this->assertEquals('dashboard:kpis', CacheService::dashboardKey());
    }

    public function test_brands_key_is_consistent(): void
    {
        $this->assertEquals('ref:brands', CacheService::brandsKey());
    }

    public function test_categories_key_is_consistent(): void
    {
        $this->assertEquals('ref:categories', CacheService::categoriesKey());
    }

    public function test_warehouses_key_is_consistent(): void
    {
        $this->assertEquals('ref:warehouses', CacheService::warehousesKey());
    }

    public function test_products_page_key_varies_by_params(): void
    {
        $key1 = CacheService::productsPageKey(['page' => 1]);
        $key2 = CacheService::productsPageKey(['page' => 2]);

        $this->assertNotEquals($key1, $key2);
        $this->assertStringStartsWith('products:page:', $key1);
    }

    public function test_flush_brands_clears_brands_and_dashboard(): void
    {
        Cache::put(CacheService::brandsKey(), 'brands', 60);
        Cache::put(CacheService::dashboardKey(), 'dashboard', 60);

        CacheService::flushBrands();

        $this->assertNull(Cache::get(CacheService::brandsKey()));
        $this->assertNull(Cache::get(CacheService::dashboardKey()));
    }

    public function test_flush_categories_clears_categories_only(): void
    {
        Cache::put(CacheService::categoriesKey(), 'categories', 60);
        Cache::put(CacheService::dashboardKey(), 'dashboard', 60);

        CacheService::flushCategories();

        $this->assertNull(Cache::get(CacheService::categoriesKey()));
        $this->assertNotNull(Cache::get(CacheService::dashboardKey()));
    }

    public function test_flush_products_clears_products_and_dashboard(): void
    {
        CacheService::trackKey('products', 'products:page:xyz');
        Cache::put('products:page:xyz', 'data', 60);
        Cache::put(CacheService::dashboardKey(), 'dashboard', 60);

        CacheService::flushProducts();

        $this->assertNull(Cache::get('products:page:xyz'));
        $this->assertNull(Cache::get(CacheService::dashboardKey()));
    }

    public function test_flush_documents_clears_documents_and_dashboard(): void
    {
        CacheService::trackKey('documents', 'documents:page:abc');
        Cache::put('documents:page:abc', 'data', 60);
        Cache::put(CacheService::dashboardKey(), 'dashboard', 60);

        CacheService::flushDocuments();

        $this->assertNull(Cache::get('documents:page:abc'));
        $this->assertNull(Cache::get(CacheService::dashboardKey()));
    }
}
