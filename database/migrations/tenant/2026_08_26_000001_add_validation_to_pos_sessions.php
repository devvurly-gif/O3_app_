<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validation d'une session de caisse par un responsable.
 *
 * Fermer sa caisse et valider l'écart sont deux gestes différents, faits par
 * deux personnes différentes : le caissier compte et ferme, le responsable
 * contrôle et endosse. Sans cette séparation, celui qui constate le manque est
 * aussi celui qui le justifie.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->timestamp('validated_at')->nullable()->after('cash_difference');
            $table->foreignId('validated_by')->nullable()->after('validated_at')
                  ->constrained('users')->nullOnDelete();
            // Le procès-verbal : ce qui explique l'écart. Obligatoire dès que
            // la caisse ne tombe pas juste.
            $table->text('variance_reason')->nullable()->after('validated_by');
            // L'écriture de trésorerie qui porte l'écart, pour remonter du
            // journal à la session et inversement.
            $table->foreignId('variance_transaction_id')->nullable()->after('variance_reason')
                  ->constrained('cash_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variance_transaction_id');
            $table->dropColumn('variance_reason');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn('validated_at');
        });
    }
};
