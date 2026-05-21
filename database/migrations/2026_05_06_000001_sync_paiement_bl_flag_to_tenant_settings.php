<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-sync the central `tenants.data.paiement_bl_enabled` flag into each
 * tenant's own `settings` row (`ventes.paiement_sur_bl`).
 *
 * Why this is needed: TenantController::update() already does this sync
 * whenever the toggle is flipped via /central/tenants/:id, BUT tenants
 * created before the sync was added — or whose flag was poked directly
 * in the DB — drift out of alignment. Symptom on teliphoni today:
 *
 *   tenants.data.paiement_bl_enabled       = true
 *   settings.ventes.paiement_sur_bl        = false   ← desync
 *
 * StorePaymentRequest reads the per-tenant setting, so a payment posted
 * to a BL gets rejected with HTTP 422 even though the central admin
 * thinks the feature is on. The user is then forced to convert the BL
 * to an invoice just to record a payment — exactly the workflow trap
 * we're trying to remove.
 *
 * Idempotent: re-running is a no-op once values are aligned.
 *
 * NOTE: this migration runs in the CENTRAL connection. We loop over
 * tenants and use Stancl's `$tenant->run()` to switch to each tenant's
 * DB before calling `Setting::set()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            $expected = (bool) ($tenant->paiement_bl_enabled ?? false);

            try {
                $tenant->run(function () use ($expected) {
                    \App\Models\Setting::set(
                        'ventes',
                        'paiement_sur_bl',
                        $expected ? 'true' : 'false',
                    );
                });
            } catch (\Throwable $e) {
                // A tenant DB that's not reachable (deleted, migrating)
                // shouldn't block the whole batch. Log via stderr —
                // Laravel's migration runner will surface it in CI.
                fwrite(
                    STDERR,
                    "  ! sync skipped for tenant '{$tenant->id}': {$e->getMessage()}\n",
                );
            }
        });
    }

    public function down(): void
    {
        // No-op: rolling this back would only re-introduce the desync.
    }
};
