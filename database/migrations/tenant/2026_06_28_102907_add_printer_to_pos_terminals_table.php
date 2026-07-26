<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->string('printer_name', 150)->nullable()->after('is_active');
            $table->boolean('auto_print')->default(true)->after('printer_name');
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->dropColumn(['printer_name', 'auto_print']);
        });
    }
};
