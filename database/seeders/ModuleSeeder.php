<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        Module::updateOrCreate(
            ['name' => 'pos'],
            [
                'display_name' => 'Point de Vente (POS)',
                'description'  => 'Module caisse avec interface tactile, sessions, tickets et impression.',
                'is_active'    => false,
            ],
        );
    }
}
