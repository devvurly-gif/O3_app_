<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestPlanChangeRequest;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Services\SubscriptionInvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Abonnement vu par le client, depuis son propre espace.
 *
 * Ces routes échappent volontairement au middleware `tenant.active` (voir sa
 * liste ALWAYS_ALLOWED) : un compte échu doit pouvoir consulter son état et
 * demander une formule, sinon il n'a aucun moyen de régulariser.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SubscriptionInvoiceService $invoices,
    ) {
    }

    /**
     * État de l'abonnement + catalogue, pour l'écran « choisir une formule ».
     */
    public function show(): JsonResponse
    {
        return response()->json(
            $this->subscriptions->summary($this->tenant())
        );
    }

    /**
     * Le client choisit une formule. Ne déclenche aucun encaissement : cela
     * enregistre sa demande et prévient O3App, qui envoie le contrat et la
     * facture. Le paiement en ligne viendra quand le volume le justifiera.
     */
    public function requestPlan(RequestPlanChangeRequest $request): JsonResponse
    {
        $this->subscriptions->requestPlan(
            tenant: $this->tenant(),
            plan: $request->validated('plan'),
            billingPeriod: $request->validated('billing_period'),
            note: $request->validated('note'),
            billing: [
                'billing_ice'     => $request->validated('billing_ice'),
                'billing_address' => $request->validated('billing_address'),
            ],
        );

        return response()->json([
            'message' => "Votre demande est enregistrée. Nous vous envoyons le contrat et la facture sous 24 h ouvrées.",
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Factures du tenant connecté.
     *
     * Un client doit pouvoir récupérer ses propres factures sans écrire à son
     * prestataire — c'est aussi ce qui évite la moitié des demandes de renvoi.
     */
    public function invoices(): JsonResponse
    {
        return response()->json([
            'data' => TenantInvoice::forTenant((string) $this->tenant()->id)
                ->live()
                ->latestFirst()
                ->get([
                    'id', 'number', 'issued_at', 'due_at', 'period_starts_at',
                    'period_ends_at', 'amount_ttc_cents', 'status', 'paid_at',
                ]),
        ]);
    }

    /**
     * Téléchargement d'une facture par son propriétaire.
     *
     * Le contrôle d'appartenance est fait ici, à la main, et pas seulement par
     * la route : les identifiants sont séquentiels et centraux, donc sans ce
     * contrôle un tenant pourrait lire la facture d'un autre en changeant un
     * chiffre dans l'URL.
     */
    public function invoicePdf(TenantInvoice $invoice): StreamedResponse
    {
        abort_unless($invoice->tenant_id === $this->tenant()->id, Response::HTTP_NOT_FOUND);

        $contents = $this->invoices->pdfContents($invoice);

        return response()->streamDownload(
            fn () => print($contents),
            $this->invoices->filename($invoice),
            ['Content-Type' => 'application/pdf']
        );
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();

        abort_unless($tenant instanceof Tenant, Response::HTTP_NOT_FOUND);

        return $tenant;
    }
}
