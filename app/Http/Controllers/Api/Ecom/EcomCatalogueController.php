<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcomCatalogueController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
    ) {
    }

    /**
     * GET /api/ecom/products
     * List eCom products with optional filters.
     */
    public function products(Request $request): JsonResponse
    {
        // Visibility chain: a product appears on the storefront only if
        //   - the product itself is published (is_ecom + p_status)
        //   - AND its category is published (or no category at all)
        //   - AND its brand is published (or no brand at all)
        // Categories/brands left NULL are treated as "no constraint" so
        // products without a category/brand still appear.
        $query = Product::where('is_ecom', true)
            ->where('p_status', true)
            ->where(function ($q) {
                $q->whereNull('category_id')
                  ->orWhereHas('category', fn ($c) => $c->where('is_ecom', true));
            })
            ->where(function ($q) {
                $q->whereNull('brand_id')
                  ->orWhereHas('brand', fn ($b) => $b->where('is_ecom', true));
            })
            ->with(['primaryImage', 'images', 'category', 'brand', 'warehouseStocks']);

        // Filter: promo only
        if ($request->boolean('promo')) {
            $products = $this->promotionService->getPromoProducts(
                $request->integer('limit', 50)
            );

            return response()->json([
                'data' => $products->map(fn ($p) => $this->promotionService->transformForEcom($p)),
            ]);
        }

        // Filter: new only
        if ($request->boolean('new')) {
            $products = $this->promotionService->getNewProducts(
                $request->integer('days', 30),
                $request->integer('limit', 20)
            );

            return response()->json([
                'data' => $products->map(fn ($p) => $this->promotionService->transformForEcom($p)),
            ]);
        }

        // Filter: category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter: brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        // Filter: price range
        if ($request->filled('price_min') || $request->filled('price_max')) {
            $min = $request->input('price_min', 0);
            $max = $request->input('price_max', PHP_FLOAT_MAX);
            $query->whereBetween('p_salePrice', [$min, $max]);
        }

        // Filter: search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('p_title', 'like', "%{$search}%")
                  ->orWhere('p_sku', 'like', "%{$search}%")
                  ->orWhere('p_ean13', 'like', "%{$search}%")
                  ->orWhere('p_description', 'like', "%{$search}%");
            });

            // Relevance ranking: an exact SKU/EAN/title match (or a title/sku
            // that starts with the query) surfaces first, ahead of products
            // that only matched via a substring/description hit. Otherwise
            // the result the customer searched for could land anywhere in
            // the grid depending on the secondary sort below.
            $query->orderByRaw(
                'CASE
                    WHEN p_sku = ? THEN 0
                    WHEN p_ean13 = ? THEN 0
                    WHEN p_title = ? THEN 1
                    WHEN p_sku LIKE ? THEN 2
                    WHEN p_title LIKE ? THEN 3
                    ELSE 4
                END',
                [$search, $search, $search, "{$search}%", "{$search}%"]
            );
        }

        // Sort
        $sortField = match ($request->input('sort')) {
            'price_asc'  => ['p_salePrice', 'asc'],
            'price_desc' => ['p_salePrice', 'desc'],
            'name'       => ['p_title', 'asc'],
            'newest'     => ['created_at', 'desc'],
            default      => ['created_at', 'desc'],
        };
        $query->orderBy($sortField[0], $sortField[1]);

        $perPage = min($request->integer('per_page', 20), 100);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(
            fn ($p) => $this->promotionService->transformForEcom($p)
        );

        return response()->json($products);
    }

    /**
     * GET /api/ecom/products/{slug}
     * Single product by slug.
     */
    public function product(string $slug): JsonResponse
    {
        // Same visibility chain as in products(): hide products whose
        // category or brand has been unpublished from the storefront.
        $product = Product::where('p_slug', $slug)
            ->where('is_ecom', true)
            ->where('p_status', true)
            ->where(function ($q) {
                $q->whereNull('category_id')
                  ->orWhereHas('category', fn ($c) => $c->where('is_ecom', true));
            })
            ->where(function ($q) {
                $q->whereNull('brand_id')
                  ->orWhereHas('brand', fn ($b) => $b->where('is_ecom', true));
            })
            ->with(['primaryImage', 'images', 'videos', 'documents', 'category', 'brand', 'warehouseStocks', 'variants.warehouseStocks'])
            ->firstOrFail();

        return response()->json($this->promotionService->transformForEcomWithVariants($product));
    }

    /**
     * GET /api/ecom/categories
     * List categories that have eCom products.
     */
    public function categories(): JsonResponse
    {
        // Storefront category list = categories themselves marked
        // is_ecom = true AND containing at least one published product.
        $categories = Category::where('is_ecom', true)
            ->whereHas('products', function ($q) {
                $q->where('is_ecom', true)->where('p_status', true);
            })
            ->withCount(['products' => fn ($q) => $q->where('is_ecom', true)->where('p_status', true)])
            ->orderBy('ctg_title')
            ->get()
            ->map(fn ($cat) => [
                'id'             => $cat->id,
                'name'           => $cat->ctg_title,
                'code'           => $cat->ctg_code,
                'products_count' => $cat->products_count,
            ]);

        return response()->json(['data' => $categories]);
    }

    /**
     * GET /api/ecom/brands
     * List brands that have eCom products.
     */
    public function brands(): JsonResponse
    {
        // Storefront brand list = brands themselves marked
        // is_ecom = true AND containing at least one published product.
        $brands = Brand::where('is_ecom', true)
            ->whereHas('products', function ($q) {
                $q->where('is_ecom', true)->where('p_status', true);
            })
            ->withCount(['products' => fn ($q) => $q->where('is_ecom', true)->where('p_status', true)])
            ->orderBy('br_title')
            ->get()
            ->map(fn ($brand) => [
                'id'             => $brand->id,
                'name'           => $brand->br_title,
                'code'           => $brand->br_code,
                'products_count' => $brand->products_count,
            ]);

        return response()->json(['data' => $brands]);
    }
}
