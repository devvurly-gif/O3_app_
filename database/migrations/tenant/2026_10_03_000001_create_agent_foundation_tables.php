<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socle des agents IA (voir la spécification « Spécification du socle des agents IA »).
 *
 * Routeur à règles + dossier d'affaire + journal d'audit + file de validation
 * humaine + seuils. Les tables d'import existantes (purchase_imports,
 * whatsapp_order_imports) et order_messages restent inchangées : agent_events
 * et agent_actions s'y rattachent par référence, sans les remplacer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 30)->unique();           // achats | ventes | stocks | expedition | recouvrement | comptabilite | marketing
            $table->string('name', 80);
            $table->unsignedBigInteger('user_id')->nullable(); // compte technique Sanctum
            $table->string('ability', 60)->nullable();         // ex. achats:import
            $table->string('default_level', 12)->default('approval'); // auto | approval | blocked
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('third_partner_id')->nullable()->index();
            $table->string('status', 12)->default('open')->index(); // open | closed
            $table->json('refs')->nullable();                  // commande, devis, facture, achat liés
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->nullable()->index();
            $table->string('source', 20);                      // email | pdf | whatsapp | bank | erp | agent | manual
            $table->json('payload')->nullable();
            $table->json('entities')->nullable();              // client, fournisseur, n° commande, montant
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('parent_event_id')->nullable()->index();
            $table->unsignedBigInteger('order_message_id')->nullable()->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('status', 12)->default('new')->index(); // new | routed | in_progress | done | to_sort | error
            $table->timestamps();
        });

        Schema::create('agent_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 40);
            $table->json('conditions')->nullable();            // {source, keywords[], mime}
            $table->string('agent_domain', 30);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('phase')->default(1);
            $table->timestamps();
        });

        Schema::create('agent_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->string('action', 60);
            $table->string('level', 12);                       // auto | approval | blocked
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->timestamp('created_at')->nullable();       // journal en ajout seul : pas d'updated_at
        });

        Schema::create('agent_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('action_id')->index();
            $table->json('proposal');
            $table->string('decision', 12)->nullable()->index(); // null = en attente | approved | modified | rejected
            $table->json('modifications')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('agent_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('agent_domain', 30);
            $table->string('action_type', 60);
            $table->string('parameter', 40);                   // amount | discount_pct | margin_pct | price_gap_pct | confidence | reminder_level
            $table->decimal('value', 14, 2)->nullable();       // null = « à définir »
            $table->string('unit', 10)->nullable();            // MAD | %
            $table->timestamps();
            $table->unique(['agent_domain', 'action_type', 'parameter'], 'agent_thresholds_unique');
        });
    }

    public function down(): void
    {
        foreach (['agent_thresholds', 'agent_approvals', 'agent_actions', 'agent_routing_rules', 'agent_events', 'agent_cases', 'agents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
