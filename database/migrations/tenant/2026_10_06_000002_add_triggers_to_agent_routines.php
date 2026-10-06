<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routines déclenchées par un événement interne d'O3 (produit créé, stock bas, facture confirmée…) en plus des
 * routines à heure fixe : `trigger` décrit l'événement attendu, `last_event_id` mémorise le dernier événement
 * déjà traité pour que chacun ne le soit qu'une fois.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_routines', function (Blueprint $table) {
            $table->json('trigger')->nullable()->after('schedule');          // {event_type, conditions, cooldown_minutes}
            $table->unsignedBigInteger('last_event_id')->nullable()->after('trigger');
        });
    }

    public function down(): void
    {
        Schema::table('agent_routines', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'last_event_id']);
        });
    }
};
