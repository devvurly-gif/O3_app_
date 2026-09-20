<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Factures d'abonnement, vues du back-office O3App.
 *
 * Emission manuelle, telechargement, renvoi, annulation. L'emission
 * automatique passe par la commande `subscriptions:invoice` ; ce controleur
 * sert les cas ou il faut reprendre la main — un client qui demande sa facture
 * en avance, un email qui n'est pas arrive, une erreur de saisie a annuler.
 */
class TenantInvoiceController extends Controller
{
    public function __construct(private readonly SubscriptionInvoiceService $invoices)
    {
    }

    /**
     * GET /api/central/tenants/{tenant}/invoices
     */
    public function index(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data'           => TenantInvoice::forTenant((string) $tenant->id)->latestFirst()->get(),
            'issuer_missing' => $this->invoices->missingIssuerFields(),
        ]);
    }

    /**
     * POST /api/central/tenants/{tenant}/invoices
     *
     * Émission manuelle, hors du rythme du cron.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'billing_period' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'with_setup_fee' => ['nullable', 'boolean'],
            'issued_at'      => ['nullable', 'date'],
            'note'           => ['nullable', 'string', 'max:2000'],
            'send'           => ['nullable', 'boolean'],
        ]);

        $invoice = $this->invoices->issueFor($tenant, $validated);

        $sent = false;

        if ($validated['send'] ?? true) {
            try {
                $this->invoices->send($invoice);
                $sent = true;
            } catch (\Throwable $e) {
                // La facture existe et reste renvoyable : on le dit, on ne la
                // supprime pas.
                return response()->json([
                    'message' => "Facture {$invoice->number} émise, mais l'envoi a échoué : {$e->getMessage()}",
                    'invoice' => $invoice->refresh(),
                ], Response::HTTP_CREATED);
            }
        }

        return response()->json([
            'message' => "Facture {$invoice->number} émise" . ($sent ? ' et envoyée.' : '.'),
            'invoice' => $invoice->refresh(),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/central/invoices/{invoice}/pdf
     */
    public function pdf(TenantInvoice $invoice): StreamedResponse
    {
        $contents = $this->invoices->pdfContents($invoice);
        $filename = $this->invoices->filename($invoice);

        return response()->streamDownload(
            fn () => print($contents),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * POST /api/central/invoices/{invoice}/send
     */
    public function send(TenantInvoice $invoice): JsonResponse
    {
        $this->invoices->send($invoice);

        return response()->json([
            'message' => "Facture {$invoice->number} envoyée à {$invoice->tenant->email}.",
            'invoice' => $invoice->refresh(),
        ]);
    }

    /**
     * POST /api/central/invoices/{invoice}/cancel
     */
    public function cancel(Request $request, TenantInvoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->invoices->cancel($invoice, $validated['reason']);

        return response()->json([
            'message' => "Facture {$invoice->number} annulée. Son numéro reste consommé : la séquence doit rester continue.",
            'invoice' => $invoice->refresh(),
        ]);
    }
}
