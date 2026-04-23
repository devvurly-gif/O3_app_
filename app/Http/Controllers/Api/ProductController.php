<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\CacheService;
use App\Services\PriceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private PriceResolver $priceResolver,
    ) {
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
            $product->load([
                'category', 'brand', 'images', 'primaryImage',
                'warehouseStocks.warehouse', 'priceListItems.priceList'
            ])
        );
    }

    public function statistics(Product $product): JsonResponse
    {
        $sales = $product->documentLines()
            ->whereHas('document', function ($q) {
                $q->whereIn('document_type', ['InvoiceSale', 'TicketSale']);
            })
            ->with('document:id,issued_at')
            ->get();

        $purchases = $product->documentLines()
            ->whereHas('document', function ($q) {
                $q->whereIn('document_type', ['InvoicePurchase', 'ReceiptNotePurchase']);
            })
            ->with('document:id,issued_at')
            ->get();

        $totalUnitsSold = $sales->sum('quantity');
        $totalRevenue = $sales->sum('total_ttc');
        $totalUnitsPurchased = $purchases->sum('quantity');
        $totalCost = $purchases->sum('total_ht');

        return response()->json([
            'sales' => [
                'total_units' => $totalUnitsSold,
                'total_revenue' => round($totalRevenue, 2),
                'avg_price' => $totalUnitsSold > 0 ? round($totalRevenue / $totalUnitsSold, 2) : 0,
                'count' => $sales->groupBy('document_header_id')->count(),
                'last_sale_date' => $sales->max(fn ($item) => $item->document?->issued_at),
            ],
            'purchases' => [
                'total_units' => $totalUnitsPurchased,
                'total_cost' => round($totalCost, 2),
                'avg_price' => $totalUnitsPurchased > 0 ? round($totalCost / $totalUnitsPurchased, 2) : 0,
                'count' => $purchases->groupBy('document_header_id')->count(),
                'last_purchase_date' => $purchases->max(fn ($item) => $item->document?->issued_at),
            ],
        ]);
    }

    public function stockHistory(Request $request, Product $product): JsonResponse
    {
        $movements = $product->stockMouvements()
            ->with('warehouse', 'documentHeader', 'user')
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($movements);
    }

    /**
     * Resolve unit prices for a set of (product, quantity) pairs using the
     * channel-aware PriceResolver. Used by the sales-document form so the
     * displayed price reflects either:
     *   - the selected customer's assigned price list, or
     *   - the default "all"-channel price list (comptoir / walk-in), or
     *   - the product base p_salePrice.
     *
     * Body:
     *   customer_id: int|null
     *   channel:     string  ('all' | 'pos' | 'ecom', defaults to 'all')
     *   items:       [{ product_id: int, quantity?: int }]
     */
    public function reprice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id'        => ['nullable', 'integer', 'exists:third_partners,id'],
            'channel'            => ['nullable', 'string', 'in:all,pos,ecom'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['nullable', 'integer', 'min:1'],
        ]);

        $channel  = $validated['channel'] ?? 'all';
        $customer = $validated['customer_id']
            ? ThirdPartner::find($validated['customer_id'])
            : null;

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->all();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = collect($validated['items'])->map(function (array $item) use ($products, $customer, $channel) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                return null;
            }

            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $resolved = $this->priceResolver->resolve($product, $customer, $qty, $channel);

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
            'channel'       => $channel,
            'items'         => $lines,
        ]);
    }

    public function priceLists(Product $product): JsonResponse
    {
        $priceListItems = $product->priceListItems()
            ->with('priceList')
            ->orderBy('min_qty', 'asc')
            ->get();

        return response()->json($priceListItems);
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
