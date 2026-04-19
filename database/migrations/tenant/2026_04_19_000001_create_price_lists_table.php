<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // "Détail", "Gros", "Revendeur"
            $table->string('description')->nullable();
            $table->enum('channel', ['all', 'pos', 'ecom'])
                  ->default('all');                           // canal de vente
            $table->boolean('is_default')->default(false);    // tarif par défaut pour clients sans grille
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);  // en cas de conflit, priority DESC
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
