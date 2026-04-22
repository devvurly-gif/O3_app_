<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Services\PosService;
use App\Services\PriceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosProductController extends Controller
{
    public function __construct(
        private PosService $posService,
        private PriceResolver $priceResolver,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $session = PosSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->with('terminal')
            ->firstOrFail();

        $products = $this->posService->searchProducts(
            $request->input('search', ''),
            $session->terminal->warehouse_id,
            $request->input('category_id') ? (int) $request->input('category_id') : null,
            $request->input('limit', 50),
            $request->input('min_price') ? (float) $request->input('min_price') : null,
            $request->input('max_price') ? (float) $request->input('max_price') : null,
        );

        return response()->json($products);
    }

    /**
     * Re-price a list of cart items for a given (or no) customer using the
     * channel-aware PriceResolver. Returns one row per item with the
     * resolved HT price, tax rate, and the price list that matched.
     *
     * Body:
     *   customer_id: int|null
     *   items: [{ product_id: int, quantity: int }]
     */
    public function reprice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'         => ['nullable', 'integer', 'exists:third_partners,id'],
            'items'               => ['required', 'array'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['nullable', 'integer', 'min:1'],
        ]);

        $customer = $validated['customer_id']
            ? ThirdPartner::find($validated['customer_id'])
            : null;

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->all();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = collect($validated['items'])->map(function (array $item) use ($products, $customer) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                return null;
            }

            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $resolved = $this->priceResolver->resolve($product, $customer, $qty, 'pos');

            return [
                'product_id'    => $product->id,
                'quantity'      => $qty,
                'unit_price'    => $resolved['price_ht'],
                'price_ttc'     => $resolved['price_ttc'],
                'tax_percent'   => (float) $product->p_taxRate,
                'price_list_id' => $resolved['price_list_id'],
                'source'        => $resolved['source'],
            ];
        })->filter()->values();

        return response()->json([
            'customer_id'   => $customer?->id,
            'price_list_id' => $customer?->price_list_id,
            'items'         => $lines,
        ]);
    }
}
