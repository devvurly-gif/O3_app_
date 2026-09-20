<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TenantStatus;
use App\Mail\PlanChangeRequestMail;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Cycle de vie d'un abonnement : essai, échéance, encaissement, suspension.
 *
 * Tout ce qui fait avancer un tenant dans son cycle commercial passe par ici.
 * Les statuts ne se modifient nulle part ailleurs — c'est la condition pour
 * qu'ils restent fiables, et donc facturables.
 */
class SubscriptionService
{
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_YEARLY  = 'yearly';

    public function __construct(private readonly PlanService $plans)
    {
    }

    /**
     * Ouvre l'essai gratuit. Appelé à la création du tenant.
     *
     * `trial_ends_at` est conservée à titre d'historique ; c'est
     * `subscription_ends_at` qui fait foi partout ailleurs.
     */
    public function startTrial(Tenant $tenant, ?string $plan = null): Tenant
    {
        $endsAt = CarbonImmutable::now()->addDays((int) config('plans.trial_days', 14));

        $tenant->status               = TenantStatus::Trial;
        $tenant->trial_ends_at        = $endsAt;
        $tenant->subscription_ends_at = $endsAt;

        return $this->plans->applyTo(
            $tenant,
            $plan ?? (string) config('plans.trial_plan', 'pro')
        );
    }

    /**
     * Enregistre un règlement et repousse l'échéance.
     *
     * Le montant est facultatif : le tarif catalogue s'applique par défaut,
     * mais une remise négociée doit pouvoir être saisie telle quelle — sinon
     * la table des règlements ne reflète plus ce qui a réellement été encaissé.
     *
     * @param array{plan?: string|null, billing_period?: string|null, amount_cents?: int|null, paid_at?: string|null, method?: string|null, reference?: string|null, note?: string|null, recorded_by?: int|null} $data
     */
    public function recordPayment(Tenant $tenant, array $data = []): TenantPayment
    {
        $plan   = $this->plans->normalize($data['plan'] ?? $tenant->plan);
        $period = $this->normalizePeriod($data['billing_period'] ?? self::PERIOD_MONTHLY);
        $paidAt = CarbonImmutable::parse($data['paid_at'] ?? 'now')->startOfDay();

        [$startsAt, $endsAt] = $this->nextPeriodFor($tenant, $period);

        $amount = $data['amount_cents'] ?? $this->catalogPrice($plan, $period);

        return DB::transaction(function () use ($tenant, $plan, $period, $paidAt, $startsAt, $endsAt, $amount, $data) {
            $payment = TenantPayment::create([
                'tenant_id'        => $tenant->id,
                'plan'             => $plan,
                'billing_period'   => $period,
                'amount_cents'     => (int) $amount,
                'paid_at'          => $paidAt->toDateString(),
                'period_starts_at' => $startsAt->toDateString(),
                'period_ends_at'   => $endsAt->toDateString(),
                'method'           => $data['method']      ?? 'virement',
                'reference'        => $data['reference']   ?? null,
                'note'             => $data['note']        ?? null,
                'recorded_by'      => $data['recorded_by'] ?? null,
            ]);

            $tenant->status               = TenantStatus::Active;
            $tenant->subscription_ends_at = $endsAt;
            $tenant->requested_plan       = null;
            $tenant->requested_at         = null;

            $this->plans->applyTo($tenant, $plan);

            // Solde la facture ouverte correspondante. Résolu ici et non
            // injecté au constructeur : SubscriptionInvoiceService dépend
            // lui-même de ce service (il a besoin de nextPeriodFor et du tarif
            // catalogue), et l'injecter créerait un cycle.
            app(SubscriptionInvoiceService::class)->settleOutstanding($tenant, $payment);

            return $payment;
        });
    }

    /**
     * Période qui suit celle en cours.
     *
     * Un client qui règle en avance ne doit pas perdre les jours qui lui
     * restent : la nouvelle période s'enchaîne à la précédente tant que
     * celle-ci court encore, et ne démarre à aujourd'hui que si l'échéance est
     * déjà passée.
     *
     * Partagé avec SubscriptionInvoiceService : la facture doit porter très
     * exactement la période que le règlement ouvrira, sinon le client paie une
     * chose et reçoit l'autre.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} début, fin
     */
    public function nextPeriodFor(Tenant $tenant, string $billingPeriod): array
    {
        $period  = $this->normalizePeriod($billingPeriod);
        $current = $tenant->subscription_ends_at;

        $startsAt = ($current !== null && $current->endOfDay()->isFuture())
            ? CarbonImmutable::parse($current)->addDay()->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $endsAt = ($period === self::PERIOD_YEARLY)
            ? $startsAt->addYear()->subDay()
            : $startsAt->addMonth()->subDay();

        return [$startsAt, $endsAt];
    }

    /**
     * Le client choisit une formule depuis son espace. Enregistre l'intention
     * et prévient O3App : c'est le déclencheur de l'envoi du contrat et de la
     * facture, pas un encaissement.
     *
     * @param array{billing_ice?: string|null, billing_address?: string|null} $billing
     */
    public function requestPlan(
        Tenant $tenant,
        string $plan,
        ?string $billingPeriod = null,
        ?string $note = null,
        array $billing = [],
    ): Tenant {
        if (!$this->plans->exists($plan)) {
            throw new InvalidArgumentException("Formule inconnue : {$plan}");
        }

        // Mentions légales de facturation : on n'écrase jamais une valeur déjà
        // connue par un champ laissé vide.
        foreach (['billing_ice', 'billing_address'] as $field) {
            if (filled($billing[$field] ?? null)) {
                $tenant->{$field} = $billing[$field];
            }
        }

        $tenant->requested_plan           = $plan;
        $tenant->requested_billing_period = $this->normalizePeriod($billingPeriod ?? self::PERIOD_MONTHLY);
        $tenant->requested_at             = CarbonImmutable::now()->toIso8601String();
        $tenant->requested_note           = $note;
        $tenant->save();

        $this->notifyPlanRequest($tenant);

        return $tenant;
    }

    /**
     * Recalcule le statut d'après les dates. Appelé par `subscriptions:check`.
     *
     * Ne touche jamais un tenant en attente de vérification d'email : son
     * accès est déjà fermé, et le faire glisser vers « suspendu » brouillerait
     * la seule information utile — il n'a jamais commencé.
     */
    public function refreshStatus(Tenant $tenant): Tenant
    {
        $target = $this->projectedStatusFor($tenant);

        if ($target !== $tenant->currentStatus()) {
            $tenant->status = $target;
            $tenant->save();
        }

        return $tenant;
    }

    /**
     * Statut que les dates imposent, sans rien écrire.
     *
     * Sert aussi bien à refreshStatus() qu'à l'option --dry-run de la commande
     * `subscriptions:check` : deux implémentations de cette règle finiraient
     * fatalement par diverger, et la simulation cesserait de dire la vérité.
     */
    public function projectedStatusFor(Tenant $tenant): TenantStatus
    {
        $status = $tenant->currentStatus();

        if ($status === TenantStatus::Pending || $tenant->subscription_ends_at === null) {
            return $status;
        }

        $endsAt      = CarbonImmutable::parse($tenant->subscription_ends_at)->endOfDay();
        $suspendedAt = $endsAt->addDays((int) config('plans.grace_days', 15));
        $now         = CarbonImmutable::now();

        return match (true) {
            $now->greaterThan($suspendedAt) => TenantStatus::Suspended,
            $now->greaterThan($endsAt)      => TenantStatus::PastDue,
            // L'échéance est de nouveau dans le futur (règlement enregistré à
            // la main sur un compte suspendu) : le compte redevient actif, sauf
            // s'il est encore en période d'essai.
            $status === TenantStatus::Trial => TenantStatus::Trial,
            default                         => TenantStatus::Active,
        };
    }

    /**
     * État de l'abonnement et catalogue, tels que l'écran « choisir une
     * formule » en a besoin.
     *
     * Renvoie un tableau simple plutôt qu'une API Resource : le projet n'en
     * utilise nulle part et le frontend consomme du JSON brut — une ressource
     * isolée ajouterait un enrobage `data` que personne n'attend.
     *
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant): array
    {
        $status = $tenant->currentStatus();

        return [
            'status'               => $status->value,
            'status_label'         => $status->label(),
            'plan'                 => $tenant->plan,
            'plan_name'            => $this->plans->get((string) $tenant->plan)['name'] ?? $tenant->plan,
            'subscription_ends_at' => $tenant->subscription_ends_at?->toDateString(),
            'days_left'            => $tenant->daysUntilExpiry(),
            'can_write'            => $tenant->canWrite(),
            'is_trial'             => $status === TenantStatus::Trial,
            'grace_days'           => (int) config('plans.grace_days', 15),
            'features'             => $this->plans->featuresForTenant($tenant),
            'requested_plan'       => $tenant->requested_plan,
            'requested_at'         => $tenant->requested_at,
            'plans'                => array_values($this->plans->all()),
        ];
    }

    /**
     * Tarif catalogue, en centimes.
     */
    public function catalogPrice(string $plan, string $period): int
    {
        $definition = $this->plans->get($plan);

        return (int) ($this->normalizePeriod($period) === self::PERIOD_YEARLY
            ? ($definition['price_year_cents'] ?? 0)
            : ($definition['price_month_cents'] ?? 0));
    }

    public function normalizePeriod(?string $period): string
    {
        return in_array($period, [self::PERIOD_MONTHLY, self::PERIOD_YEARLY], true)
            ? $period
            : self::PERIOD_MONTHLY;
    }

    /**
     * Un échec d'envoi ne doit pas faire échouer la demande du client : elle
     * est déjà enregistrée sur le tenant et visible au back-office.
     */
    private function notifyPlanRequest(Tenant $tenant): void
    {
        $to = config('mail.admin_notification_to') ?: config('mail.from.address');

        if (!$to) {
            return;
        }

        try {
            Mail::to($to)->send(new PlanChangeRequestMail(
                tenantId: (string) $tenant->id,
                companyName: (string) $tenant->name,
                email: (string) $tenant->email,
                phone: $tenant->signup_phone,
                plan: (string) $tenant->requested_plan,
                planName: (string) ($this->plans->get((string) $tenant->requested_plan)['name'] ?? $tenant->requested_plan),
                billingPeriod: (string) $tenant->requested_billing_period,
                note: $tenant->requested_note,
            ));
        } catch (\Throwable $e) {
            Log::error('PlanChangeRequestMail failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
