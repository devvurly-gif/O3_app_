<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add 'cancellation' to the reason ENUM
        DB::statement("ALTER TABLE stock_mouvements MODIFY COLUMN reason ENUM(
            'purchase','sale','return_in','return_out',
            'transfer_in','transfer_out',
            'adjustment_in','adjustment_out',
            'loss','initial',
            'manual_entry','manual_exit','inventory_adjustment',
            'purchase_receipt','sale_delivery',
            'stock_entry','stock_exit','stock_adjustment',
            'stock_transfer_out','stock_transfer_in',
            'pos_sale','pos_void',
            'cancellation'
        ) NOT NULL");

        // 2. Add status column (pending = not yet applied to stock, applied = stock updated, cancelled = voided)
        Schema::table('stock_mouvements', function (Blueprint $table) {
            $table->enum('status', ['pending', 'applied', 'cancelled'])
                  ->default('applied')
                  ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('stock_mouvements', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        DB::statement("ALTER TABLE stock_mouvements MODIFY COLUMN reason ENUM(
            'purchase','sale','return_in','return_out',
            'transfer_in','transfer_out',
            'adjustment_in','adjustment_out',
            'loss','initial',
            'manual_entry','manual_exit','inventory_adjustment',
            'purchase_receipt','sale_delivery',
            'stock_entry','stock_exit','stock_adjustment',
            'stock_transfer_out','stock_transfer_in',
            'pos_sale','pos_void'
        ) NOT NULL");
    }
};
