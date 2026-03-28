<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_header_id')
                    ->constrained('document_headers')
                    ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->enum('method', [
                'cash',
                'bank_transfer',
                'cheque',
                'effet',
                'credit',
            ]);
            $table->date('paid_at');
            $table->string('reference')->nullable();    // cheque number, transfer ref...
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
