<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image');
            $table->string('button_text')->nullable();
            $table->enum('link_type', ['promotion', 'category', 'product', 'url', 'none'])->default('none');
            $table->unsignedBigInteger('link_id')->nullable();
            $table->string('link_url')->nullable();
            $table->enum('position', ['hero', 'sidebar', 'popup'])->default('hero');
            $table->integer('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
