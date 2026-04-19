<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\ThirdPartnerRepositoryInterface;
use App\Services\CacheService;
use App\Services\PaymentNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThirdPartnerController extends Controller
{
    public function __construct(private ThirdPartnerRepositoryInterface $partners)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->partners->paginate(
                perPage: (int) $request->input('per_page', 15),
                orderBy: $request->input('sort', 'tp_title'),
                direction: $request->input('order', 'asc'),
                filters: array_filter([
                    'search' => $request->search ? [
                        'columns' => ['tp_title', 'tp_phone', 'tp_email', 'tp_city'],
                        'value'   => $request->search,
                    ] : null,
                    'tp_Role'   => $request->role,
                    'tp_status' => $request->has('status') ? $request->boolean('status') : null
                      ])
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tp_title'          => ['required', 'string', 'max:255'],
            'tp_Role'           => ['required', 'in:customer,supplier,both'],
            'tp_Ice_Number'     => ['nullable', 'string', 'max:50'],
            'tp_Rc_Number'      => ['nullable', 'string', 'max:50'],
            'tp_patente_Number' => ['nullable', 'string', 'max:50'],
            'tp_IdenFiscal'     => ['nullable', 'string', 'max:50'],
            'tp_status'         => ['boolean'],
            'tp_phone'          => ['nullable', 'string', 'max:30'],
            'tp_email'          => ['nullable', 'email', 'max:255'],
            'tp_address'        => ['nullable', 'string'],
            'tp_city'           => ['nullable', 'string', 'max:100'],
            'seuil_credit'             => ['nullable', 'numeric', 'min:0'],
            'type_compte'              => ['nullable', 'in:normal,en_compte'],
            'frequence_facturation'    => ['nullable', 'required_if:type_compte,en_compte', 'in:mensuelle,trimestrielle,semestrielle'],
            'price_list_id'            => ['nullable', 'integer', 'exists:price_lists,id'],
        ]);

        // Force encours_actuel to 0 on creation — it's calculated from invoices/payments
        $data['encours_actuel'] = 0;

        $partner = $this->partners->create($data);
        CacheService::flushPartners();

        return response()->json($partner, 201);
    }

    public function show(ThirdPartner $thirdPartner): JsonResponse
    {
        return response()->json(
            $thirdPartner->load([
                'documentHeaders.footer',
                'documentHeaders.payments',
                'documentHeaders.lignes.product',
            ])
        );
    }

    public function update(Request $request, ThirdPartner $thirdPartner): JsonResponse
    {
        $data = $request->validate([
            'tp_title'          => ['sometimes', 'string', 'max:255'],
            'tp_Role'           => ['sometimes', 'in:customer,supplier,both'],
            'tp_Ice_Number'     => ['nullable', 'string', 'max:50'],
            'tp_Rc_Number'      => ['nullable', 'string', 'max:50'],
            'tp_patente_Number' => ['nullable', 'string', 'max:50'],
            'tp_IdenFiscal'     => ['nullable', 'string', 'max:50'],
            'tp_status'         => ['sometimes', 'boolean'],
            'tp_phone'          => ['nullable', 'string', 'max:30'],
            'tp_email'          => ['nullable', 'email', 'max:255'],
            'tp_address'        => ['nullable', 'string'],
            'tp_city'           => ['nullable', 'string', 'max:100'],
            'seuil_credit'             => ['nullable', 'numeric', 'min:0'],
            'type_compte'              => ['nullable', 'in:normal,en_compte'],
            'frequence_facturation'    => ['nullable', 'required_if:type_compte,en_compte', 'in:mensuelle,trimestrielle,semestrielle'],
            'price_list_id'            => ['nullable', 'integer', 'exists:price_lists,id'],
        ]);

        // Never allow manual encours_actuel update — it's calculated from invoices/payments
        unset($data['encours_actuel']);

        $this->partners->update($thirdPartner, $data);
        CacheService::flushPartners();

        return response()->json($thirdPartner);
    }

    public function destroy(ThirdPartner $thirdPartner): JsonResponse
    {
        $this->partners->delete($thirdPartner);
        CacheService::flushPartners();

        return response()->json(null, 204);
    }

    /**
     * Distribute a bulk payment across unpaid invoices (FIFO – oldest first).
     */
    public function bulkPayment(Request $request, ThirdPartner $thirdPartner): JsonResponse
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'method'    => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes'     => ['nullable', 'string'],
        ]);

        // Get all unpaid invoices for this partner, oldest first
        $unpaidDocs = $thirdPartner->documentHeaders()
            ->whereIn('document_type', ['InvoiceSale', 'InvoicePurchase'])
            ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
            ->with('footer')
            ->orderBy('issued_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($unpaidDocs->isEmpty()) {
            return response()->json(['message' => 'Aucune facture impayée trouvée.'], 422);
        }

        $remaining = (float) $data['amount'];
        $paymentsCreated = [];
        $affectedInvoices = [];

        // Skip individual notifications — we'll send one grouped notification after
        Payment::$skipNotification = true;

        try {
            DB::transaction(function () use ($unpaidDocs, &$remaining, $data, $request, &$paymentsCreated, &$affectedInvoices) {
                foreach ($unpaidDocs as $doc) {
                    if ($remaining <= 0) break;

                    $due = (float) $doc->footer->amount_due;
                    if ($due <= 0) continue;

                    $applied = min($remaining, $due);

                    $payment = Payment::create([
                        'document_header_id' => $doc->id,
                        'amount'             => round($applied, 2),
                        'method'             => $data['method'],
                        'paid_at'            => now(),
                        'reference'          => $data['reference'] ?? null,
                        'notes'              => $data['notes'] ?? null,
                        'user_id'            => $request->user()->id,
                    ]);

                    $paymentsCreated[] = $payment;

                    // Collect affected invoice info for the grouped notification
                    $freshFooter = $doc->footer->fresh();
                    $affectedInvoices[] = [
                        'reference'      => $doc->reference,
                        'amount_applied' => round($applied, 2),
                        'amount_due'     => (float) $freshFooter->amount_due,
                        'is_paid'        => $freshFooter->isPaid(),
                    ];

                    $remaining -= $applied;
                }
            });
        } finally {
            Payment::$skipNotification = false;
        }

        CacheService::flushDocuments();

        // Send ONE grouped notification (email + WhatsApp)
        if (!empty($paymentsCreated)) {
            try {
                $totalApplied = round((float) $data['amount'] - $remaining, 2);

                app(PaymentNotificationService::class)->send(
                    partner: $thirdPartner,
                    totalPaid: $totalApplied,
                    method: $data['method'],
                    reference: $data['reference'] ?? null,
                    affectedInvoices: $affectedInvoices,
                );
            } catch (\Throwable $e) {
                Log::warning("Bulk payment notification failed for partner {$thirdPartner->tp_title}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'message'          => count($paymentsCreated) . ' paiement(s) enregistré(s).',
            'payments_created' => count($paymentsCreated),
            'total_applied'    => round((float) $data['amount'] - $remaining, 2),
            'remaining'        => round($remaining, 2),
        ]);
    }
}
