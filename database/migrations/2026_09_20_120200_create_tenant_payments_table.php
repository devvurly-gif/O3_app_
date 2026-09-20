<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règlements d'abonnement encaissés par O3App auprès de ses tenants.
 *
 * Table CENTRALE : elle enregistre ce que les clients paient à O3App, à ne pas
 * confondre avec la table `payments` de chaque base tenant, qui enregistre ce
 * que les clients DU tenant paient à celui-ci.
 *
 * Au Maroc, sur ce segment, le règlement arrive par virement ou par chèque :
 * la saisie est manuelle et assumée. Cette table est ce qui la rend traçable —
 * et servira de base aux factures d'abonnement de la phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payments', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->string('plan', 30);
            $table->string('billing_period', 10);        // monthly | yearly
            $table->unsignedBigInteger('amount_cents');  // MAD HT, en centimes
            $table->date('paid_at');
            $table->date('period_starts_at');
            $table->date('period_ends_at');
            $table->string('method', 30)->default('virement'); // virement | cheque | especes | carte
            $table->string('reference')->nullable();     // n° de chèque, référence de virement
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable(); // utilisateur central ayant saisi

            $table->timestamps();

            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};
