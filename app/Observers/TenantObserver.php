<?php

namespace App\Observers;

use App\Jobs\ProvisionTenantSsl;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        $tenant->url_ready = false;
        $tenant->saveQuietly();

        ProvisionTenantSsl::dispatch($tenant->id)->delay(now()->addSeconds(5));
    }

    /**
     * When ecom_enabled is switched ON, add shop.<tenant>.o3app.ma to the SSL cert
     * and verify the shop URL is reachable.
     */
    public function updated(Tenant $tenant): void
    {
        if ($this->ecomJustEnabled($tenant)) {
            Log::info("[ECOM] ecom_enabled activated for tenant {$tenant->id} — provisioning shop SSL");
            ProvisionTenantSsl::dispatch($tenant->id)->delay(now()->addSeconds(3));
        }

        if ($this->variantsJustEnabled($tenant)) {
            Log::info("[VARIANTS] variants_enabled activated for tenant {$tenant->id} — running migration");
            \App\Jobs\RunTenantMigration::dispatch($tenant->id);
        }
    }

    /**
     * Detect if ecom_enabled just changed from falsy → true in this update.
     * ecom_enabled lives in the JSON `data` column, so we compare raw JSON.
     */
    private function variantsJustEnabled(Tenant $tenant): bool
    {
        if (!$tenant->variants_enabled) {
            return false;
        }

        $originalData = $tenant->getOriginal('data');
        if (is_string($originalData)) {
            $originalData = json_decode($originalData, true) ?? [];
        }

        return empty($originalData['variants_enabled']);
    }

    private function ecomJustEnabled(Tenant $tenant): bool
    {
        if (!$tenant->ecom_enabled) {
            return false;
        }

        $originalData = $tenant->getOriginal('data');

        // getOriginal('data') may be a string (raw JSON) or already decoded array
        if (is_string($originalData)) {
            $originalData = json_decode($originalData, true) ?? [];
        }

        $wasEnabled = !empty($originalData['ecom_enabled']);

        return !$wasEnabled;
    }

    /**
     * Check if the tenant's primary domain is reachable over HTTPS.
     */
    public static function checkUrlActive(Tenant $tenant): bool
    {
        $domain = $tenant->domains()->first()?->domain;

        if (!$domain) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->head('https://' . $domain);

            $active = $response->status() < 500;

            Log::info("[URL-CHECK] {$domain} → HTTP {$response->status()} → " . ($active ? 'active' : 'down'));

            return $active;
        } catch (\Throwable $e) {
            Log::warning("[URL-CHECK] {$domain} unreachable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if the tenant's shop subdomain is reachable over HTTPS.
     */
    public static function checkShopUrlActive(Tenant $tenant): bool
    {
        $domain = $tenant->domains()->first()?->domain;

        if (!$domain) {
            return false;
        }

        $shopDomain = 'shop.' . $domain;

        try {
            $response = Http::timeout(5)
                ->withoutVerifying()
                ->head('https://' . $shopDomain);

            $active = $response->status() < 500;

            Log::info("[URL-CHECK-SHOP] {$shopDomain} → HTTP {$response->status()} → " . ($active ? 'active' : 'down'));

            return $active;
        } catch (\Throwable $e) {
            Log::warning("[URL-CHECK-SHOP] {$shopDomain} unreachable: " . $e->getMessage());
            return false;
        }
    }
}
