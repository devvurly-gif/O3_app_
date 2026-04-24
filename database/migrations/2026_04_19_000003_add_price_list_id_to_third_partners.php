<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->foreignId('price_list_id')
                  ->nullable()
                  ->after('structure_id')
                  ->constrained('price_lists')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn('price_list_id');
        });
    }
};
