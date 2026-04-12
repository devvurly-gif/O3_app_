<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Slide;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // Handle banner_image file upload
        if ($request->hasFile('banner_image_file')) {
            $request->validate(['banner_image_file' => 'image|max:2048']);
            $path = $request->file('banner_image_file')->store('promotions', 'public');
            $validated['banner_image'] = '/storage/' . $path;
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

        // Auto-create a hero slide if banner_image is set
        if (!empty($promotion->banner_image)) {
            Slide::create([
                'title'       => $promotion->name,
                'subtitle'    => $promotion->banner_text,
                'image'       => $promotion->banner_image,
                'button_text' => 'Voir la promo',
                'link_type'   => 'promotion',
                'link_id'     => $promotion->id,
                'position'    => 'hero',
                'starts_at'   => $promotion->starts_at,
                'ends_at'     => $promotion->ends_at,
                'is_active'   => $promotion->is_active ?? true,
            ]);
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

        // Handle banner_image file upload
        if ($request->hasFile('banner_image_file')) {
            $request->validate(['banner_image_file' => 'image|max:2048']);
            // Delete old banner if stored locally
            if ($promotion->banner_image && str_starts_with($promotion->banner_image, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $promotion->banner_image));
            }
            $path = $request->file('banner_image_file')->store('promotions', 'public');
            $validated['banner_image'] = '/storage/' . $path;
        }

        $promotion->update($validated);

        if (array_key_exists('product_ids', $validated)) {
            $syncData = [];
            foreach ($validated['product_ids'] ?? [] as $item) {
                $syncData[$item['id']] = ['promo_price' => $item['promo_price'] ?? null];
            }
            $promotion->products()->sync($syncData);
        }

        // Auto-sync linked slide
        $linkedSlide = Slide::where('link_type', 'promotion')->where('link_id', $promotion->id)->first();

        if (!empty($promotion->banner_image)) {
            $slideData = [
                'title'       => $promotion->name,
                'subtitle'    => $promotion->banner_text,
                'image'       => $promotion->banner_image,
                'button_text' => 'Voir la promo',
                'link_type'   => 'promotion',
                'link_id'     => $promotion->id,
                'position'    => 'hero',
                'starts_at'   => $promotion->starts_at,
                'ends_at'     => $promotion->ends_at,
                'is_active'   => $promotion->is_active ?? true,
            ];

            if ($linkedSlide) {
                $linkedSlide->update($slideData);
            } else {
                Slide::create($slideData);
            }
        } elseif ($linkedSlide) {
            // Banner removed → delete linked slide
            $linkedSlide->delete();
        }

        $this->promotionService->clearCache();

        return response()->json($promotion->load('products'));
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        // Delete linked slide(s)
        Slide::where('link_type', 'promotion')->where('link_id', $promotion->id)->delete();

        $promotion->delete();
        $this->promotionService->clearCache();

        return response()->json(['message' => 'Promotion supprimée.']);
    }
}
