<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PromotionService
{
    private const CACHE_TTL = 120; // 2 minutes

    public function __construct(
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * Base query for products visible on the storefront.
     *
     * Visibility chain: product is published AND active, AND its
     * category is published (or null), AND its brand is published
     * (or null). Mirrors EcomCatalogueController so promo / new /
     * standard listings stay consistent.
     */
    private function visibleEcomQuery()
    {
        return Product::where('is_ecom', true)
            ->where('p_status', true)
            ->where(function ($q) {
                $q->whereNull('category_id')
                  ->orWhereHas('category', fn ($c) => $c->where('is_ecom', true));
            })
            ->where(function ($q) {
                $q->whereNull('brand_id')
                  ->orWhereHas('brand', fn ($b) => $b->where('is_ecom', true));
            });
    }

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
    /**
     * @param float|null $baseHt Prix HT servant de référence à la remise. La
     *   boutique passe le tarif de grille résolu : sans cela, le pourcentage
     *   affiché serait calculé sur p_salePrice et ne correspondrait plus au
     *   prix montré au client.
     */
    public function getProductPromoData(Product $product, ?float $baseHt = null): array
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

        $originalPrice = $baseHt ?? (float) $product->p_salePrice;

        if ($forcedPrice && $forcedPrice > 0) {
            $promoPrice = (float) $forcedPrice;
        } else {
            $promoPrice = $promo->calculateDiscount($originalPrice);
        }

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

            return $this->visibleEcomQuery()
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
            return $this->visibleEcomQuery()
                ->where('created_at', '>=', now()->subDays($days))
                ->with(['primaryImage', 'category', 'brand', 'warehouseStocks'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Un libellé fourre-tout de l'ERP, à masquer sur la boutique ?
     *
     * Comparaison insensible à la casse et aux espaces de bord : ces libellés
     * sont saisis à la création du tenant, pas garantis normalisés.
     */
    public static function isPlaceholder(?string $label, string $type): bool
    {
        if ($label === null) {
            return false;
        }

        $placeholder = config("ecom.placeholder_labels.{$type}");

        return $placeholder !== null
            && mb_strtolower(trim($label)) === mb_strtolower($placeholder);
    }

    /**
     * Transform a product for eCom API response.
     */
    public function transformForEcom(Product $product): array
    {
        // Le prix affiché en boutique vient de la grille ECommerce quand elle
        // couvre le produit, sinon de la grille par défaut, sinon du prix de
        // vente. La remise se calcule sur ce même prix.
        $tarif     = $this->priceResolver->resolveForStorefront($product);
        $promoData = $this->getProductPromoData($product, $tarif['price_ht']);

        return [
            'id'                => $product->id,
            'title'             => $product->p_title,
            'slug'              => $product->p_slug,
            'code'              => $product->p_code,
            'sku'               => $product->p_sku,
            'ean13'             => $product->p_ean13,
            'description'       => $product->p_description,
            'long_description'  => $product->p_long_description,
            'price'             => $tarif['price_ht'],
            'price_ttc'         => $tarif['price_ttc'],
            'price_source'      => $tarif['source'],
            'tax_rate'          => (float) $product->p_taxRate,
            'in_stock'          => $product->total_stock > 0,
            'stock_level'       => $product->total_stock,
            // Les fourre-tout « Non catégorisé » / « Marque inconnue » sont des
            // béquilles internes de l'ERP : sur la boutique, on les renvoie comme
            // une absence de rattachement plutôt qu'en badge au-dessus du produit.
            'category'          => $this->isPlaceholder($product->category?->ctg_title, 'category') ? null
                : ($product->category ? [
                    'id'   => $product->category->id,
                    'name' => $product->category->ctg_title,
                ] : null),
            'brand'             => $this->isPlaceholder($product->brand?->br_title, 'brand') ? null
                : ($product->brand ? [
                    'id'   => $product->brand->id,
                    'name' => $product->brand->br_title,
                ] : null),
            'image'             => $product->primaryImage?->url,
            'images'            => $product->images->map(fn ($img) => [
                'url'       => $img->url,
                'alt'       => $img->altContent,
                'isPrimary' => $img->isPrimary,
            ]),
            'videos'            => $product->relationLoaded('videos') ? $product->videos->map(fn ($video) => [
                'id'    => $video->id,
                'title' => $video->title,
                'url'   => $video->url,
            ]) : [],
            'documents'         => $product->relationLoaded('documents') ? $product->documents->map(fn ($doc) => [
                'id'        => $doc->id,
                'title'     => $doc->title,
                'file_name' => $doc->file_name,
                'url'       => $doc->url,
                'mime_type' => $doc->mime_type,
                'size'      => $doc->size,
            ]) : [],
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
            'has_variants'      => false,
            'variants'          => [],
        ];
    }

    public function transformForEcomWithVariants(Product $product): array
    {
        $base = $this->transformForEcom($product);

        $product->loadMissing(['variants.warehouseStocks']);

        if ($product->variants->isEmpty()) {
            return $base;
        }

        $variants = $product->variants
            ->where('is_active', true)
            ->map(fn ($v) => [
                'id'              => $v->id,
                'label'           => $v->label,
                'sku'             => $v->sku,
                'price'           => $v->price ? (float) $v->price : $base['price'],
                'price_ttc'       => $v->price
                    ? round((float) $v->price * (1 + (float) $product->p_taxRate / 100), 2)
                    : $base['price_ttc'],
                'stock_available' => (float) $v->warehouseStocks->sum('stockLevel'),
                'in_stock'        => $v->warehouseStocks->sum('stockLevel') > 0,
            ])
            ->values();

        $base['has_variants'] = true;
        $base['variants']     = $variants;
        $base['in_stock']     = $variants->contains('in_stock', true);
        $base['stock_level']  = $variants->sum('stock_available');

        return $base;
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
