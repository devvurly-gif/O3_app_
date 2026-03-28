<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('wh_title');
            $table->string('wh_code')->unique();
            $table->boolean('wh_status')->default(true);
            $table->foreignId('structure_id')
                    ->nullable()
                    ->constrained('structure_incrementors')
                    ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
