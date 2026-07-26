<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');              // "Rouge / XL" — affiché UI/documents
            $table->json('attributes');           // {"color":"Rouge","size":"XL"}
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->nullable(); // null = hérite du produit
            $table->decimal('stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
