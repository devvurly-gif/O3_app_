<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relances de paiement préparées par l'agent Recouvrement. Un brouillon par
 * facture et par niveau (unique) : rien n'est envoyé tant qu'un humain ne l'a pas
 * validé, et une relance déjà préparée n'est jamais recréée.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_header_id');
            $table->unsignedBigInteger('third_partner_id')->nullable()->index();
            $table->unsignedTinyInteger('level');                       // 1 courtois, 2 ferme, 3 escalade humaine
            $table->string('channel', 12)->default('whatsapp');         // whatsapp | manual
            $table->text('message');
            $table->decimal('amount_due', 14, 2);
            $table->unsignedSmallInteger('days_overdue');
            $table->string('status', 12)->default('draft')->index();    // draft | sent | failed | rejected
            $table->string('error', 255)->nullable();
            $table->string('reason', 255)->nullable();                  // motif d'un rejet
            $table->unsignedBigInteger('event_id')->nullable()->index(); // événement de l'agent qui l'a préparée
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['document_header_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
