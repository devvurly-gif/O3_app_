<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalize legacy setting keys to the canonical names in
 * SettingController::ALLOWED_SETTINGS.
 *
 * Drift origin: the SettingSeeder used to ship keys that pre-dated the
 * security whitelist (iden_fiscal, default_tva, default_due_days,
 * legal_mentions, app.*). Saving any group from /settings/app then failed
 * with HTTP 422 "Unknown setting keys for this domain." because the form
 * blindly merged DB rows into its reactive object and re-posted the
 * unknown keys.
 *
 * This migration:
 *   1. Renames legacy keys, preserving any value already entered by the
 *      tenant (st_value carried over via single UPDATE).
 *   2. Drops orphaned keys that no UI surface or backend code still uses
 *      (bank_*, lone legal_mentions). Easier than carrying them in the
 *      whitelist forever for no functional reason.
 *   3. Is fully idempotent — re-running is a no-op once cleaned up.
 *
 * Renames are run as DELETE-on-collision then UPDATE so we don't trip the
 * unique (st_domain, st_key) index when a tenant already has BOTH the
 * legacy and canonical key (rare but possible).
 */
return new class extends Migration
{
    public function up(): void
    {
        // (legacy_domain, legacy_key) → (new_domain, new_key)
        $renames = [
            ['company',  'iden_fiscal',       'company',  'if'],
            ['invoice',  'default_tva',       'invoice',  'default_tax_rate'],
            ['invoice',  'default_due_days',  'invoice',  'payment_terms_days'],
            ['app',      'currency',          'locale',   'currency'],
            ['app',      'currency_symbol',   'locale',   'currency_symbol'],
            ['app',      'date_format',       'locale',   'date_format'],
            ['app',      'language',          'locale',   'language'],
            ['app',      'timezone',          'locale',   'timezone'],
        ];

        foreach ($renames as [$oldDomain, $oldKey, $newDomain, $newKey]) {
            // If the canonical row already exists, just delete the legacy
            // duplicate (we keep the canonical one with whatever value
            // the user currently has).
            $canonicalExists = DB::table('settings')
                ->where('st_domain', $newDomain)
                ->where('st_key', $newKey)
                ->exists();

            if ($canonicalExists) {
                DB::table('settings')
                    ->where('st_domain', $oldDomain)
                    ->where('st_key', $oldKey)
                    ->delete();
                continue;
            }

            // Otherwise, rename the legacy row in place.
            DB::table('settings')
                ->where('st_domain', $oldDomain)
                ->where('st_key', $oldKey)
                ->update([
                    'st_domain' => $newDomain,
                    'st_key'    => $newKey,
                    'updated_at' => now(),
                ]);
        }

        // Orphaned legacy keys with no current backend or UI consumer.
        // Removing them keeps the API surface clean and prevents future
        // 422s if a stray UI ever re-loads them.
        DB::table('settings')
            ->where('st_domain', 'invoice')
            ->whereIn('st_key', ['legal_mentions', 'bank_name', 'bank_rib', 'bank_iban'])
            ->delete();
    }

    public function down(): void
    {
        // No rollback — this is a data normalization migration. Reverting
        // would re-introduce the broken keys. If a tenant truly needs the
        // legacy names back, they should be added explicitly to the
        // ALLOWED_SETTINGS whitelist, not resurrected blindly.
    }
};
