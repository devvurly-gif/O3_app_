<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_ecom')->default(false)->after('p_status');
            $table->string('p_slug')->nullable()->unique()->after('is_ecom');
            $table->text('p_long_description')->nullable()->after('p_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_ecom', 'p_slug', 'p_long_description']);
        });
    }
};
