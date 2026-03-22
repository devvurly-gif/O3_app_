<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\StructureIncrementor;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $structure = StructureIncrementor::where('si_model', 'Category')->first();

        $categories = [
            ['ctg_title' => 'Informatique',         'ctg_status' => true],
            ['ctg_title' => 'Bureautique',           'ctg_status' => true],
            ['ctg_title' => 'Électronique',          'ctg_status' => true],
            ['ctg_title' => 'Fournitures de Bureau', 'ctg_status' => true],
            ['ctg_title' => 'Mobilier',              'ctg_status' => true],
            ['ctg_title' => 'Consommables',          'ctg_status' => true],
            ['ctg_title' => 'Réseau & Télécom',      'ctg_status' => true],
            ['ctg_title' => 'Sécurité',              'ctg_status' => false],
        ];

        foreach ($categories as $category) {
            $exists = Category::where('ctg_title', $category['ctg_title'])->exists();

            if (! $exists) {
                Category::create(array_merge($category, [
                    'ctg_code'     => $structure?->generateCode(),
                    'structure_id' => $structure?->id,
                ]));

                $structure?->refresh();
            }
        }
    }
}
