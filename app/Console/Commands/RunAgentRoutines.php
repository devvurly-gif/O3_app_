<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachTenant;
use App\Models\AgentRoutine;
use App\Models\Tenant;
use App\Services\Agents\RoutineRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Exécute les routines planifiées de l'orchestrateur arrivées à échéance (toutes les 15 minutes).
 *
 * Une routine ne fait que lire ou préparer des brouillons (voir RoutineSteps) : aucun client n'est contacté et
 * rien n'est appliqué sans validation. Le compte rendu est déposé dans la conversation de l'administrateur qui
 * l'a créée. Une routine n'est jamais exécutée deux fois pour la même échéance (voir RoutineRunner::runDue).
 */
class RunAgentRoutines extends Command
{
    use RunsForEachTenant;

    protected $signature = 'agents:run-routines {--tenant= : ID d\'un tenant (lancement manuel)}';

    protected $description = "Exécute les routines planifiées de l'orchestrateur arrivées à échéance (brouillons et lectures seulement)";

    public function handle(RoutineRunner $runner): int
    {
        return $this->runForEachTenant(function (Tenant $tenant) use ($runner) {
            if (!$tenant->agentsUsable() || !Schema::hasTable('agent_routines')) {
                return self::SUCCESS;
            }

            $runner->runDue(fn (AgentRoutine $r, string $status) => $this->line("Routine #{$r->id} « {$r->name} » : {$status}"));

            return self::SUCCESS;
        }, writes: true, only: $this->option('tenant'));
    }
}
