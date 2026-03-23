<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            // WhatsApp (Twilio) defaults
            ['st_domain' => 'whatsapp', 'st_key' => 'twilio_sid',            'st_value' => ''],
            ['st_domain' => 'whatsapp', 'st_key' => 'twilio_auth_token',     'st_value' => ''],
            ['st_domain' => 'whatsapp', 'st_key' => 'twilio_whatsapp_from',  'st_value' => ''],
            ['st_domain' => 'whatsapp', 'st_key' => 'whatsapp_enabled',      'st_value' => 'false'],

            // Email (SMTP) defaults
            ['st_domain' => 'email', 'st_key' => 'mail_host',         'st_value' => 'smtp.gmail.com'],
            ['st_domain' => 'email', 'st_key' => 'mail_port',         'st_value' => '465'],
            ['st_domain' => 'email', 'st_key' => 'mail_username',     'st_value' => ''],
            ['st_domain' => 'email', 'st_key' => 'mail_password',     'st_value' => ''],
            ['st_domain' => 'email', 'st_key' => 'mail_encryption',   'st_value' => 'ssl'],
            ['st_domain' => 'email', 'st_key' => 'mail_from_address', 'st_value' => ''],
            ['st_domain' => 'email', 'st_key' => 'mail_from_name',    'st_value' => ''],
            ['st_domain' => 'email', 'st_key' => 'mail_enabled',      'st_value' => 'false'],
        ];

        foreach ($defaults as $setting) {
            Setting::firstOrCreate(
                ['st_domain' => $setting['st_domain'], 'st_key' => $setting['st_key']],
                ['st_value' => $setting['st_value']]
            );
        }
    }

    public function down(): void
    {
        Setting::where('st_domain', 'whatsapp')->delete();
        Setting::where('st_domain', 'email')->delete();
    }
};
