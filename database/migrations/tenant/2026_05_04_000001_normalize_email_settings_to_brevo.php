<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replace the legacy Gmail/465/SSL defaults seeded by
 * 2026_03_23_000001_seed_whatsapp_and_email_settings.php with the
 * Brevo (port 2525, TLS) values that actually work on this VPS.
 *
 * Why this matters: DynamicConfigServiceProvider reads these rows
 * at boot and overrides config('mail.*'), even when .env is correct.
 * On 2026-05-03 every outbound email tried smtp.gmail.com:465 and
 * timed out, despite the .env pointing at Brevo.
 *
 * Conservative: only flip a row if it still holds the legacy default.
 * If the tenant has manually set a custom value (e.g. their own SMTP
 * provider), we leave it alone.
 *
 * Idempotent — running twice is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->updateIfLegacy('mail_host',       'smtp.gmail.com',     'smtp-relay.brevo.com');
        $this->updateIfLegacy('mail_port',       '465',                '2525');
        $this->updateIfLegacy('mail_encryption', 'ssl',                'tls');
    }

    public function down(): void
    {
        // No-op: we don't want to revert tenants back to the broken Gmail
        // defaults just because someone rolled back a migration.
    }

    private function updateIfLegacy(string $key, string $legacyValue, string $newValue): void
    {
        DB::table('settings')
            ->where('st_domain', 'email')
            ->where('st_key', $key)
            ->where('st_value', $legacyValue)
            ->update([
                'st_value'   => $newValue,
                'updated_at' => now(),
            ]);
    }
};
