<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add variant_id FK
        if (!Schema::hasColumn('warehouse_has_stock', 'variant_id')) {
            Schema::table('warehouse_has_stock', function (Blueprint $table) {
                $table->foreignId('variant_id')
                      ->nullable()
                      ->after('product_id')
                      ->constrained('product_variants')
                      ->nullOnDelete();
            });
        }

        // Add standalone index so MySQL doesn't rely on the unique for the warehouse FK
        $indexes = DB::select("SHOW INDEX FROM warehouse_has_stock WHERE Key_name = 'idx_whs_warehouse_id'");
        if (empty($indexes)) {
            DB::statement('ALTER TABLE warehouse_has_stock ADD INDEX idx_whs_warehouse_id (warehouse_id)');
        }

        // Drop old unique (warehouse_id, product_id)
        try {
            DB::statement('ALTER TABLE warehouse_has_stock DROP INDEX warehouse_has_stock_warehouse_id_product_id_unique');
        } catch (\Throwable) {}

        // Add new composite unique (warehouse_id, product_id, variant_id)
        try {
            DB::statement('ALTER TABLE warehouse_has_stock ADD UNIQUE KEY whs_warehouse_product_variant_unique (warehouse_id, product_id, variant_id)');
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE warehouse_has_stock DROP INDEX whs_warehouse_product_variant_unique');
        DB::statement('ALTER TABLE warehouse_has_stock DROP INDEX idx_whs_warehouse_id');
        DB::statement('ALTER TABLE warehouse_has_stock ADD UNIQUE KEY warehouse_has_stock_warehouse_id_product_id_unique (warehouse_id, product_id)');

        Schema::table('warehouse_has_stock', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn('variant_id');
        });
    }
};
