<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TreasuryService;
use Illuminate\Console\Command;

/**
 * Matérialise les écritures récurrentes de trésorerie (loyer, salaires…).
 *
 * Sans argument la commande balaie tous les tenants actifs : elle est appelée
 * par le planificateur depuis le contexte central, où aucune base tenant n'est
 * connectée — s'appuyer sur le tenant « courant » ne générerait rien du tout.
 */
class GenerateTreasuryRecurrences extends Command
{
    protected $signature = 'treasury:generate
        {--tenant= : Ne traiter qu\'un tenant}
        {--up-to= : Générer les échéances jusqu\'à cette date (défaut : aujourd\'hui)}
        {--dry-run : Compter sans rien créer}';

    protected $description = 'Génère les écritures de trésorerie récurrentes arrivées à échéance';

    public function handle(TreasuryService $treasury): int
    {
        $upTo   = $this->option('up-to');
        $dryRun = (bool) $this->option('dry-run');

        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->error('Aucun tenant à traiter.');
            return self::FAILURE;
        }

        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $result = ['created' => 0, 'recurrences' => 0];

            try {
                $tenant->run(function () use ($treasury, $upTo, $dryRun, &$result) {
                    $result = $treasury->generateDueRecurrences($upTo, $dryRun);
                });
            } catch (\Throwable $e) {
                // Un tenant sans le module (migration non passée) ne doit pas
                // interrompre la tournée des autres.
                $this->warn("  {$tenant->id} : ignoré — {$e->getMessage()}");
                continue;
            }

            $totalCreated += $result['created'];

            if ($result['created'] > 0) {
                $this->info("  {$tenant->id} : {$result['created']} écriture(s) depuis {$result['recurrences']} récurrence(s).");
            } else {
                $this->line("  {$tenant->id} : rien à générer.");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '') . "Terminé — {$totalCreated} écriture(s) au total.");

        return self::SUCCESS;
    }
}
