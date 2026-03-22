<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\StructureIncrementor;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_incrementors', function (Blueprint $table) {
            $table->id();
            $table->string('si_title');
            $table->string('si_model');
            $table->string('si_template');
            $table->integer('si_nextTrick')->default(1);
            $table->boolean('si_status')->default(true);
            $table->timestamps();
        });

        StructureIncrementor::insert([
            ['si_title' => 'Structure Principale',    'si_model' => 'Main',         'si_template' => 'MAIN-{000}',    'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Produits',      'si_model' => 'Product',      'si_template' => 'PRD-{00000}',   'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Catégories',    'si_model' => 'Category',     'si_template' => 'CAT-{000}',     'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Marques',       'si_model' => 'Brand',        'si_template' => 'BRD-{000}',     'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Entrepôts',     'si_model' => 'Warehouse',    'si_template' => 'WH-{000}',      'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Clients',       'si_model' => 'Customer',     'si_template' => 'CLT-{000}',     'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Fournisseurs',  'si_model' => 'Supplier',     'si_template' => 'FRN-{000}',     'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Utilisateurs',  'si_model' => 'User',         'si_template' => 'USR-{000}',     'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['si_title' => 'Structure Paiements',     'si_model' => 'Payment',      'si_template' => 'PAY-{00000}',   'si_nextTrick' => 1, 'si_status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_incrementors');
    }
};
