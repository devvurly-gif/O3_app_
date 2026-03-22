<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PromotionService
{
    private const CACHE_TTL = 120; // 2 minutes

    /**
     * Get active promotions (cached).
     */
    public function getActivePromotions(): Collection
    {
        return Cache::remember('ecom.promotions.active', self::CACHE_TTL, function () {
            return Promotion::active()
                ->orderByDesc('priority')
                ->with('products')
                ->get();
        });
    }

    /**
     * Enrich a product with its promo data.
     * Returns: [promo_price, discount_percent, promotion_name, has_promo]
     */
    public function getProductPromoData(Product $product): array
    {
        $promo = $this->getBestPromotionForProduct($product);

        if (!$promo) {
            return [
                'has_promo'        => false,
                'promo_price'      => null,
                'discount_percent' => null,
                'promotion_name'   => null,
                'promotion_slug'   => null,
            ];
        }

        // Check if there's a forced promo_price on the pivot
        $pivot = $promo->pivot ?? null;
        $forcedPrice = $pivot?->promo_price;

        if ($forcedPrice && $forcedPrice > 0) {
            $promoPrice = (float) $forcedPrice;
        } else {
            $promoPrice = $promo->calculateDiscount((float) $product->p_salePrice);
        }

        $originalPrice = (float) $product->p_salePrice;
        $discountPercent = $originalPrice > 0
            ? round((($originalPrice - $promoPrice) / $originalPrice) * 100)
            : 0;

        return [
            'has_promo'        => true,
            'promo_price'      => $promoPrice,
            'discount_percent' => $discountPercent,
            'promotion_name'   => $promo->name,
            'promotion_slug'   => $promo->slug,
        ];
    }

    /**
     * Get the best (highest priority) active promotion for a product.
     */
    public function getBestPromotionForProduct(Product $product): ?Promotion
    {
        return Promotion::active()
            ->whereHas('products', fn ($q) => $q->where('products.id', $product->id))
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Get all products in promotion (for eCom listing).
     */
    public function getPromoProducts(int $limit = 50): Collection
    {
        return Cache::remember("ecom.promo_products.{$limit}", self::CACHE_TTL, function () use ($limit) {
            $activePromoIds = Promotion::active()->pluck('id');

            if ($activePromoIds->isEmpty()) {
                return collect();
            }

            return Product::where('is_ecom', true)
                ->where('p_status', true)
                ->whereHas('promotions', fn ($q) => $q->whereIn('promotions.id', $activePromoIds))
                ->with(['primaryImage', 'category', 'brand', 'warehouseStocks', 'promotions' => fn ($q) => $q->active()])
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get new products (created in the last X days).
     */
    public function getNewProducts(int $days = 30, int $limit = 20): Collection
    {
        return Cache::remember("ecom.new_products.{$days}.{$limit}", self::CACHE_TTL, function () use ($days, $limit) {
            return Product::where('is_ecom', true)
                ->where('p_status', true)
                ->where('created_at', '>=', now()->subDays($days))
                ->with(['primaryImage', 'category', 'brand', 'warehouseStocks'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Transform a product for eCom API response.
     */
    public function transformForEcom(Product $product): array
    {
        $promoData = $this->getProductPromoData($product);

        return [
            'id'                => $product->id,
            'title'             => $product->p_title,
            'slug'              => $product->p_slug,
            'code'              => $product->p_code,
            'sku'               => $product->p_sku,
            'ean13'             => $product->p_ean13,
            'description'       => $product->p_description,
            'long_description'  => $product->p_long_description,
            'price'             => (float) $product->p_salePrice,
            'price_ttc'         => $product->salePriceTtc(),
            'tax_rate'          => (float) $product->p_taxRate,
            'in_stock'          => $product->total_stock > 0,
            'stock_level'       => $product->total_stock,
            'category'          => $product->category ? [
                'id'   => $product->category->id,
                'name' => $product->category->ctg_title,
            ] : null,
            'brand'             => $product->brand ? [
                'id'   => $product->brand->id,
                'name' => $product->brand->br_title,
            ] : null,
            'image'             => $product->primaryImage?->url,
            'images'            => $product->images->map(fn ($img) => [
                'url'       => $img->url,
                'alt'       => $img->altContent,
                'isPrimary' => $img->isPrimary,
            ]),
            'is_new'            => $product->created_at->gte(now()->subDays(30)),
            // Promo data
            'has_promo'         => $promoData['has_promo'],
            'promo_price'       => $promoData['promo_price'],
            'promo_price_ttc'   => $promoData['promo_price']
                ? round($promoData['promo_price'] * (1 + (float) $product->p_taxRate / 100), 2)
                : null,
            'discount_percent'  => $promoData['discount_percent'],
            'promotion_name'    => $promoData['promotion_name'],
            'promotion_slug'    => $promoData['promotion_slug'],
        ];
    }

    /**
     * Clear all eCom caches.
     */
    public function clearCache(): void
    {
        Cache::forget('ecom.promotions.active');
        // Clear promo_products and new_products with common limits
        foreach ([10, 20, 50] as $limit) {
            Cache::forget("ecom.promo_products.{$limit}");
        }
        foreach ([30] as $days) {
            foreach ([10, 20] as $limit) {
                Cache::forget("ecom.new_products.{$days}.{$limit}");
            }
        }
    }
}
