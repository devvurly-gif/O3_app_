<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_footers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_header_id')
                    ->unique()
                    ->constrained('document_headers')
                    ->cascadeOnDelete();

            // ── Totals ────────────────────────────────────────────
            $table->decimal('total_ht', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_ttc', 15, 2)->default(0);

            // ── Payment ───────────────────────────────────────────
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'cheque',
                'effet',
                'credit',
            ])->nullable();
            $table->date('payment_date')->nullable();

            // ── Moroccan fiscal ───────────────────────────────────
            $table->string('total_in_words')->nullable();  // "Mille deux cents dirhams"

            // ── Signature / Stamp ─────────────────────────────────
            $table->boolean('is_signed')->default(false);
            $table->string('stamp_path')->nullable();

            // ── Print / Send tracking ─────────────────────────────
            $table->boolean('is_printed')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->json('sent_via')->nullable();   // {"email":"...", "whatsapp":"..."}

            // ── Legal / Bank ──────────────────────────────────────
            $table->text('bank_details')->nullable();
            $table->text('legal_mentions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_footers');
    }
};
