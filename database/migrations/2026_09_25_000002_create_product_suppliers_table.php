<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('third_partner_id')->constrained('third_partners')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();      // référence de l'article chez ce fournisseur
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->unsignedInteger('priority')->default(1);  // 1 = préféré ; départage à priorité égale par id croissant
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'third_partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
