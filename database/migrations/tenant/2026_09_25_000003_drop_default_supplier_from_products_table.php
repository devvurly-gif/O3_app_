<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplacé par la table product_suppliers (plusieurs fournisseurs par produit,
 * avec priorité et prix chacun) — un produit s'achète souvent chez plusieurs
 * fournisseurs, ce qu'une simple colonne default_supplier_id ne pouvait pas
 * représenter. Aucune donnée réelle n'avait encore été saisie sur cette
 * colonne (déployée le 2026-09-25, quelques minutes avant ce correctif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('default_supplier_id')
                ->nullable()
                ->after('brand_id')
                ->constrained('third_partners')
                ->nullOnDelete();
        });
    }
};
