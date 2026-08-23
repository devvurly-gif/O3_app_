<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace d'une remise accordée au comptoir.
 *
 * Le POS acceptait le prix envoyé par le poste sans jamais le recalculer :
 * un caissier pouvait vendre à n'importe quel prix, sans que rien ne le
 * signale. Le tarif est désormais résolu côté serveur, et s'en écarter demande
 * la permission `pos.override_price`.
 *
 * Quand l'écart est autorisé, il faut pouvoir le relire : cette colonne garde
 * le tarif qui aurait dû s'appliquer. `unit_price` porte le prix pratiqué, la
 * différence entre les deux est la remise réellement consentie.
 *
 * Nullable et renseignée seulement en cas d'écart : une vente au tarif ne
 * laisse rien, et toutes les lignes existantes restent valides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_lignes', function (Blueprint $table) {
            $table->decimal('reference_price', 15, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('document_lignes', function (Blueprint $table) {
            $table->dropColumn('reference_price');
        });
    }
};
