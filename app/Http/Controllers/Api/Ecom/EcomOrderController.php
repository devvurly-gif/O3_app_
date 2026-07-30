<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Mail\EcomOrderConfirmationMail;
use App\Models\DocumentIncrementor;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewEcomOrderNotification;
use App\Services\DocumentHeaderService;
use App\Services\DynamicMailService;
use App\Services\PriceResolver;
use App\Services\StockMouvementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EcomOrderController extends Controller
{
    public function __construct(
        private DocumentHeaderService $documentService,
        private PriceResolver $priceResolver,
        private StockMouvementService $stockService,
    ) {
    }

    /**
     * GET /api/ecom/customers/lookup?email=...
     * Used by the checkout page's email-first step: tells the storefront
     * whether this email already belongs to a customer of this tenant so
     * it can either prefill the delivery form or reveal the blank
     * "nouveau client" form. Rate-limited by the ecom route group
     * (throttle:60,1) same as the rest of the public ecom API.
     */
    public function lookupCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
        ]);

        $customer = ThirdPartner::where('tp_Role', 'customer')
            ->where(function ($q) use ($validated) {
                if (!empty($validated['email'])) {
                    $q->orWhere('tp_email', $validated['email']);
                }
                if (!empty($validated['phone'])) {
                    $q->orWhere('tp_phone', $validated['phone']);
                }
            })
            ->first();

        if (!$customer) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists'   => true,
            'customer' => [
                'name'    => $customer->tp_title,
                'phone'   => $customer->tp_phone,
                'email'   => $customer->tp_email,
                'address' => $customer->tp_address,
                'city'    => $customer->tp_city,
            ],
        ]);
    }

    /**
     * POST /api/ecom/orders
     * Create a customer + Bon de Livraison (unconfirmed) from the ecommerce checkout.
     * Staff confirm it from the Documents Vente screen once picked/packed, at which
     * point stock is actually deducted (see DocumentVenteController::confirmer_bl).
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

        // Find the DeliveryNote (BL) incrementor
        $incrementor = DocumentIncrementor::where('di_model', 'DeliveryNote')->first();

        if (!$incrementor) {
            return response()->json(['message' => 'DeliveryNote incrementor not configured'], 500);
        }

        // Ecom orders draw stock from the tenant's (single) warehouse
        $warehouse = Warehouse::query()->orderBy('id')->first();

        if (!$warehouse) {
            return response()->json(['message' => 'No warehouse configured'], 500);
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

        // Create the BL (Bon de Livraison), unconfirmed — stock is only
        // reserved (pending) here; it's deducted for real when staff confirm it.
        $document = $this->documentService->createWithLinesAndFooter(
            [
                'document_incrementor_id' => $incrementor->id,
                'document_type'           => 'DeliveryNote',
                'document_title'          => 'Commande eCom - ' . $customerData['name'],
                'thirdPartner_id'         => $customer->id,
                'warehouse_id'            => $warehouse->id,
                'user_id'                 => config('services.ecom.default_user_id', 1),
                'issued_at'               => now(),
                'notes'                   => $validated['notes'] ?? null,
                // Snapshot what the customer typed at checkout — the
                // customer profile itself is never overwritten here, so a
                // returning customer shipping to a different address still
                // has that address recorded against this specific order.
                'ship_name'               => $customerData['name'],
                'ship_phone'              => $customerData['phone'],
                'ship_address'            => $customerData['address'],
                'ship_city'               => $customerData['city'],
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

        $document->load('lignes');
        $this->stockService->processDocument($document, pending: true);

        $this->sendOrderEmails($document, $customer);

        return response()->json([
            'success'     => true,
            'reference'   => $document->reference,
            'document_id' => $document->id,
        ], 201);
    }

    /**
     * Send both order emails (customer confirmation + staff new-order alert)
     * gated by the tenant's "Send Email" toggle in Réglages > Email. Both are
     * queued (ShouldQueue), so a slow/unreachable SMTP server can never delay
     * the checkout response — failures are logged, not surfaced to the buyer.
     */
    private function sendOrderEmails($document, ThirdPartner $customer): void
    {
        if (!DynamicMailService::isEnabled()) {
            return;
        }

        try {
            Mail::to($customer->tp_email)->send(new EcomOrderConfirmationMail($document));
        } catch (\Throwable $e) {
            Log::warning("EcomOrder: customer confirmation email failed for {$document->reference}: {$e->getMessage()}");
        }

        try {
            $recipients = User::whereHas('role', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
                ->where('is_active', true)
                ->get();

            foreach ($recipients as $user) {
                $user->notify(new NewEcomOrderNotification($document));
            }
        } catch (\Throwable $e) {
            Log::warning("EcomOrder: staff new-order notification failed for {$document->reference}: {$e->getMessage()}");
        }
    }
}
