<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIN de commande par message (WhatsApp / SMS) : avoir le téléphone du client
 * ne suffit pas, il doit aussi donner le PIN reçu à la création de sa fiche.
 * Seul le haché est stocké ; le PIN en clair n'est affiché qu'une fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->string('order_pin_hash')->nullable();
            $table->timestamp('order_pin_set_at')->nullable();
            $table->unsignedTinyInteger('order_pin_failures')->default(0);
            $table->timestamp('order_pin_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('third_partners', function (Blueprint $table) {
            $table->dropColumn(['order_pin_hash', 'order_pin_set_at', 'order_pin_failures', 'order_pin_locked_at']);
        });
    }
};
