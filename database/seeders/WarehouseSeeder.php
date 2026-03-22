<?php

namespace Database\Seeders;

use App\Models\StructureIncrementor;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $structure = StructureIncrementor::where('si_model', 'Warehouse')->first();

        $warehouses = [
            ['wh_title' => 'Entrepôt Principal',  'wh_status' => true],
            ['wh_title' => 'Entrepôt Casablanca', 'wh_status' => true],
            ['wh_title' => 'Entrepôt Rabat',      'wh_status' => true],
            ['wh_title' => 'Stock Défectueux',    'wh_status' => false],
        ];

        foreach ($warehouses as $warehouse) {
            $exists = Warehouse::where('wh_title', $warehouse['wh_title'])->exists();

            if (! $exists) {
                Warehouse::create(array_merge($warehouse, [
                    'wh_code'      => $structure?->generateCode(),
                    'structure_id' => $structure?->id,
                ]));

                $structure?->refresh();
            }
        }
    }
}
