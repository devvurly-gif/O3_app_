<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ThirdPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gère les fournisseurs pouvant livrer un produit (table pivot
 * product_suppliers, voir Product::suppliers()) — un produit s'achète
 * souvent chez plusieurs fournisseurs, à des prix et délais différents.
 * Utilisé par l'onglet « Fournisseurs » de la fiche produit, et lu par
 * achats:draft-daily-po (fournisseur de priorité la plus basse en premier).
 */
class ProductSupplierController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        return response()->json($product->suppliers);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request);

        if ($product->suppliers()->where('third_partner_id', $data['third_partner_id'])->exists()) {
            return response()->json([
                'message' => 'Ce fournisseur est déjà lié à ce produit — modifiez le lien existant au lieu d\'en créer un second.',
            ], 422);
        }

        $product->suppliers()->attach($data['third_partner_id'], collect($data)->except('third_partner_id')->all());

        return response()->json($product->suppliers()->where('third_partner_id', $data['third_partner_id'])->first(), 201);
    }

    public function update(Request $request, Product $product, ThirdPartner $supplier): JsonResponse
    {
        $data = $this->validated($request, $supplier->id);

        $product->suppliers()->updateExistingPivot($supplier->id, collect($data)->except('third_partner_id')->all());

        return response()->json($product->suppliers()->where('third_partner_id', $supplier->id)->first());
    }

    public function destroy(Product $product, ThirdPartner $supplier): JsonResponse
    {
        $product->suppliers()->detach($supplier->id);

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?int $ignoreThirdPartnerId = null): array
    {
        $rules = [
            'supplier_sku'   => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'priority'       => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
        ];
        if ($ignoreThirdPartnerId === null) {
            $rules['third_partner_id'] = ['required', 'integer', 'exists:third_partners,id'];
        }

        $data = $request->validate($rules);

        $thirdPartnerId = $ignoreThirdPartnerId ?? $data['third_partner_id'];
        $role = ThirdPartner::whereKey($thirdPartnerId)->value('tp_Role');
        if (!in_array($role, ['supplier', 'both'], true)) {
            abort(422, "Ce tiers n'est pas enregistré comme fournisseur dans O3 (tp_Role = {$role}).");
        }

        return $data;
    }
}
