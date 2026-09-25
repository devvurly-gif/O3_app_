<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat « commande rapide » de la boutique : le client prouve qu'il possède le
 * numéro de sa fiche en saisissant un code reçu par SMS/WhatsApp, puis
 * commande sous une session courte. Codes et jetons ne sont stockés que hachés.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_chat_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30)->index();
            $table->unsignedBigInteger('third_partner_id');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('third_partner_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_chat_sessions');
        Schema::dropIfExists('customer_chat_codes');
    }
};
