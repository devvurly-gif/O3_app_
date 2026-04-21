<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\ThirdPartner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RecalculatePartnersEncours extends Command
{
    protected $signature = 'partners:recalculate-encours
                            {--tenant= : Run only for a specific tenant id (domain)}
                            {--id= : Recalculate only a specific partner id}
                            {--role=all : Filter by role (customer, supplier, both, all)}
                            {--dry-run : Show what would change without persisting}';

    protected $description = 'Recompute encours_actuel for every third partner from source data across all tenants (invoices + BLs if paiement_sur_bl + purchases − payments − returns).';

    public function handle(): int
    {
        $tenantFilter = $this->option('tenant');

        $tenantsQuery = Tenant::query();
        if ($tenantFilter) {
            $tenantsQuery->whereKey($tenantFilter);
        }

        $tenants = $tenantsQuery->get();

        if ($tenants->isEmpty()) {
            $this->error($tenantFilter
                ? "Tenant '{$tenantFilter}' introuvable."
                : 'Aucun tenant trouvé.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $id = $this->option('id');
        $role = $this->option('role');

        $grandChanged = 0;
        $grandUnchanged = 0;

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info(sprintf('▶ Tenant: %s', $tenant->id));

            $tenant->run(function () use ($dryRun, $id, $role, &$grandChanged, &$grandUnchanged) {
                $query = ThirdPartner::query();

                if ($id) {
                    $query->whereKey($id);
                }
                if ($role !== 'all') {
                    $query->where('tp_Role', $role);
                }

                $partners = $query->get();

                if ($partners->isEmpty()) {
                    $this->line('  (aucun partenaire à traiter)');
                    return;
                }

                $this->line(sprintf('  %sTraitement de %d partenaire(s)…',
                    $dryRun ? '[DRY-RUN] ' : '', $partners->count()));

                $changed = 0;
                $unchanged = 0;

                foreach ($partners as $partner) {
                    $before = (float) $partner->encours_actuel;
                    $after = $partner->recalculateEncours(persist: !$dryRun);
                    $delta = round($after - $before, 2);

                    if (abs($delta) < 0.01) {
                        $unchanged++;
                        continue;
                    }

                    $changed++;
                    $this->line(sprintf(
                        '    %-40s  %10.2f → %10.2f  (Δ %+.2f)',
                        Str::limit($partner->tp_title, 38),
                        $before,
                        $after,
                        $delta
                    ));
                }

                $this->line(sprintf('  → %d changé(s), %d inchangé(s)', $changed, $unchanged));
                $grandChanged += $changed;
                $grandUnchanged += $unchanged;
            });
        }

        $this->newLine();
        $this->info(sprintf(
            '%sTerminé. Total : %d changé(s), %d inchangé(s) sur %d tenant(s).',
            $dryRun ? '[DRY-RUN] ' : '',
            $grandChanged,
            $grandUnchanged,
            $tenants->count()
        ));

        return self::SUCCESS;
    }
}
