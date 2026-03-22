<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Company info
            ['st_domain' => 'company', 'st_key' => 'name',        'st_value' => 'o2 App SARL'],
            ['st_domain' => 'company', 'st_key' => 'ice',         'st_value' => '000000000000000'],
            ['st_domain' => 'company', 'st_key' => 'rc',          'st_value' => '12345'],
            ['st_domain' => 'company', 'st_key' => 'patente',     'st_value' => '12345678'],
            ['st_domain' => 'company', 'st_key' => 'iden_fiscal', 'st_value' => '12345678'],
            ['st_domain' => 'company', 'st_key' => 'address',     'st_value' => 'Rue Example, Casablanca'],
            ['st_domain' => 'company', 'st_key' => 'phone',       'st_value' => '+212600000000'],
            ['st_domain' => 'company', 'st_key' => 'email',       'st_value' => 'contact@o2app.ma'],
            ['st_domain' => 'company', 'st_key' => 'logo',        'st_value' => null],

            // Invoice settings
            ['st_domain' => 'invoice', 'st_key' => 'default_tva',       'st_value' => '20'],
            ['st_domain' => 'invoice', 'st_key' => 'default_due_days',  'st_value' => '30'],
            ['st_domain' => 'invoice', 'st_key' => 'legal_mentions',    'st_value' => 'Merci pour votre confiance.'],
            ['st_domain' => 'invoice', 'st_key' => 'bank_name',         'st_value' => 'Attijariwafa Bank'],
            ['st_domain' => 'invoice', 'st_key' => 'bank_rib',          'st_value' => '007 780 0000000000000000 00'],
            ['st_domain' => 'invoice', 'st_key' => 'bank_iban',         'st_value' => 'MA64007780000000000000000'],

            // App settings
            ['st_domain' => 'app', 'st_key' => 'currency',       'st_value' => 'MAD'],
            ['st_domain' => 'app', 'st_key' => 'currency_symbol','st_value' => 'DH'],
            ['st_domain' => 'app', 'st_key' => 'date_format',    'st_value' => 'd/m/Y'],
            ['st_domain' => 'app', 'st_key' => 'language',       'st_value' => 'fr'],
            ['st_domain' => 'app', 'st_key' => 'timezone',       'st_value' => 'Africa/Casablanca'],

            // Stock settings
            ['st_domain' => 'stock', 'st_key' => 'autoriser_stock_negatif', 'st_value' => 'false'],

            // Sales settings
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
