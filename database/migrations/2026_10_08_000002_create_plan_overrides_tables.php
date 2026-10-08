<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des formules modifiable depuis la gestion des tenants.
 *
 * `config/plans.php` reste la source par défaut ; `plan_overrides` ne porte que ce que le super-administrateur a changé
 * (prix, contenu, quotas…), fusionné au catalogue au démarrage (PlanCatalog). `plan_changes` garde l'historique : qui a
 * changé quoi, quand, avant et après. Aucune facture déjà émise n'est touchée : elles portent leur propre copie de la formule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);                       // plan | addon
            $table->string('item_key', 40);                   // essentiel | pro | business | extra_user…
            $table->json('data');                             // seulement les champs modifiables, tels qu'enregistrés
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'item_key']);
        });

        Schema::create('plan_changes', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('item_key', 40);
            $table->string('action', 12);                     // update | reset
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 120)->nullable();
            $table->json('before');
            $table->json('after');
            $table->unsignedInteger('tenants_concerned')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['kind', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_changes');
        Schema::dropIfExists('plan_overrides');
    }
};
