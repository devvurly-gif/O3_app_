<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_header_id')
                    ->constrained('document_headers')
                    ->cascadeOnDelete();

            $table->foreignId('product_id')
                    ->nullable()
                    ->constrained('products')
                    ->nullOnDelete();

            $table->integer('sort_order')->default(0);

            $table->enum('line_type', [
                'product',
                'comment',
                'discount',
            ])->default('product');

            // Snapshots from product at time of document creation
            $table->string('designation');
            $table->string('reference')->nullable();

            // Quantities & pricing
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->nullable();                     // pièce, kg, litre...
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(20);     // TVA

            // Computed totals (stored for performance)
            $table->decimal('total_ligne_ht', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);

            $table->enum('status', ['active', 'cancelled'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_lignes');
    }
};
