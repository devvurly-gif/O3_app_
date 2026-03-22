<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_has_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('stockLevel', 10, 2)->default(0);
            $table->timestamp('stockAtTime')->nullable();
            $table->decimal('wh_average', 15, 2)->default(0);  // weighted average cost

            // Who last updated stock
            $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

            $table->unique(['warehouse_id', 'product_id']);     // prevent duplicates
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_has_stock');
    }
};
