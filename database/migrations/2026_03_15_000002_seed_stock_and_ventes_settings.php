<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['st_domain' => 'stock', 'st_key' => 'autoriser_stock_negatif'],
            ['st_value' => 'false', 'st_type' => 'boolean']
        );

        Setting::firstOrCreate(
            ['st_domain' => 'ventes', 'st_key' => 'paiement_sur_bl'],
            ['st_value' => 'false', 'st_type' => 'boolean']
        );
    }

    public function down(): void
    {
        Setting::where('st_domain', 'stock')->where('st_key', 'autoriser_stock_negatif')->delete();
        Setting::where('st_domain', 'ventes')->where('st_key', 'paiement_sur_bl')->delete();
    }
};
