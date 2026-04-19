<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            Module::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'pos'],
                [
                    'display_name' => 'Point de Vente (POS)',
                    'description'  => 'Module caisse avec interface tactile, sessions, tickets et impression.',
                    'is_active'    => false,
                ],
            );
        }
    }
}
