<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Observers\TenantObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionTenantSsl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private string $tenantId) {}

    public function handle(): void
    {
        $output   = [];
        $exitCode = 0;

        exec('sudo /usr/local/bin/certbot-add-tenant.sh ' . escapeshellarg($this->tenantId) . ' 2>&1', $output, $exitCode);

        $log = implode("\n", $output);

        if ($exitCode !== 0) {
            Log::error("[SSL] Failed to provision cert for tenant {{$this->tenantId}}", ['output' => $log]);
            $this->fail(new \RuntimeException("certbot exited with code $exitCode: $log"));
            return;
        }

        Log::info("[SSL] Certificate provisioned for tenant {{$this->tenantId}}", ['output' => $log]);

        // After SSL is ready, verify the URL is reachable and mark it
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) return;

        $active = TenantObserver::checkUrlActive($tenant);

        $tenant->url_ready = $active;
        $tenant->saveQuietly();

        Log::info("[SSL] Tenant {$this->tenantId} url_ready={$active}");
    }
}
