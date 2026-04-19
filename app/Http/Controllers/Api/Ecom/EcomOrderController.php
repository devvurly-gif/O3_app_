<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Services\DocumentHeaderService;
use App\Services\PriceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcomOrderController extends Controller
{
    public function __construct(
        private DocumentHeaderService $documentService,
        private PriceResolver $priceResolver,
    ) {
    }

    /**
     * POST /api/ecom/orders
     * Create a customer + devis (QuoteSale) from the ecommerce checkout.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer.name'    => 'required|string|max:255',
            'customer.phone'   => 'required|string|max:50',
            'customer.email'   => 'required|email|max:255',
            'customer.address' => 'required|string|max:500',
            'customer.city'    => 'required|string|max:100',
            'notes'            => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer|exists:products,id',
            'items.*.designation' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:1',
            'items.*.unit_price'  => 'nullable|numeric|min:0', // ignored — resolved server-side
            'items.*.tax_percent' => 'nullable|numeric|min:0',
        ]);

        $customerData = $validated['customer'];

        // Find existing customer by email or create new one
        $customer = ThirdPartner::where('tp_email', $customerData['email'])
            ->where('tp_Role', 'customer')
            ->first();

        if (!$customer) {
            $customer = ThirdPartner::create([
                'tp_title'     => $customerData['name'],
                'tp_phone'     => $customerData['phone'],
                'tp_email'     => $customerData['email'],
                'tp_address'   => $customerData['address'],
                'tp_city'      => $customerData['city'],
                'tp_Role'      => 'customer',
                'tp_status'    => true,
                'type_compte'  => 'normal',
            ]);
        }

        // Find the QuoteSale incrementor
        $incrementor = DocumentIncrementor::where('di_model', 'QuoteSale')->first();

        if (!$incrementor) {
            return response()->json(['message' => 'QuoteSale incrementor not configured'], 500);
        }

        // Build lines — resolve prices server-side to prevent price tampering
        $lines = collect($validated['items'])->map(function ($item) use ($customer) {
            $product = Product::find($item['product_id']);
            $resolved = $this->priceResolver->resolve(
                product:  $product,
                customer: $customer,
                quantity: (int) $item['quantity'],
                channel:  'ecom',
            );
            return [
                'product_id'       => $item['product_id'],
                'designation'      => $item['designation'],
                'quantity'         => $item['quantity'],
                'unit_price'       => $resolved['price_ht'],
                'tax_percent'      => $item['tax_percent'] ?? (float) ($product->p_taxRate ?? 0),
                'discount_percent' => 0,
            ];
        })->toArray();

        // Calculate totals
        $totalHt = collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_price']);
        $totalTax = collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_price'] * ($l['tax_percent'] / 100));
        $totalTtc = $totalHt + $totalTax;

        // Create the devis
        $document = $this->documentService->createWithLinesAndFooter(
            [
                'document_incrementor_id' => $incrementor->id,
                'document_type'           => 'QuoteSale',
                'document_title'          => 'Commande eCom - ' . $customerData['name'],
                'thirdPartner_id'         => $customer->id,
                'user_id'                 => config('services.ecom.default_user_id', 1),
                'issued_at'               => now(),
                'notes'                   => $validated['notes'] ?? null,
            ],
            $lines,
            [
                'total_ht'       => $totalHt,
                'total_tax'      => $totalTax,
                'total_ttc'      => $totalTtc,
                'total_discount' => 0,
                'amount_paid'    => 0,
                'amount_due'     => $totalTtc,
            ]
        );

        return response()->json([
            'success'   => true,
            'reference' => $document->reference,
            'devis_id'  => $document->id,
        ], 201);
    }
}
