<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mouvements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            // Source document (nullable for manual adjustments)
            $table->foreignId('document_header_id')
                    ->nullable()
                    ->constrained('document_headers')
                    ->nullOnDelete();

            // Snapshots
            $table->string('document_reference')->nullable();   // FAC-2024-0001
            $table->string('document_type')->nullable();        // Invoice, DeliveryNote

            // Movement details
            $table->enum('direction', ['in', 'out']);
            $table->enum('reason', [
                'purchase',
                'sale',
                'return_in',
                'return_out',
                'transfer_in',
                'transfer_out',
                'adjustment_in',
                'adjustment_out',
                'loss',
                'initial',
            ]);

            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);

            // Stock snapshot before / after
            $table->decimal('stock_before', 10, 2)->default(0);
            $table->decimal('stock_after', 10, 2)->default(0);

            // Who did it
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mouvements');
    }
};
