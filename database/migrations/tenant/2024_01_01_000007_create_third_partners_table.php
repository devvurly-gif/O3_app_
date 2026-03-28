<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_partners', function (Blueprint $table) {
            $table->id();
            $table->string('tp_title');
            $table->string('tp_Ice_Number')->nullable();
            $table->string('tp_Rc_Number')->nullable();
            $table->string('tp_patente_Number')->nullable();
            $table->string('tp_IdenFiscal')->nullable();
            $table->enum('tp_Role', ['customer', 'supplier', 'both'])->default('customer');
            $table->boolean('tp_status')->default(true);

            // Phone / address
            $table->string('tp_phone')->nullable();
            $table->string('tp_email')->nullable();
            $table->text('tp_address')->nullable();
            $table->string('tp_city')->nullable();

            $table->foreignId('structure_id')
                    ->nullable()
                    ->constrained('structure_incrementors')
                    ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_partners');
    }
};
