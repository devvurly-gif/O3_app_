<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseHasStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    // GET /products/{id}/variants
    public function index(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $variants = $product->variants()->with('warehouseStocks')->get()
            ->map(function ($v) {
                return array_merge($v->toArray(), [
                    'stock' => (float) $v->warehouseStocks->sum('stockLevel'),
                ]);
            });
        return response()->json($variants);
    }

    // POST /products/{id}/variants/sync
    public function sync(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'variants'              => 'required|array',
            'variants.*.label'      => 'required|string|max:255',
            'variants.*.attributes' => 'sometimes|array',
            'variants.*.sku'        => 'nullable|string|max:100',
            'variants.*.price'      => 'nullable|numeric|min:0',
            'variants.*.is_active'  => 'nullable|boolean',
            'variants.*.position'   => 'nullable|integer',
        ]);

        $incoming   = collect($data['variants']);
        $incomingIds = $incoming->pluck('id')->filter()->values()->all();

        $product->variants()->whereNotIn('id', $incomingIds)->delete();

        // `$warehouse` était capturé sans jamais exister ni servir : PHP 8
        // émettait un avertissement de variable indéfinie à chaque appel, pour
        // une valeur que le corps de la closure n'utilise pas.
        $saved = $incoming->map(function ($v, $i) use ($product) {
            $payload = [
                'product_id' => $product->id,
                'label'      => $v['label'],
                'attributes' => $v['attributes'] ?? [],
                'sku'        => $v['sku'] ?? null,
                'price'      => $v['price'] ?? null,
                'is_active'  => $v['is_active'] ?? true,
                'position'   => $v['position'] ?? $i,
            ];

            if (!empty($v['id'])) {
                $variant = ProductVariant::find($v['id']);
                if ($variant) {
                    $variant->update($payload);
                } else {
                    $variant = ProductVariant::create($payload);
                }
            } else {
                $variant = ProductVariant::create($payload);
            }

            return array_merge($variant->fresh()->toArray(), [
                'stock' => (float) WarehouseHasStock::where('variant_id', $variant->id)->sum('stockLevel'),
            ]);
        });

        return response()->json($saved->values());
    }

    // DELETE /products/{id}/variants/{variantId}
    public function destroy(int $id, int $variantId): JsonResponse
    {
        ProductVariant::where('product_id', $id)->findOrFail($variantId)->delete();
        return response()->json(null, 204);
    }
}
