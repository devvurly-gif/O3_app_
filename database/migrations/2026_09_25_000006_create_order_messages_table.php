<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal de la messagerie commandes : chaque message reçu (WhatsApp, SMS,
 * chat web) et chaque réponse envoyée. Sert de fil de conversation dans la
 * page « Messagerie » et d'idempotence pour les webhooks (provider_message_id).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);                 // whatsapp | sms | web_staff | web_client
            $table->string('direction', 3);                // in | out
            $table->string('phone', 30)->nullable()->index();
            $table->unsignedBigInteger('third_partner_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('body');
            $table->string('provider_message_id', 64)->nullable()->unique();
            $table->string('parse_method', 10)->nullable(); // rules | ai | none
            $table->string('status', 20)->nullable();       // created | rejected | ignored | error | sent | failed
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
