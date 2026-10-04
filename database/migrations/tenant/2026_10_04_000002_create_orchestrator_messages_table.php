<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversation entre un administrateur et l'orchestrateur des agents IA. Une
 * session par utilisateur : chaque administrateur ne voit que ses échanges.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('orchestrator_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 12);                       // admin | orchestrator
            $table->text('body');
            $table->json('meta')->nullable();                 // intention comprise, événement créé, liens proposés
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orchestrator_messages');
    }
};
