<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factures d'abonnement emises par O3App a ses tenants.
 *
 * Table CENTRALE. Ne pas confondre avec les `document_headers` de chaque base
 * tenant, qui portent les factures que le tenant emet a ses propres clients.
 *
 * Les montants sont en centimes de dirham, et l'identite des deux parties est
 * figee dans `snapshot` au moment de l'emission : une facture doit rester
 * identique a elle-meme meme si le tarif, l'adresse ou la raison sociale
 * changent par la suite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invoices', function (Blueprint $table) {
            $table->id();

            $table->string('number')->unique();          // FA-2026-0001
            $table->string('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->string('plan', 30);
            $table->string('billing_period', 10);        // monthly | yearly

            $table->date('issued_at');
            $table->date('due_at');
            $table->date('period_starts_at');
            $table->date('period_ends_at');

            $table->unsignedBigInteger('subtotal_cents');   // abonnement seul
            $table->unsignedBigInteger('setup_fee_cents')->default(0);
            $table->unsignedBigInteger('amount_ht_cents');  // subtotal + setup
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('amount_ttc_cents');

            // draft : emise mais pas encore envoyee. cancelled : annulee par un
            // avoir ou une erreur de saisie — jamais supprimee, la sequence des
            // numeros doit rester continue.
            $table->string('status', 20)->default('draft');

            $table->timestamp('sent_at')->nullable();
            $table->date('paid_at')->nullable();

            $table->foreignId('tenant_payment_id')->nullable()
                ->constrained('tenant_payments')
                ->nullOnDelete();

            $table->string('pdf_path')->nullable();
            $table->json('snapshot')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'issued_at']);
            $table->index(['status', 'due_at']);
            // Index et non contrainte d'unicite : le service refuse deja de
            // refacturer une periode deja couverte par une facture vivante,
            // mais apres une annulation il faut pouvoir en emettre une
            // corrigee sur la meme periode — ce qu'une contrainte interdirait.
            $table->index(['tenant_id', 'period_starts_at'], 'tenant_invoices_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invoices');
    }
};
