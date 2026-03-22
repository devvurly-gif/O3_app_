<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

class EcomPromotionController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
    ) {
    }

    /**
     * GET /api/ecom/promotions
     * List active promotions with banner info.
     */
    public function index(): JsonResponse
    {
        $promotions = Promotion::active()
            ->orderByDesc('priority')
            ->get()
            ->map(fn (Promotion $p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'slug'         => $p->slug,
                'description'  => $p->description,
                'type'         => $p->type,
                'value'        => (float) $p->value,
                'banner_image' => $p->banner_image,
                'banner_text'  => $p->banner_text,
                'starts_at'    => $p->starts_at ? \Carbon\Carbon::parse($p->starts_at)->toIso8601String() : null,
                'ends_at'      => $p->ends_at ? \Carbon\Carbon::parse($p->ends_at)->toIso8601String() : null,
                'products_count' => $p->products()->count(),
            ]);

        return response()->json(['data' => $promotions]);
    }

    /**
     * GET /api/ecom/promotions/{slug}
     * Single promotion with its products.
     */
    public function show(string $slug): JsonResponse
    {
        $promotion = Promotion::active()
            ->where('slug', $slug)
            ->firstOrFail();

        $products = $promotion->products()
            ->where('is_ecom', true)
            ->where('p_status', true)
            ->with(['primaryImage', 'images', 'category', 'brand', 'warehouseStocks'])
            ->get()
            ->map(fn ($p) => $this->promotionService->transformForEcom($p));

        return response()->json([
            'promotion' => [
                'id'           => $promotion->id,
                'name'         => $promotion->name,
                'slug'         => $promotion->slug,
                'description'  => $promotion->description,
                'type'         => $promotion->type,
                'value'        => (float) $promotion->value,
                'banner_image' => $promotion->banner_image,
                'banner_text'  => $promotion->banner_text,
                'starts_at'    => $promotion->starts_at ? \Carbon\Carbon::parse($promotion->starts_at)->toIso8601String() : null,
                'ends_at'      => $promotion->ends_at ? \Carbon\Carbon::parse($promotion->ends_at)->toIso8601String() : null,
            ],
            'products' => $products,
        ]);
    }
}
