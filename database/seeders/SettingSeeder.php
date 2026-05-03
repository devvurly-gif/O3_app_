<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed the canonical settings for a fresh tenant.
 *
 * IMPORTANT: keys here MUST match SettingController::ALLOWED_SETTINGS.
 * Adding a setting in the UI requires updating BOTH that whitelist
 * AND this seeder. A drift is what produced the legacy `iden_fiscal`,
 * `default_tva`, `app.*` keys that were silently rejected on save.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Company info ──────────────────────────────────────────
            ['st_domain' => 'company', 'st_key' => 'name',     'st_value' => 'O3 App SARL'],
            ['st_domain' => 'company', 'st_key' => 'phone',    'st_value' => '+212600000000'],
            ['st_domain' => 'company', 'st_key' => 'email',    'st_value' => 'contact@o3app.ma'],
            ['st_domain' => 'company', 'st_key' => 'ice',      'st_value' => '000000000000000'],
            ['st_domain' => 'company', 'st_key' => 'rc',       'st_value' => '12345'],
            ['st_domain' => 'company', 'st_key' => 'if',       'st_value' => '12345678'],
            ['st_domain' => 'company', 'st_key' => 'patente',  'st_value' => '12345678'],
            ['st_domain' => 'company', 'st_key' => 'address',  'st_value' => 'Rue Example, Casablanca'],
            ['st_domain' => 'company', 'st_key' => 'city',     'st_value' => 'Casablanca'],
            ['st_domain' => 'company', 'st_key' => 'logo',     'st_value' => null],

            // ── Invoice settings ─────────────────────────────────────
            ['st_domain' => 'invoice', 'st_key' => 'default_tax_rate',    'st_value' => '20'],
            ['st_domain' => 'invoice', 'st_key' => 'payment_terms_days',  'st_value' => '30'],
            ['st_domain' => 'invoice', 'st_key' => 'footer_note',         'st_value' => 'Merci pour votre confiance.'],

            // ── Locale ────────────────────────────────────────────────
            ['st_domain' => 'locale', 'st_key' => 'currency',        'st_value' => 'MAD'],
            ['st_domain' => 'locale', 'st_key' => 'currency_symbol', 'st_value' => 'DH'],
            ['st_domain' => 'locale', 'st_key' => 'date_format',     'st_value' => 'd/m/Y'],
            ['st_domain' => 'locale', 'st_key' => 'language',        'st_value' => 'fr'],
            ['st_domain' => 'locale', 'st_key' => 'timezone',        'st_value' => 'Africa/Casablanca'],

            // ── Stock ─────────────────────────────────────────────────
            ['st_domain' => 'stock', 'st_key' => 'autoriser_stock_negatif', 'st_value' => 'false'],
            ['st_domain' => 'stock', 'st_key' => 'seuil_alerte_stock',      'st_value' => '5'],

            // ── Sales ─────────────────────────────────────────────────
            ['st_domain' => 'ventes', 'st_key' => 'paiement_sur_bl', 'st_value' => 'false'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['st_domain' => $setting['st_domain'], 'st_key' => $setting['st_key']],
                ['st_value'  => $setting['st_value']]
            );
        }
    }
}
