<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'idempotence de l'import API des achats (agent de saisie Jadema).
 * Une ligne par ID registre (external_id, ex. FA-2026-0001).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_imports', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 40)->unique();      // ID registre : FA-2026-0001
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
        Schema::dropIfExists('purchase_imports');
    }
};
