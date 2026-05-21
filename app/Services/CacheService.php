<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    // TTLs in seconds
    public const TTL_SHORT     = 15;        // 15s — dashboard live refresh
    public const TTL_MEDIUM    = 300;       // 5 min  — dashboard charts, aggregations
    public const TTL_LONG      = 900;       // 15 min — reference lists (brands, categories, warehouses)
    public const TTL_VERY_LONG = 3600;      // 1 hour — rarely changing data

    public static function remember(string $key, int $ttl, Closure $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\BadMethodCallException) {
            // File/array driver does not support tagging (Stancl tenancy wraps with tags).
            // Fall through to execute the callback directly.
            return $callback();
        }
    }

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            try {
                Cache::forget($key);
            } catch (\BadMethodCallException) {
                // Ignore — file driver with Stancl tenancy tagging
            }
        }
    }

    public static function forgetByPrefix(string $prefix): void
    {
        try {
            $knownKeys = Cache::get("cache_keys:{$prefix}", []);
            foreach ($knownKeys as $key) {
                Cache::forget($key);
            }
            Cache::forget("cache_keys:{$prefix}");
        } catch (\BadMethodCallException) {
            // file driver + Stancl tenancy tagging
        }
    }

    public static function trackKey(string $prefix, string $key): void
    {
        try {
            $tracked = Cache::get("cache_keys:{$prefix}", []);
            if (!in_array($key, $tracked)) {
                $tracked[] = $key;
                Cache::put("cache_keys:{$prefix}", $tracked, self::TTL_VERY_LONG);
            }
        } catch (\BadMethodCallException) {
            // file driver + Stancl tenancy tagging
        }
    }

    // ── Known cache key builders ──────────────────────────────────────

    public static function dashboardKey(): string
    {
        return 'dashboard:kpis';
    }

    public static function brandsKey(): string
    {
        return 'ref:brands';
    }

    public static function categoriesKey(): string
    {
        return 'ref:categories';
    }

    public static function warehousesKey(): string
    {
        return 'ref:warehouses';
    }

    public static function productsPageKey(array $params): string
    {
        $key = 'products:page:' . md5(serialize($params));
        self::trackKey('products', $key);
        return $key;
    }

    public static function thirdPartnersPageKey(array $params): string
    {
        $key = 'partners:page:' . md5(serialize($params));
        self::trackKey('partners', $key);
        return $key;
    }

    public static function documentsPageKey(array $params): string
    {
        $key = 'documents:page:' . md5(serialize($params));
        self::trackKey('documents', $key);
        return $key;
    }

    // ── Bulk invalidation helpers ─────────────────────────────────────

    public static function flushDashboard(): void
    {
        self::forget(self::dashboardKey());
    }

    public static function flushBrands(): void
    {
        self::forget(self::brandsKey());
        self::flushDashboard();
    }

    public static function flushCategories(): void
    {
        self::forget(self::categoriesKey());
    }

    public static function flushWarehouses(): void
    {
        self::forget(self::warehousesKey());
        self::flushDashboard();
    }

    public static function flushProducts(): void
    {
        self::forgetByPrefix('products');
        self::flushDashboard();
    }

    public static function flushPartners(): void
    {
        self::forgetByPrefix('partners');
        self::flushDashboard();
    }

    public static function flushDocuments(): void
    {
        self::forgetByPrefix('documents');
        self::flushDashboard();
    }
}
