<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('p_title');
            $table->mediumText('p_description')->nullable();
            $table->string('p_sku')->unique();
            $table->string('p_ean13')->nullable();
            $table->decimal('p_purchasePrice', 15, 2)->default(0);
            $table->decimal('p_salePrice', 15, 2)->default(0);
            $table->decimal('p_cost', 15, 2)->default(0);
            $table->boolean('p_status')->default(true);
            $table->decimal('p_taxRate', 5, 2)->default(20);    // TVA %
            $table->string('p_unit')->default('pièce');          // kg, litre, pièce...

            $table->foreignId('category_id')
                    ->constrained('categories')
                    ->restrictOnDelete();

            $table->foreignId('brand_id')
                    ->nullable()
                    ->constrained('brands')
                    ->nullOnDelete();

            $table->foreignId('structure_id')
                    ->nullable()
                    ->constrained('structure_incrementors')
                    ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
