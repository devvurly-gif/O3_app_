<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variant_option_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');        // "Couleur", "Taille", "Matière"
            $table->string('slug')->unique(); // "color", "size", "material"
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('variant_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_option_type_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('key');         // "blanc", "small"
            $table->string('value');       // "#FFFFFF", "S"
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('variant_option_types');
    }
};
