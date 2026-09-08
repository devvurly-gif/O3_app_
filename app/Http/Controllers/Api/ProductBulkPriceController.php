<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BulkSalePriceUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Revision en masse du prix de vente.
 *
 * `preview` chiffre, `apply` ecrit — et `apply` refait le chiffrage avant
 * d'ecrire : si le lot n'a plus la taille annoncee a l'ecran (un produit cree
 * entre-temps, un filtre qui ne rend plus la meme chose), l'operation est
 * refusee plutot que d'appliquer une regle a un perimetre que personne n'a vu.
 */
class ProductBulkPriceController extends Controller
{
    public function __construct(private readonly BulkSalePriceUpdater $updater)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        [$filters, $rule] = $this->parse($request);

        return response()->json(
            $this->updater->preview($filters, $rule, Product::costsVisibleTo($request->user()))
        );
    }

    public function apply(Request $request): JsonResponse
    {
        [$filters, $rule] = $this->parse($request);

        $expected = $request->validate([
            'expected_count' => ['required', 'integer', 'min:0'],
        ])['expected_count'];

        $preview = $this->updater->preview($filters, $rule, Product::costsVisibleTo($request->user()));

        if ($preview['matched'] > BulkSalePriceUpdater::MAX_PRODUCTS) {
            return response()->json([
                'message' => "Lot trop grand : {$preview['matched']} produits pour un maximum de "
                    . BulkSalePriceUpdater::MAX_PRODUCTS . '. Affinez les filtres.',
            ], 422);
        }

        if ($preview['matched'] !== $expected) {
            return response()->json([
                'message' => "Le perimetre a change depuis le chiffrage : {$preview['matched']} produits "
                    . "au lieu de {$expected}. Relancez le chiffrage.",
                'preview' => $preview,
            ], 422);
        }

        if ($preview['negative'] > 0) {
            return response()->json([
                'message' => "La regle donnerait un prix negatif sur {$preview['negative']} produit(s). "
                    . 'Rien n\'a ete modifie.',
                'preview' => $preview,
            ], 422);
        }

        $updated = $this->updater->apply($filters, $rule);

        return response()->json([
            'message' => "{$updated} prix de vente mis a jour.",
            'updated' => $updated,
            'matched' => $preview['matched'],
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function parse(Request $request): array
    {
        $validated = $request->validate([
            'product_ids'    => ['nullable', 'array'],
            'product_ids.*'  => ['integer'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'brand_ids'      => ['nullable', 'array'],
            'brand_ids.*'    => ['integer'],
            'status'         => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'search'         => ['nullable', 'string', 'max:120'],

            'mode'           => ['required', Rule::in(BulkSalePriceUpdater::MODES)],
            'value'          => ['required', 'numeric'],
            // La marge se calcule sur le prix d'achat par defaut ; `cost` vise
            // le cout de revient quand le tenant le renseigne.
            'basis'          => ['nullable', Rule::in(['purchase', 'cost'])],
            'rounding'       => ['nullable', Rule::in(BulkSalePriceUpdater::ROUNDINGS)],
        ]);

        $filters = [
            'product_ids'  => $validated['product_ids']  ?? [],
            'category_ids' => $validated['category_ids'] ?? [],
            'brand_ids'    => $validated['brand_ids']    ?? [],
            'status'       => $validated['status']       ?? 'all',
            'search'       => $validated['search']       ?? null,
        ];

        $rule = [
            'mode'     => $validated['mode'],
            'value'    => $validated['value'],
            'basis'    => $validated['basis']    ?? 'purchase',
            'rounding' => $validated['rounding'] ?? 'none',
        ];

        return [$filters, $rule];
    }
}
