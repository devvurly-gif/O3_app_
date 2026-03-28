<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('altContent')->nullable();
            $table->string('url');
            $table->boolean('isPrimary')->default(false);
            $table->foreignId('product_id')
                    ->constrained('products')
                    ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
