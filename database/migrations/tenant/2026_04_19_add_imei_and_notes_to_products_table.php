<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('p_imei', 50)->nullable()->after('p_ean13');
            $table->text('p_notes')->nullable()->after('p_slug');
            $table->index('p_imei');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['p_imei']);
            $table->dropColumn(['p_imei', 'p_notes']);
        });
    }
};
