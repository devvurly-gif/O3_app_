<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Agents\AgentRegistry;
use Illuminate\Console\Command;

/**
 * Rattache les comptes techniques déjà créés des agents IA (Achats, Ventes) à
 * la table `agents`, sans émettre ni révoquer aucun jeton. Idempotent.
 */
class LinkAgentAccounts extends Command
{
    protected $signature = 'agents:link-accounts {tenant=jadema : ID du tenant}';

    protected $description = 'Rattache les comptes techniques des agents IA à la table agents (aucun jeton émis)';

    public function handle(AgentRegistry $registry): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (!$tenant) {
            $this->error("Tenant '{$this->argument('tenant')}' introuvable.");
            return self::FAILURE;
        }

        $tenant->run(function () use ($registry) {
            foreach ($registry->linkExistingAccounts() as $domain => $state) {
                $this->line("{$domain} : {$state}");
            }
        });

        return self::SUCCESS;
    }
}
