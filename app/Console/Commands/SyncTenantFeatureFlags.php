<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncTenantFeatureFlags extends Command
{
    protected $signature = 'tenants:sync-feature-flags {--dry-run : Report drift without writing}';
    protected $description = 'Re-sync central tenant feature flags (e.g. paiement_bl_enabled) into each tenant\'s own settings table, repairing any drift';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $drifted = 0;
        $failed = 0;

        Tenant::all()->each(function (Tenant $tenant) use ($dryRun, &$drifted, &$failed) {
            $expected = (bool) ($tenant->paiement_bl_enabled ?? false);

            try {
                $tenant->run(function () use ($tenant, $expected, $dryRun, &$drifted) {
                    $actual = Setting::get('ventes', 'paiement_sur_bl', 'false') === 'true';

                    if ($actual === $expected) {
                        return;
                    }

                    $drifted++;
                    $this->warn("  drift: {$tenant->id} — central paiement_bl_enabled=" . ($expected ? 'true' : 'false')
                        . ", tenant setting=" . ($actual ? 'true' : 'false'));

                    if (! $dryRun) {
                        Setting::set('ventes', 'paiement_sur_bl', $expected ? 'true' : 'false');
                        $this->info("    fixed: {$tenant->id} → " . ($expected ? 'true' : 'false'));
                    }
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ! sync skipped for tenant '{$tenant->id}': {$e->getMessage()}");
            }
        });

        $this->newLine();
        if ($drifted === 0) {
            $this->info('All tenants in sync.');
        } else {
            $this->info(($dryRun ? "{$drifted} tenant(s) drifted (dry run, nothing written)." : "{$drifted} tenant(s) fixed."));
        }
        if ($failed) {
            $this->warn("{$failed} tenant(s) could not be checked.");
        }

        return 0;
    }
}
