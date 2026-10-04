<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachTenant;
use App\Models\Agent;
use App\Services\Agents\AgentOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque matin, l'agent Recouvrement contrôle les encaissements et prépare les
 * relances en BROUILLON. Aucun client n'est contacté : un humain valide chaque
 * relance dans « Ventes → Relances de paiement ».
 *
 * Ignore les tenants où l'agent n'est pas activé (agents.is_active = false) ou
 * dont les tables du socle n'existent pas encore.
 */
class PrepareCollectionReminders extends Command
{
    use RunsForEachTenant;

    protected $signature = 'recouvrement:prepare {--tenant= : ID d\'un tenant (lancement manuel)}';

    protected $description = "L'agent Recouvrement contrôle les encaissements et prépare les relances de paiement (brouillons)";

    public function handle(AgentOrderService $orders): int
    {
        return $this->runForEachTenant(function () use ($orders) {
            if (!Schema::hasTable('agents') || !Schema::hasTable('payment_reminders')) {
                return self::SUCCESS;
            }
            if (!Agent::where('domain', 'recouvrement')->where('is_active', true)->exists()) {
                $this->line('Agent Recouvrement inactif : ignoré.');
                return self::SUCCESS;
            }

            $out = $orders->orderCollections('Planificateur');
            if (!$out['ok']) {
                $this->warn($out['message']);
                return self::FAILURE;
            }

            $r = $out['result'];
            $this->info(sprintf(
                '%d facture(s) en retard, %d relance(s) préparée(s), %d anomalie(s) d\'encaissement.',
                $r['overdue'], count($r['created']), count($r['anomalies'])
            ));

            return self::SUCCESS;
        }, writes: true, only: $this->option('tenant'));
    }
}
