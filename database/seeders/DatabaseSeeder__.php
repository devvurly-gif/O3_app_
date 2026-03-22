<?php

namespace Database\Seeders;

use App\Models\DocumentIncrementor;
use App\Models\StructureIncrementor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Structure Incrementors ─────────────────────────────────
        $structure = StructureIncrementor::create([
            'si_title'     => 'Structure Principale',
            'si_model'     => 'Main',
            'si_template'  => 'MAIN-{NUM}',
            'si_nextTrick' => 1,
            'si_status'    => true,
        ]);

        // ── Document Incrementors ──────────────────────────────────
        DocumentIncrementor::insert([
            ['di_title' => 'Factures',          'di_model' => 'Invoice',       'di_domain' => 'main', 'template' => 'FAC-{YEAR}-{NUM}',  'nextTrick' => 1, 'operatorSens' => '+', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['di_title' => 'Bons de Commande',  'di_model' => 'PurchaseOrder', 'di_domain' => 'main', 'template' => 'BC-{YEAR}-{NUM}',   'nextTrick' => 1, 'operatorSens' => '+', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['di_title' => 'Bons de Livraison', 'di_model' => 'DeliveryNote',  'di_domain' => 'main', 'template' => 'BL-{YEAR}-{NUM}',   'nextTrick' => 1, 'operatorSens' => '+', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['di_title' => 'Avoirs',            'di_model' => 'CreditNote',    'di_domain' => 'main', 'template' => 'AV-{YEAR}-{NUM}',   'nextTrick' => 1, 'operatorSens' => '+', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['di_title' => 'Devis',             'di_model' => 'Quote',         'di_domain' => 'main', 'template' => 'DEV-{YEAR}-{NUM}',  'nextTrick' => 1, 'operatorSens' => '+', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['di_title' => 'Bons de Retour',    'di_model' => 'ReturnNote',    'di_domain' => 'main', 'template' => 'BR-{YEAR}-{NUM}',   'nextTrick' => 1, 'operatorSens' => '+', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Admin user ─────────────────────────────────────────────
        User::create([
            'name'         => 'Admin',
            'email'        => 'admin@o2app.ma',
            'password'     => Hash::make('password'),
            'role'         => 'admin',
            'is_active'    => true,
            'structure_id' => $structure->id,
        ]);
    }
}
