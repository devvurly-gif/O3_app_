<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter warehouse_dest_id pour les transferts de stock
        Schema::table('document_headers', function (Blueprint $table) {
            $table->foreignId('warehouse_dest_id')
                  ->nullable()
                  ->after('warehouse_id')
                  ->constrained('warehouses')
                  ->nullOnDelete();
        });

        // 2. Étendre l'enum status pour ajouter 'applied'
        DB::statement("ALTER TABLE document_headers MODIFY COLUMN status ENUM(
            'draft',
            'confirmed',
            'sent',
            'delivered',
            'received',
            'pending',
            'paid',
            'partial',
            'cancelled',
            'converted',
            'applied'
        ) DEFAULT 'draft'");

        // 3. Étendre l'enum reason des mouvements pour les docs stock
        DB::statement("ALTER TABLE stock_mouvements MODIFY COLUMN reason ENUM(
            'purchase','sale','return_in','return_out',
            'transfer_in','transfer_out',
            'adjustment_in','adjustment_out',
            'loss','initial',
            'manual_entry','manual_exit','inventory_adjustment',
            'purchase_receipt','sale_delivery',
            'stock_entry','stock_exit','stock_adjustment','stock_transfer_in','stock_transfer_out'
        ) NOT NULL");
    }

    public function down(): void
    {
        Schema::table('document_headers', function (Blueprint $table) {
            $table->dropForeign(['warehouse_dest_id']);
            $table->dropColumn('warehouse_dest_id');
        });

        DB::statement("ALTER TABLE document_headers MODIFY COLUMN status ENUM(
            'draft',
            'confirmed',
            'sent',
            'delivered',
            'received',
            'pending',
            'paid',
            'partial',
            'cancelled',
            'converted'
        ) DEFAULT 'draft'");

        DB::statement("ALTER TABLE stock_mouvements MODIFY COLUMN reason ENUM(
            'purchase','sale','return_in','return_out',
            'transfer_in','transfer_out',
            'adjustment_in','adjustment_out',
            'loss','initial',
            'manual_entry','manual_exit','inventory_adjustment',
            'purchase_receipt','sale_delivery'
        ) NOT NULL");
    }
};
