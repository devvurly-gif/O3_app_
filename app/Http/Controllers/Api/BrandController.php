<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private BrandRepositoryInterface $brands)
    {
    }

    public function index(): JsonResponse
    {
        $data = CacheService::remember(
            CacheService::brandsKey(),
            CacheService::TTL_LONG,
            fn () => $this->brands->all(orderBy: 'br_title')
        );

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'br_title'  => ['required', 'string', 'max:255'],
            'br_status' => ['boolean'],
            'is_ecom'   => ['sometimes', 'boolean'],
        ]);

        if (!$this->ecomEnabled()) {
            unset($data['is_ecom']);
        }

        $brand = $this->brands->create($data);
        CacheService::flushBrands();

        return response()->json($brand, 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json($brand->load('products'));
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'br_title'  => ['sometimes', 'string', 'max:255'],
            'br_status' => ['sometimes', 'boolean'],
            'is_ecom'   => ['sometimes', 'boolean'],
        ]);

        if (!$this->ecomEnabled()) {
            unset($data['is_ecom']);
        }

        $this->brands->update($brand, $data);
        CacheService::flushBrands();

        return response()->json($brand);
    }

    /**
     * Whether the current tenant has the e-commerce module enabled.
     * Same defense-in-depth pattern as ProductController: even if the
     * client posts is_ecom, we drop it server-side when the feature is off.
     */
    private function ecomEnabled(): bool
    {
        $t = function_exists('tenant') ? tenant() : null;
        return (bool) ($t?->ecom_enabled ?? false);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brands->delete($brand);
        CacheService::flushBrands();

        return response()->json(null, 204);
    }
}
