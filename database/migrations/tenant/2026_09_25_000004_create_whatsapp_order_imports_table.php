<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'idempotence de l'import des commandes WhatsApp (agent de
 * facturation client). Une ligne par ID registre (external_id, ex.
 * CMD-2026-0001) — meme structure que purchase_imports, cote ventes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_order_imports', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 40)->unique();
            $table->string('status', 20);                      // created | rejected
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->json('response');
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->string('document_reference', 40)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_order_imports');
    }
};
