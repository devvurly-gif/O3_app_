<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\StructureIncrementor;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $structure = StructureIncrementor::where('si_model', 'Brand')->first();

        $brands = [
            ['br_title' => 'HP',        'br_status' => true],
            ['br_title' => 'Dell',      'br_status' => true],
            ['br_title' => 'Lenovo',    'br_status' => true],
            ['br_title' => 'Samsung',   'br_status' => true],
            ['br_title' => 'Canon',     'br_status' => true],
            ['br_title' => 'Epson',     'br_status' => true],
            ['br_title' => 'Logitech',  'br_status' => true],
            ['br_title' => 'TP-Link',   'br_status' => true],
            ['br_title' => 'Generic',   'br_status' => true],
        ];

        foreach ($brands as $brand) {
            $exists = Brand::where('br_title', $brand['br_title'])->exists();

            if (! $exists) {
                Brand::create(array_merge($brand, [
                    'br_code'      => $structure?->generateCode(),
                    'structure_id' => $structure?->id,
                ]));

                $structure?->refresh();
            }
        }
    }
}
