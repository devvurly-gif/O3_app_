<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_mouvements MODIFY COLUMN reason ENUM(
            'purchase','sale','return_in','return_out',
            'transfer_in','transfer_out',
            'adjustment_in','adjustment_out',
            'loss','initial',
            'manual_entry','manual_exit','inventory_adjustment',
            'purchase_receipt','sale_delivery',
            'stock_entry','stock_exit','stock_adjustment',
            'stock_transfer_out','stock_transfer_in'
        ) NOT NULL");
    }
};
