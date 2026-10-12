<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Repositories\Contracts\ThirdPartnerRepositoryInterface;
use App\Services\CacheService;
use App\Services\Messaging\OrderPin;
use App\Services\PaymentNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ThirdPartnerController extends Controller
{
    public function __construct(
        private ThirdPartnerRepositoryInterface $partners,
        private OrderPin $pins,
    ) {
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

        // PIN des commandes par WhatsApp/SMS : tiré à la création, montré une
        // seule fois dans cette réponse, à remettre au client par l'équipe.
        $body = $partner->toArray();
        if ($partner->isCustomer()) {
            $body['order_pin'] = $this->pins->generate($partner);
            $body['order_pin_state'] = 'active';
        }

        return response()->json($body, 201);
    }

    /**
     * POST /third-partners/{id}/order-pin — nouveau PIN de commande par message
     * (PIN perdu, divulgué, ou canal bloqué après trop d'erreurs). L'ancien PIN
     * cesse aussitôt de fonctionner ; le nouveau n'est montré qu'ici.
     */
    public function regenerateOrderPin(ThirdPartner $thirdPartner): JsonResponse
    {
        if (!$thirdPartner->isCustomer()) {
            return response()->json(['message' => 'Seul un client peut avoir un PIN de commande.'], 422);
        }

        $pin = $this->pins->generate($thirdPartner);
        activity()->performedOn($thirdPartner)->causedBy(auth()->user())->log('PIN de commande régénéré');

        return response()->json([
            'order_pin'       => $pin,
            'order_pin_state' => 'active',
        ]);
    }

    public function show(ThirdPartner $thirdPartner): JsonResponse
    {
        return response()->json(
            $thirdPartner->load([
                'documentHeaders.footer',
                'documentHeaders.payments',
                'documentHeaders.lignes.product',
                'documentHeaders.children:id,reference,document_type,parent_id',
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
     * DELETE /api/third-partners/{id}/definitif (admin)
     * Suppression définitive d'un client ou d'un fournisseur, y compris déjà supprimé (corbeille). Refusée tant
     * que la fiche porte un historique — documents (même supprimés), écritures et échéances de trésorerie,
     * messages de commande, dossiers d'agents, relances de paiement : les perdre fausserait les soldes et les
     * rapports. Dans ce cas, la suppression simple (corbeille) reste la bonne option. Les codes d'accès au chat
     * client et les tarifs fournisseur de la fiche partent avec elle.
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $partner = ThirdPartner::withTrashed()->findOrFail($id);

        $usage = collect([
            'document(s)'                  => ['document_headers', 'thirdPartner_id'],
            'écriture(s) de trésorerie'    => ['cash_transactions', 'thirdPartner_id'],
            'échéance(s) récurrente(s)'    => ['cash_recurrences', 'thirdPartner_id'],
            'message(s) de commande'       => ['order_messages', 'third_partner_id'],
            'dossier(s) d\'agent'          => ['agent_cases', 'third_partner_id'],
            'relance(s) de paiement'       => ['payment_reminders', 'third_partner_id'],
        ])->map(fn (array $ref) => Schema::hasTable($ref[0]) ? DB::table($ref[0])->where($ref[1], $id)->count() : 0)
            ->filter();

        if ($usage->isNotEmpty()) {
            return response()->json(['message' => 'Suppression définitive impossible : cette fiche porte encore '
                . $usage->map(fn ($n, $label) => "{$n} {$label}")->implode(', ')
                . '. Gardez-la en suppression simple, ou supprimez d\'abord ces éléments.'], 422);
        }

        $snapshot = ['code' => $partner->tp_code, 'title' => $partner->tp_title, 'role' => $partner->tp_Role, 'ice' => $partner->tp_Ice_Number];
        try {
            DB::transaction(function () use ($partner, $id) {
                foreach (['customer_chat_codes', 'customer_chat_sessions'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('third_partner_id', $id)->delete();
                    }
                }
                $partner->forceDelete();                     // tarifs fournisseur en cascade
            });
        } catch (QueryException) {
            return response()->json(['message' => 'Suppression définitive impossible : cette fiche est encore référencée ailleurs dans l\'application.'], 422);
        }

        activity()->causedBy(auth()->user())->withProperties($snapshot)->log("Tiers {$snapshot['code']} supprimé définitivement");
        CacheService::flushPartners();

        return response()->json(null, 204);
    }

    /**
     * Distribute a bulk payment across unpaid invoices (FIFO – oldest first).
     */
    public function bulkPayment(Request $request, ThirdPartner $thirdPartner): JsonResponse
    {
        $data = $request->validate([
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'method'        => ['required', 'string', 'max:50'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string'],
            // Optional: restrict the allocation to specific document IDs
            // (the UI lets the user tick which BLs/Factures this payment
            // covers). When omitted, all unpaid payable docs are used.
            'document_ids'  => ['nullable', 'array'],
            'document_ids.*' => ['integer'],
        ]);

        // Get all unpaid invoices for this partner, oldest first
        $payableTypes = ['InvoiceSale', 'InvoicePurchase'];
        if (Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true') {
            $payableTypes[] = 'DeliveryNote';
        }

        $query = $thirdPartner->documentHeaders()
            ->whereIn('document_type', $payableTypes)
            ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0))
            ->with('footer')
            ->orderBy('issued_at', 'asc')
            ->orderBy('id', 'asc');

        if (!empty($data['document_ids'])) {
            $query->whereIn('id', $data['document_ids']);
        }

        $unpaidDocs = $query->get();

        if ($unpaidDocs->isEmpty()) {
            return response()->json(['message' => 'Aucune facture impayée trouvée.'], 422);
        }

        // Le reglement groupe passe a cote de StorePaymentRequest : sans ce
        // controle, il rouvrirait la porte que l'on vient de fermer. On refuse
        // le lot entier plutot que d'ecarter une facture en silence — le
        // repartir sans le dire donnerait un resultat que personne n'a demande.
        $doubleCounted = $unpaidDocs->first(fn ($doc) => $doc->hasManualTreasuryEntry());

        if ($doubleCounted) {
            return response()->json([
                'message' => 'Le document ' . $doubleCounted->reference . ' est deja porte par une ecriture '
                           . 'de tresorerie saisie a la main : un reglement par-dessus compterait la somme '
                           . 'deux fois. Annulez cette ecriture depuis la Tresorerie, puis relancez le reglement.',
            ], 422);
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
