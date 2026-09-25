<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Téléphone d'un utilisateur O3 : permet à la messagerie de reconnaître un
 * membre de l'équipe (vous, un livreur) qui envoie un BL par SMS/WhatsApp.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'phone')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
