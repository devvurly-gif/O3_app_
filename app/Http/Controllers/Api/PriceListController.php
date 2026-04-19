<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Services\PriceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceListController extends Controller
{
    public function __construct(private PriceResolver $priceResolver) {}

    /**
     * GET /api/price-lists
     */
    public function index(): JsonResponse
    {
        $lists = PriceList::withCount('items', 'customers')
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $lists]);
    }

    /**
     * GET /api/price-lists/{id}
     * Returns the price list with all items (product joined).
     */
    public function show(int $id): JsonResponse
    {
        $list = PriceList::with(['items.product:id,p_title,p_code,p_sku,p_taxRate'])
            ->findOrFail($id);

        return response()->json(['data' => $list]);
    }

    /**
     * POST /api/price-lists
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'channel'     => 'required|in:all,pos,ecom',
            'is_default'  => 'boolean',
            'is_active'   => 'boolean',
            'priority'    => 'integer|min:0',
        ]);

        $list = DB::transaction(function () use ($data) {
            // Only one default per channel
            if (!empty($data['is_default'])) {
                PriceList::where('channel', $data['channel'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
            return PriceList::create($data);
        });

        return response()->json(['data' => $list], 201);
    }

    /**
     * PUT /api/price-lists/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $list = PriceList::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'channel'     => 'sometimes|in:all,pos,ecom',
            'is_default'  => 'boolean',
            'is_active'   => 'boolean',
            'priority'    => 'integer|min:0',
        ]);

        DB::transaction(function () use ($list, $data) {
            if (!empty($data['is_default'])) {
                PriceList::where('channel', $data['channel'] ?? $list->channel)
                    ->where('is_default', true)
                    ->where('id', '!=', $list->id)
                    ->update(['is_default' => false]);
            }
            $list->update($data);
        });

        return response()->json(['data' => $list->fresh()]);
    }

    /**
     * DELETE /api/price-lists/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $list = PriceList::findOrFail($id);

        if ($list->is_default) {
            return response()->json(['message' => 'Impossible de supprimer la grille par défaut.'], 422);
        }

        $list->delete();
        return response()->json(['message' => 'Grille tarifaire supprimée.']);
    }

    // ── Items management ──────────────────────────────────

    /**
     * POST /api/price-lists/{id}/items
     * Bulk add/update items (array of {product_id, price_ht, min_qty?, valid_from?, valid_to?}).
     */
    public function upsertItems(Request $request, int $id): JsonResponse
    {
        $list = PriceList::findOrFail($id);

        $data = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|integer|exists:products,id',
            'items.*.price_ht'      => 'required|numeric|min:0',
            'items.*.min_qty'       => 'integer|min:1',
            'items.*.valid_from'    => 'nullable|date',
            'items.*.valid_to'      => 'nullable|date|after_or_equal:items.*.valid_from',
        ]);

        $results = DB::transaction(function () use ($list, $data) {
            $items = [];
            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $taxRate = (float) $product->p_taxRate;
                $priceTtc = $row['price_ht'] * (1 + $taxRate / 100);

                $item = PriceListItem::updateOrCreate(
                    [
                        'price_list_id' => $list->id,
                        'product_id'    => $row['product_id'],
                        'min_qty'       => $row['min_qty'] ?? 1,
                    ],
                    [
                        'price_ht'   => $row['price_ht'],
                        'price_ttc'  => round($priceTtc, 2),
                        'valid_from' => $row['valid_from'] ?? null,
                        'valid_to'   => $row['valid_to'] ?? null,
                    ]
                );
                $items[] = $item;
            }
            return $items;
        });

        return response()->json(['data' => $results]);
    }

    /**
     * DELETE /api/price-lists/{id}/items/{itemId}
     */
    public function destroyItem(int $id, int $itemId): JsonResponse
    {
        $item = PriceListItem::where('price_list_id', $id)->findOrFail($itemId);
        $item->delete();
        return response()->json(['message' => 'Ligne supprimée.']);
    }

    // ── Price resolution endpoint (for POS/ecom frontends) ──

    /**
     * GET /api/price-lists/resolve?product_id=X&third_partner_id=Y&quantity=Z&channel=pos
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'       => 'required|integer|exists:products,id',
            'third_partner_id' => 'nullable|integer|exists:third_partners,id',
            'quantity'         => 'integer|min:1',
            'channel'          => 'nullable|in:all,pos,ecom',
        ]);

        $product  = Product::findOrFail($data['product_id']);
        $customer = isset($data['third_partner_id']) ? ThirdPartner::find($data['third_partner_id']) : null;
        $qty      = $data['quantity'] ?? 1;
        $channel  = $data['channel'] ?? 'all';

        $resolved = $this->priceResolver->resolve($product, $customer, $qty, $channel);

        return response()->json($resolved);
    }
}
