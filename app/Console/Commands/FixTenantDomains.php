<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Domain;

class FixTenantDomains extends Command
{
    protected $signature = 'tenants:fix-domains {--dry-run : Show changes without applying}';
    protected $description = 'Fix tenant domains: remove extra .ma. fragment (e.g. jadeverfes.ma.o3app.ma → jadeverfes.o3app.ma)';

    public function handle(): int
    {
        $domains = Domain::all();
        $fixed = 0;

        foreach ($domains as $domain) {
            // Match domains like: tenant.ma.o3app.ma → tenant.o3app.ma
            if (preg_match('/^(.+)\.ma\.o3app\.ma$/', $domain->domain, $m)) {
                $newDomain = $m[1] . '.o3app.ma';

                if ($this->option('dry-run')) {
                    $this->line("  [DRY] {$domain->domain} → {$newDomain}");
                } else {
                    $domain->update(['domain' => $newDomain]);
                    $this->info("  ✓ {$domain->domain} → {$newDomain}");
                }
                $fixed++;
            } else {
                $this->line("  OK: {$domain->domain}");
            }
        }

        $this->newLine();
        $this->info($fixed ? "{$fixed} domain(s) " . ($this->option('dry-run') ? 'to fix.' : 'fixed.') : 'All domains are clean.');

        return 0;
    }
}
