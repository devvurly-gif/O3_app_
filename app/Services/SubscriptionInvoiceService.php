<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\BillingNotConfiguredException;
use App\Mail\SubscriptionInvoiceMail;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Models\TenantPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Emission, envoi et reglement des factures d'abonnement d'O3App.
 *
 * Transposition centrale de ce que le produit sait deja faire pour les clients
 * de ses tenants (DocumentPdfService, GeneratePeriodicInvoices) : meme idee,
 * mais l'emetteur est O3App, le client est le tenant, et les compteurs vivent
 * dans la base centrale.
 *
 * Deux regles gouvernent tout le reste :
 *
 *  1. Une facture est figee. Le PDF est ecrit une fois sur le disque et relu
 *     tel quel ; l'identite des deux parties et le tarif sont recopies dans
 *     `snapshot`. Une facture regeneree a la volee changerait si une adresse ou
 *     un prix bougeait, ce qui est exactement ce qu'une facture ne doit pas
 *     faire.
 *  2. Une facture ne se supprime pas. Une erreur s'annule, et la sequence des
 *     numeros reste continue.
 */
class SubscriptionInvoiceService
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceNumberService $numbers,
    ) {
    }

    /**
     * Emet la facture de la periode qui suit celle en cours.
     *
     * @param array{billing_period?: string|null, issued_at?: string|null, with_setup_fee?: bool|null, note?: string|null} $options
     *
     * @throws BillingNotConfiguredException Si l'emetteur est incomplet.
     * @throws RuntimeException             Si la periode est deja facturee.
     */
    public function issueFor(Tenant $tenant, array $options = []): TenantInvoice
    {
        $this->assertIssuerConfigured();

        $plan   = $this->plans->normalize($tenant->plan);
        $period = $this->subscriptions->normalizePeriod(
            $options['billing_period'] ?? $this->lastBillingPeriodOf($tenant)
        );

        [$startsAt, $endsAt] = $this->subscriptions->nextPeriodFor($tenant, $period);

        if ($existing = $this->liveInvoiceCovering($tenant, $startsAt)) {
            throw new RuntimeException(
                "La période du {$startsAt->format('d/m/Y')} est déjà couverte par la facture {$existing->number}."
            );
        }

        $issuedAt = CarbonImmutable::parse($options['issued_at'] ?? 'now')->startOfDay();
        $dueAt    = $issuedAt->addDays((int) config('billing.payment_terms_days', 15));

        $subtotal = $this->subscriptions->catalogPrice($plan, $period);
        $setupFee = ($options['with_setup_fee'] ?? $this->isFirstInvoice($tenant))
            ? (int) ($this->plans->get($plan)['setup_fee_cents'] ?? 0)
            : 0;

        // Les frais de mise en service sont offerts sur l'engagement annuel :
        // c'est le levier qui pousse a l'annuel sans toucher au prix affiche
        // (voir docs/commercial/plan-commercialisation.md §3).
        if ($period === SubscriptionService::PERIOD_YEARLY) {
            $setupFee = 0;
        }

        $amountHt = $subtotal + $setupFee;
        $vatRate  = (float) config('billing.vat_rate', 20);
        $vat      = (int) round($amountHt * $vatRate / 100);

        $invoice = DB::transaction(function () use (
            $tenant, $plan, $period, $issuedAt, $dueAt, $startsAt, $endsAt,
            $subtotal, $setupFee, $amountHt, $vatRate, $vat, $options
        ) {
            return TenantInvoice::create([
                'number'            => $this->numbers->next($issuedAt),
                'tenant_id'         => $tenant->id,
                'plan'              => $plan,
                'billing_period'    => $period,
                'issued_at'         => $issuedAt->toDateString(),
                'due_at'            => $dueAt->toDateString(),
                'period_starts_at'  => $startsAt->toDateString(),
                'period_ends_at'    => $endsAt->toDateString(),
                'subtotal_cents'    => $subtotal,
                'setup_fee_cents'   => $setupFee,
                'amount_ht_cents'   => $amountHt,
                'vat_rate'          => $vatRate,
                'vat_cents'         => $vat,
                'amount_ttc_cents'  => $amountHt + $vat,
                'status'            => InvoiceStatus::Draft,
                'snapshot'          => $this->snapshot($tenant, $plan),
                'note'              => $options['note'] ?? null,
            ]);
        });

        $this->storePdf($invoice);

        return $invoice->refresh();
    }

    /**
     * Envoie la facture au tenant, PDF en piece jointe.
     *
     * L'echec d'envoi n'annule pas la facture : elle existe, elle est
     * numerotee, et elle reste renvoyable depuis le back-office.
     */
    public function send(TenantInvoice $invoice): bool
    {
        $pdf = $this->pdfContents($invoice);

        Mail::to($invoice->tenant->email)->send(
            new SubscriptionInvoiceMail($invoice, $pdf)
        );

        if ($invoice->currentStatus() === InvoiceStatus::Draft) {
            $invoice->status = InvoiceStatus::Sent;
        }

        $invoice->sent_at = now();
        $invoice->save();

        return true;
    }

    /**
     * Solde une facture par un reglement.
     */
    public function markPaid(TenantInvoice $invoice, ?TenantPayment $payment = null): TenantInvoice
    {
        $invoice->status            = InvoiceStatus::Paid;
        $invoice->paid_at           = $payment?->paid_at ?? now()->toDateString();
        $invoice->tenant_payment_id = $payment?->id ?? $invoice->tenant_payment_id;
        $invoice->save();

        return $invoice;
    }

    /**
     * Annule une facture. Le numero reste consomme : un trou dans la sequence
     * s'explique, deux factures du meme numero non.
     */
    public function cancel(TenantInvoice $invoice, string $reason): TenantInvoice
    {
        if ($invoice->currentStatus() === InvoiceStatus::Paid) {
            throw new RuntimeException(
                "La facture {$invoice->number} est réglée : elle s'annule par un avoir, pas par une suppression."
            );
        }

        $invoice->status = InvoiceStatus::Cancelled;
        $invoice->note   = trim((string) $invoice->note . "\nAnnulée : " . $reason);
        $invoice->save();

        return $invoice;
    }

    /**
     * Rattache un reglement a la facture ouverte la plus ancienne du tenant.
     *
     * Appele depuis SubscriptionService::recordPayment : quand l'encaissement
     * est saisi au back-office, la facture correspondante doit se solder toute
     * seule, sinon la liste des impayes ne veut plus rien dire.
     */
    public function settleOutstanding(Tenant $tenant, TenantPayment $payment): ?TenantInvoice
    {
        $invoice = TenantInvoice::forTenant((string) $tenant->id)
            ->outstanding()
            ->orderBy('issued_at')
            ->orderBy('id')
            ->first();

        return $invoice ? $this->markPaid($invoice, $payment) : null;
    }

    /**
     * Tenants dont l'echeance approche et qui n'ont pas encore leur facture.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    public function tenantsDueForInvoice(?CarbonImmutable $today = null): \Illuminate\Support\Collection
    {
        $today    = $today ?? CarbonImmutable::now();
        $leadDays = (int) config('billing.issue_lead_days', 15);
        $horizon  = $today->addDays($leadDays)->toDateString();

        return Tenant::query()
            ->where('is_active', true)
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<=', $horizon)
            ->get()
            ->filter(function (Tenant $tenant) {
                // Un compte jamais verifie n'a rien a payer : il n'a jamais
                // commence.
                if ($tenant->currentStatus() === \App\Enums\TenantStatus::Pending) {
                    return false;
                }

                $period = $this->subscriptions->normalizePeriod($this->lastBillingPeriodOf($tenant));
                [$startsAt] = $this->subscriptions->nextPeriodFor($tenant, $period);

                return $this->liveInvoiceCovering($tenant, $startsAt) === null;
            })
            ->values();
    }

    /**
     * Contenu du PDF, relu depuis le disque et regenere seulement s'il manque.
     */
    public function pdfContents(TenantInvoice $invoice): string
    {
        $disk = Storage::disk((string) config('billing.pdf_disk', 'local'));

        if ($invoice->pdf_path && $disk->exists($invoice->pdf_path)) {
            return (string) $disk->get($invoice->pdf_path);
        }

        return $this->storePdf($invoice);
    }

    public function filename(TenantInvoice $invoice): string
    {
        return 'Facture_' . str_replace('/', '-', $invoice->number) . '.pdf';
    }

    /**
     * @throws BillingNotConfiguredException
     */
    public function assertIssuerConfigured(): void
    {
        $missing = $this->missingIssuerFields();

        if ($missing !== []) {
            throw new BillingNotConfiguredException($missing);
        }
    }

    /**
     * @return array<int, string>
     */
    public function missingIssuerFields(): array
    {
        $issuer   = (array) config('billing.issuer', []);
        $required = (array) config('billing.required_issuer_fields', []);

        return array_values(array_filter(
            $required,
            static fn (string $field): bool => blank($issuer[$field] ?? null)
        ));
    }

    /**
     * Montant en toutes lettres, mention obligatoire sur une facture.
     *
     * « arrêtée la présente facture à la somme de … » ferme le montant : c'est
     * ce qui empêche qu'un chiffre soit retouché après coup.
     */
    public function amountInWords(int $cents): string
    {
        $dirhams   = intdiv($cents, 100);
        $centimes  = $cents % 100;

        $spell = static function (int $number): string {
            if (class_exists(\NumberFormatter::class)) {
                $formatter = new \NumberFormatter('fr', \NumberFormatter::SPELLOUT);

                return (string) $formatter->format($number);
            }

            // Sans l'extension intl, le chiffre vaut mieux qu'une mention vide.
            return (string) $number;
        };

        $words = ucfirst($spell($dirhams)) . ' dirham' . ($dirhams > 1 ? 's' : '');

        if ($centimes > 0) {
            $words .= ' et ' . $spell($centimes) . ' centime' . ($centimes > 1 ? 's' : '');
        }

        return $words;
    }

    // ── Interne ──────────────────────────────────────────────────────────

    private function storePdf(TenantInvoice $invoice): string
    {
        // Sous-ensemble de glyphes : sans lui dompdf embarque la police
        // entière et chaque facture pèse près d'un méga-octet en pièce jointe.
        // Réglé ici et non dans config/dompdf.php, qui n'est pas publié : le
        // changer globalement toucherait aussi les documents des tenants.
        $pdf = Pdf::setOptions(['enable_font_subsetting' => true])
            ->loadView('pdf.subscription-invoice', [
            'invoice'       => $invoice,
            'issuer'        => $invoice->snapshot['issuer'] ?? (array) config('billing.issuer'),
            'client'        => $invoice->snapshot['client'] ?? [],
            'plan'          => $invoice->snapshot['plan'] ?? [],
            // Calcule ici plutot que dans la vue : le montant en toutes lettres
            // est une mention legale, pas une decoration de mise en page.
            'amountInWords' => $this->amountInWords($invoice->amount_ttc_cents),
            ])->setPaper('a4');

        $contents = $pdf->output();

        $path = trim((string) config('billing.pdf_path', 'invoices'), '/')
            . '/' . $this->filename($invoice);

        Storage::disk((string) config('billing.pdf_disk', 'local'))->put($path, $contents);

        $invoice->pdf_path = $path;
        $invoice->saveQuietly();

        return $contents;
    }

    /**
     * Identite des deux parties et tarif, figes a l'emission.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Tenant $tenant, string $plan): array
    {
        $definition = $this->plans->get($plan);

        return [
            'issuer' => (array) config('billing.issuer'),
            'client' => [
                'tenant_id' => $tenant->id,
                'name'      => $tenant->name,
                'email'     => $tenant->email,
                'phone'     => $tenant->signup_phone,
                'domain'    => $tenant->domains()->first()?->domain,
                // Mentions legales du client : a saisir a la souscription. Une
                // facture B2B marocaine doit porter l'ICE du client des qu'il
                // en a un.
                'ice'       => $tenant->billing_ice,
                'address'   => $tenant->billing_address,
            ],
            'plan' => [
                'key'      => $definition['key'] ?? $plan,
                'name'     => $definition['name'] ?? $plan,
                'tagline'  => $definition['tagline'] ?? null,
                'features' => $definition['features'] ?? [],
                'limits'   => $definition['limits'] ?? [],
            ],
        ];
    }

    /**
     * Periodicite retenue la derniere fois. A defaut, mensuel.
     */
    private function lastBillingPeriodOf(Tenant $tenant): string
    {
        return (string) (
            $tenant->requested_billing_period
            ?? TenantPayment::forTenant((string) $tenant->id)->latestFirst()->value('billing_period')
            ?? SubscriptionService::PERIOD_MONTHLY
        );
    }

    private function liveInvoiceCovering(Tenant $tenant, CarbonImmutable $periodStart): ?TenantInvoice
    {
        return TenantInvoice::forTenant((string) $tenant->id)
            ->live()
            ->whereDate('period_starts_at', '<=', $periodStart->toDateString())
            ->whereDate('period_ends_at', '>=', $periodStart->toDateString())
            ->first();
    }

    private function isFirstInvoice(Tenant $tenant): bool
    {
        return !TenantInvoice::forTenant((string) $tenant->id)->live()->exists();
    }
}
