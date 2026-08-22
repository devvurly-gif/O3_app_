<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendu de monnaie au comptoir.
 *
 * Jusqu'ici, régler un ticket de 170 avec un billet de 200 enregistrait un
 * paiement de 200. Le tiroir ne gardait pourtant que 170, et la clôture de
 * caisse — qui somme les paiements en espèces — attendait 30 MAD de trop.
 *
 * Les paiements enregistrent désormais le net encaissé, et l'écart rendu au
 * client vit ici. L'espèce reçue reste calculable : paiement + rendu.
 *
 * Nullable : un ticket sans monnaie rendue, ou tout document hors caisse,
 * n'a rien à y stocker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_footers', function (Blueprint $table) {
            $table->decimal('change_given', 15, 2)->nullable()->after('amount_due');
        });
    }

    public function down(): void
    {
        Schema::table('document_footers', function (Blueprint $table) {
            $table->dropColumn('change_given');
        });
    }
};
