<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Mail\SubscriptionReminderMail;
use App\Models\Tenant;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fait avancer les abonnements dans le temps : bascule les statuts échus et
 * envoie les relances.
 *
 * C'est la pièce qui manquait pour que l'essai de 14 jours existe réellement.
 * Sans ce cron, `subscription_ends_at` n'est qu'une date que personne ne
 * regarde — exactement ce qu'était `trial_ends_at` jusqu'ici.
 *
 * Tourne sur le domaine central : les tenants vivent dans la base centrale, le
 * contexte tenant n'est jamais initialisé ici.
 */
class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check
                            {--tenant= : Ne traiter qu\'un tenant}
                            {--dry-run : Afficher les décisions sans rien écrire ni envoyer}';

    protected $description = "Bascule les statuts d'abonnement échus et envoie les relances d'échéance";

    public function handle(SubscriptionService $subscriptions, PlanService $plans): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Tenant::query();

        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('Aucun tenant à traiter.');

            return self::SUCCESS;
        }

        $transitions = 0;
        $reminders   = 0;

        foreach ($tenants as $tenant) {
            $before = $tenant->currentStatus();

            if (!$dryRun) {
                $subscriptions->refreshStatus($tenant);
            }

            $after = $dryRun ? $subscriptions->projectedStatusFor($tenant) : $tenant->currentStatus();

            if ($after !== $before) {
                $transitions++;
                $this->line(sprintf(
                    '  %-20s %s → %s',
                    $tenant->id,
                    $before->value,
                    $after->value
                ));
            }

            if ($this->sendReminderIfDue($tenant, $plans, $dryRun)) {
                $reminders++;
            }
        }

        $this->info(sprintf(
            '%d tenant(s) examiné(s) — %d changement(s) de statut, %d relance(s)%s.',
            $tenants->count(),
            $transitions,
            $reminders,
            $dryRun ? ' (simulation)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Envoie la relance du palier atteint, une seule fois par palier et par
     * échéance.
     *
     * La déduplication porte sur le couple (échéance, palier) : un client qui
     * règle puis se retrouve à J-7 de la nouvelle échéance doit être relancé de
     * nouveau, alors qu'un cron qui tournerait deux fois le même jour ne doit
     * rien réexpédier.
     */
    private function sendReminderIfDue(Tenant $tenant, PlanService $plans, bool $dryRun): bool
    {
        $status = $tenant->currentStatus();

        // Un compte jamais vérifié, désactivé à la main ou déjà suspendu depuis
        // longtemps n'a pas à recevoir de relance : le premier n'a jamais
        // commencé, les deux autres ont déjà reçu la leur.
        if (!in_array($status, [TenantStatus::Trial, TenantStatus::Active, TenantStatus::PastDue], true)) {
            return false;
        }

        if (!$tenant->is_active || $tenant->subscription_ends_at === null) {
            return false;
        }

        $daysLeft  = $tenant->daysUntilExpiry();
        $thresholds = (array) config('plans.reminder_days', [7, 3, 1]);

        // Palier atteint : soit l'un des jalons d'avant échéance, soit le jour
        // du basculement en impayé (palier 0).
        $threshold = match (true) {
            $daysLeft === null      => null,
            $daysLeft <= 0          => 0,
            in_array($daysLeft, $thresholds, true) => $daysLeft,
            default                 => null,
        };

        if ($threshold === null) {
            return false;
        }

        $endsAt = $tenant->subscription_ends_at->toDateString();

        if ($tenant->last_reminder_sent_for === $endsAt
            && (int) $tenant->last_reminder_days === $threshold) {
            return false;
        }

        $this->line(sprintf('  %-20s relance J-%d → %s', $tenant->id, $threshold, $tenant->email));

        if ($dryRun) {
            return true;
        }

        try {
            Mail::to($tenant->email)->send(new SubscriptionReminderMail(
                companyName: (string) $tenant->name,
                planName: (string) ($plans->get((string) $tenant->plan)['name'] ?? $tenant->plan),
                endsAt: $tenant->subscription_ends_at->format('d/m/Y'),
                daysLeft: $threshold,
                subscriptionUrl: $tenant->appUrl('/abonnement'),
                isTrial: $status === TenantStatus::Trial,
            ));
        } catch (\Throwable $e) {
            // Un email qui ne part pas ne doit pas interrompre le balayage des
            // autres tenants — mais il ne doit pas non plus être marqué comme
            // envoyé, sinon la relance est perdue pour de bon.
            Log::error('SubscriptionReminderMail failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            $this->error("    échec d'envoi : {$e->getMessage()}");

            return false;
        }

        $tenant->last_reminder_sent_for = $endsAt;
        $tenant->last_reminder_days     = $threshold;
        $tenant->save();

        return true;
    }
}
