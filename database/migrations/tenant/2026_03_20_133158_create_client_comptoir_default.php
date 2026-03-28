<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Client Comptoir is now seeded via ThirdPartnerSeeder.
        // Kept as empty migration so existing installations don't break.
    }

    public function down(): void
    {
        //
    }
};
