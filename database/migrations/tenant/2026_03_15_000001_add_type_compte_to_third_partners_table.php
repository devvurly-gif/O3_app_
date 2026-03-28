<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->enum('type_compte', ['normal', 'en_compte'])
                  ->default('normal')
                  ->after('seuil_credit');

            $table->enum('frequence_facturation', ['mensuelle', 'trimestrielle', 'semestrielle'])
                  ->nullable()
                  ->after('type_compte');
        });
    }

    public function down(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->dropColumn(['type_compte', 'frequence_facturation']);
        });
    }
};
