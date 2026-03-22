<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Promotion::withCount('products')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->input('status') === 'active') {
            $query->active();
        } elseif ($request->input('status') === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('ends_at', '<', now());
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function show(Promotion $promotion): JsonResponse
    {
        $promotion->load('products:id,p_title,p_code,p_salePrice,p_slug');
        $promotion->loadCount('products');

        return response()->json($promotion);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'slug'         => 'nullable|string|max:255|unique:promotions,slug',
            'description'  => 'nullable|string',
            'type'         => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'value'        => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'banner_image' => 'nullable|string|max:500',
            'banner_text'  => 'nullable|string|max:255',
            'starts_at'    => 'nullable|date',
            'ends_at'      => 'nullable|date|after_or_equal:starts_at',
            'is_active'    => 'boolean',
            'priority'     => 'integer|min:0',
            'product_ids'  => 'nullable|array',
            'product_ids.*.id'         => 'required|exists:products,id',
            'product_ids.*.promo_price' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $promotion = Promotion::create($validated);

        // Attach products
        if (!empty($validated['product_ids'])) {
            $syncData = [];
            foreach ($validated['product_ids'] as $item) {
                $syncData[$item['id']] = ['promo_price' => $item['promo_price'] ?? null];
            }
            $promotion->products()->sync($syncData);
        }

        $this->promotionService->clearCache();

        return response()->json($promotion->load('products'), 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'slug'         => ['sometimes', 'string', 'max:255', Rule::unique('promotions', 'slug')->ignore($promotion->id)],
            'description'  => 'nullable|string',
            'type'         => ['sometimes', Rule::in(['percentage', 'fixed_amount'])],
            'value'        => 'sometimes|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'banner_image' => 'nullable|string|max:500',
            'banner_text'  => 'nullable|string|max:255',
            'starts_at'    => 'nullable|date',
            'ends_at'      => 'nullable|date|after_or_equal:starts_at',
            'is_active'    => 'boolean',
            'priority'     => 'integer|min:0',
            'product_ids'  => 'nullable|array',
            'product_ids.*.id'         => 'required|exists:products,id',
            'product_ids.*.promo_price' => 'nullable|numeric|min:0',
        ]);

        $promotion->update($validated);

        if (array_key_exists('product_ids', $validated)) {
            $syncData = [];
            foreach ($validated['product_ids'] ?? [] as $item) {
                $syncData[$item['id']] = ['promo_price' => $item['promo_price'] ?? null];
            }
            $promotion->products()->sync($syncData);
        }

        $this->promotionService->clearCache();

        return response()->json($promotion->load('products'));
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();
        $this->promotionService->clearCache();

        return response()->json(['message' => 'Promotion supprimée.']);
    }
}
