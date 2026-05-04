<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same logic as the tenant-side migration of identical filename — see
 * database/migrations/tenant/2026_05_04_000001_normalize_email_settings_to_brevo.php
 *
 * The central app also has its own settings table (used by
 * PublicRegistrationController to send signup emails), so the same
 * Gmail-to-Brevo normalization needs to apply here.
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
        // Intentionally empty — see tenant migration.
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
