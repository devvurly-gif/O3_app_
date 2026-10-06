<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForEachTenant;
use App\Services\Agents\AgentRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Donne un compte utilisateur à chaque agent qui n'en a pas (agents du socle et agents recrutés).
 *
 * Un compte par agent, jamais partagé ; rôle « Agent IA » sans aucune permission ; mot de passe aléatoire que
 * personne ne connaît (connexion à l'interface impossible) ; aucun jeton émis. Idempotent : un agent qui a déjà
 * son compte (Achats, Ventes) n'est pas touché. Le même travail est proposé dans le chat de l'orchestrateur
 * (« crée les comptes des agents »), validé par un clic.
 */
class CreateAgentAccounts extends Command
{
    use RunsForEachTenant;

    protected $signature = 'agents:create-accounts {--tenant= : ID d\'un tenant (sinon tous les tenants en règle)}';

    protected $description = 'Crée le compte utilisateur de chaque agent IA qui n\'en a pas (sans jeton, sans permission)';

    public function handle(AgentRegistry $registry): int
    {
        return $this->runForEachTenant(function () use ($registry) {
            if (!Schema::hasTable('agents')) {
                return self::SUCCESS;
            }

            $report = $registry->ensureAllAccounts();
            if ($report === []) {
                $this->line('Tous les agents ont déjà un compte.');
            }
            foreach ($report as $name => $state) {
                $this->line("{$name} : {$state}");
            }

            return self::SUCCESS;
        }, writes: true, only: $this->option('tenant'));
    }
}
