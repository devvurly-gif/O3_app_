<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_headers', function (Blueprint $table) {
            $table->id();

            // ── Document type (from DocumentIncrementor) ──────────
            $table->foreignId('document_incrementor_id')
                    ->constrained('document_incrementors')
                    ->restrictOnDelete();

            // Snapshots (no join needed for display / filtering)
            $table->string('reference')->unique();      // FAC-2024-0001
            $table->string('document_type');            // Invoice, DeliveryNote...
            $table->string('document_title');           // Factures, Bons de Livraison...

            // ── Document chain (conversion BL → FAC etc.) ─────────
            $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('document_headers')
                    ->nullOnDelete();

            // ── Third party ───────────────────────────────────────
            $table->foreignId('thirdPartner_id')
                    ->constrained('third_partners')
                    ->restrictOnDelete();

            $table->enum('company_role', ['customer', 'supplier']);

            // ── Relations ─────────────────────────────────────────
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('warehouse_id')
                    ->nullable()
                    ->constrained('warehouses')
                    ->nullOnDelete();

            // ── Status ────────────────────────────────────────────
            $table->enum('status', [
                'draft',
                'confirmed',
                'sent',
                'paid',
                'partial',
                'cancelled',
                'converted',
            ])->default('draft');

            // ── Dates ─────────────────────────────────────────────
            $table->date('issued_at');
            $table->date('due_at')->nullable();

            // ── Extra ─────────────────────────────────────────────
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_headers');
    }
};
