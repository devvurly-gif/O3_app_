<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donne une échéance à l'abonnement.
 *
 * Jusqu'ici `trial_ends_at` existait mais n'était lue par personne
 * (Tenant::isOnTrial() et isExpired() n'étaient appelées nulle part) : l'essai
 * de 14 jours ne se terminait jamais et aucun compte n'a jamais été invité à
 * payer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status', 20)->default('trial')->after('plan');
            $table->date('subscription_ends_at')->nullable()->after('trial_ends_at');

            // Le cron `subscriptions:check` balaie chaque nuit sur ces deux
            // colonnes ; l'index évite un scan complet quand le nombre de
            // tenants aura grandi.
            $table->index(['status', 'subscription_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['status', 'subscription_ends_at']);
            $table->dropColumn(['status', 'subscription_ends_at']);
        });
    }
};
