<?php

namespace App\Console\Commands;

use App\Services\TenantResetService;
use Illuminate\Console\Command;

class ResetTenantData extends Command
{
    protected $signature = 'tenant:reset-data {--force : Skip the confirmation prompt}';
    protected $description = 'Wipe all transactions/payments/stock movements and reset stock for the CURRENT tenant. Run via: tenants:run <id> -- tenant:reset-data';

    public function handle(TenantResetService $service): int
    {
        $tenantId = tenant('id') ?? 'central';

        if ($tenantId === 'central') {
            $this->error('Refusing to run against the central database. Use `tenants:run <id> -- tenant:reset-data`.');
            return 1;
        }

        if (!$this->option('force') && !$this->confirm("This will PERMANENTLY delete all transactions/stock for tenant '{$tenantId}'. Continue?")) {
            $this->warn('Aborted.');
            return 1;
        }

        $summary = $service->reset(null);

        $this->info("Tenant '{$tenantId}' reset complete:");
        foreach ($summary as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        return 0;
    }
}
