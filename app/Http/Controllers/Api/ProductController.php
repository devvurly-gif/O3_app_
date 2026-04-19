<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductRepositoryInterface $products)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->products->paginate(
                perPage: (int) $request->input('per_page', 15),
                with: ['category', 'brand', 'images', 'primaryImage', 'warehouseStocks'],
                orderBy: $request->input('sort', 'p_title'),
                direction: $request->input('order', 'asc'),
                filters: array_filter([
                    'search' => $request->search ? [
                        'columns' => ['p_title', 'p_sku', 'p_ean13', 'p_description'],
                        'value'   => $request->search,
                    ] : null,
                    'category_id' => $request->category_id,
                    'brand_id'    => $request->brand_id,
                    'p_status'    => $request->has('status') ? $request->boolean('status') : null,
                ])
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'p_title'         => ['required', 'string', 'max:255'],
            'p_description'   => ['nullable', 'string'],
            'p_sku'           => ['nullable', 'string', 'max:100'],
            'p_ean13'         => ['nullable', 'string', 'max:13'],
            'p_imei'          => ['nullable', 'string', 'max:50'],
            'p_purchasePrice' => ['required', 'numeric', 'min:0'],
            'p_salePrice'     => ['required', 'numeric', 'min:0'],
            'p_cost'          => ['nullable', 'numeric', 'min:0'],
            'p_taxRate'       => ['nullable', 'numeric', 'min:0'],
            'p_unit'          => ['nullable', 'string', 'max:50'],
            'p_status'        => ['boolean'],
            'p_notes'         => ['nullable', 'string'],
            'category_id'     => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'        => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $product = $this->products->create($data);
        CacheService::flushProducts();

        return response()->json($product->load(['category', 'brand']), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(
            $product->load(['category', 'brand', 'images', 'warehouseStocks.warehouse'])
        );
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'p_title'         => ['sometimes', 'string', 'max:255'],
            'p_description'   => ['nullable', 'string'],
            'p_sku'           => ['nullable', 'string', 'max:100'],
            'p_ean13'         => ['nullable', 'string', 'max:13'],
            'p_imei'          => ['nullable', 'string', 'max:50'],
            'p_purchasePrice' => ['sometimes', 'numeric', 'min:0'],
            'p_salePrice'     => ['sometimes', 'numeric', 'min:0'],
            'p_cost'          => ['nullable', 'numeric', 'min:0'],
            'p_taxRate'       => ['nullable', 'numeric', 'min:0'],
            'p_unit'          => ['nullable', 'string', 'max:50'],
            'p_status'        => ['sometimes', 'boolean'],
            'p_notes'         => ['nullable', 'string'],
            'category_id'     => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'        => ['nullable', 'integer', 'exists:brands,id'],
        ]);

        $this->products->update($product, $data);
        CacheService::flushProducts();

        return response()->json($product->load(['category', 'brand']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);
        CacheService::flushProducts();

        return response()->json(null, 204);
    }
}
