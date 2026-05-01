<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a publish-to-store flag on brands and categories.
 *
 * Mirrors the existing `is_ecom` flag on `products`. Default = true so
 * the introduction is non-breaking: every existing brand/category stays
 * visible in the storefront. Merchants can then opt-out individual rows
 * (e.g. an internal "Reconditionné B2B" category) without having to
 * unpublish each product underneath.
 *
 * Filtering wiring lives in EcomCatalogueController (joins on these flags).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('is_ecom')->default(true)->after('br_status');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_ecom')->default(true)->after('ctg_status');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('is_ecom');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_ecom');
        });
    }
};
