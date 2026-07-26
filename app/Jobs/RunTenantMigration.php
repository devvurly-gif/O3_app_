<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunTenantMigration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(private string $tenantId) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (!$tenant) {
            Log::warning("[MIGRATE] Tenant {$this->tenantId} not found");
            return;
        }

        Log::info("[MIGRATE] Running migrations for tenant {$this->tenantId}");

        $tenant->run(function () {
            Artisan::call('migrate', ['--force' => true, '--path' => 'database/migrations/tenant']);
        });

        Log::info("[MIGRATE] Migrations done for tenant {$this->tenantId}");
    }
}
