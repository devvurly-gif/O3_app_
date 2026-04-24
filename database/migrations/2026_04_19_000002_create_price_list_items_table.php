<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')
                  ->constrained('price_lists')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->decimal('price_ht', 15, 2);               // prix HT
            $table->decimal('price_ttc', 15, 2);              // prix TTC (calculé via tax_rate produit)
            $table->unsignedInteger('min_qty')->default(1);   // quantité minimale pour ce tarif (dégressif)
            $table->date('valid_from')->nullable();           // début de validité
            $table->date('valid_to')->nullable();             // fin de validité
            $table->timestamps();

            // Un produit peut avoir plusieurs lignes dans une même grille (palier de qty)
            $table->index(['price_list_id', 'product_id', 'min_qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
