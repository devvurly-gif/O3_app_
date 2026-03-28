<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->decimal('encours_actuel', 15, 2)->default(0)->after('tp_city');
            $table->decimal('seuil_credit', 15, 2)->default(0)->after('encours_actuel');
        });
    }

    public function down(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->dropColumn(['encours_actuel', 'seuil_credit']);
        });
    }
};
