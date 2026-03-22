<?php

namespace Database\Seeders;

use App\Models\StructureIncrementor;
use Illuminate\Database\Seeder;

class StructureIncrementorSeeder extends Seeder
{
    public function run(): void
    {
        $structures = [
            [
                'si_title'     => 'Structure Principale',
                'si_model'     => 'Main',
                'si_template'  => 'MAIN-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Produits',
                'si_model'     => 'Product',
                'si_template'  => 'PRD-{00000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Catégories',
                'si_model'     => 'Category',
                'si_template'  => 'CAT-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Marques',
                'si_model'     => 'Brand',
                'si_template'  => 'BRD-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Entrepôts',
                'si_model'     => 'Warehouse',
                'si_template'  => 'WH-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Clients',
                'si_model'     => 'Customer',
                'si_template'  => 'CLT-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Fournisseurs',
                'si_model'     => 'Supplier',
                'si_template'  => 'FRN-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Utilisateurs',
                'si_model'     => 'User',
                'si_template'  => 'USR-{000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
            [
                'si_title'     => 'Structure Paiements',
                'si_model'     => 'Payment',
                'si_template'  => 'PAY-{00000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
            ],
        ];

        foreach ($structures as $structure) {
            StructureIncrementor::firstOrCreate(
                ['si_model' => $structure['si_model']],
                $structure
            );
        }
    }
}
