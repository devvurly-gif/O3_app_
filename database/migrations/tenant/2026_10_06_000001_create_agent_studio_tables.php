<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atelier des agents : agents recrutés depuis le chat de l'orchestrateur, routines planifiées et consignes
 * permanentes. Tout est créé par l'administrateur (validation par clic) ; un agent recruté naît inactif.
 *
 *  - agents          : `kind` (builtin | custom), la mission en français et les domaines de données qu'il peut lire ;
 *  - agent_routines  : un enchaînement d'étapes connues (brouillons et lectures seulement) à heure fixe ;
 *  - agent_directives : des règles de la maison, lues par les agents recrutés et par les routines.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('kind', 12)->default('builtin')->after('name');   // builtin | custom
            $table->text('mission')->nullable()->after('kind');
            $table->json('scopes')->nullable()->after('mission');            // domaines de données lisibles (agents custom)
            $table->unsignedBigInteger('created_by')->nullable()->after('scopes');
        });

        Schema::create('agent_routines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->json('steps');                                  // ['encaissements', 'agent:3', …] : étapes connues seulement
            $table->json('schedule');                               // {frequency, weekday?, day?, time}
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('last_status', 12)->nullable();          // ok | partial | error
            $table->text('last_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_directives', function (Blueprint $table) {
            $table->id();
            $table->string('body', 400);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_directives');
        Schema::dropIfExists('agent_routines');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['kind', 'mission', 'scopes', 'created_by']);
        });
    }
};
