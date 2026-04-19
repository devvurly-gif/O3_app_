<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StructureIncrementor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $structure = StructureIncrementor::where('si_model', 'User')->first();

        // Get tenant ID from container if in tenant context
        $tenantId = null;
        try {
            $tenantId = app(\Stancl\Tenancy\Contracts\Tenant::class)->getTenantKey();
        } catch (\Exception $e) {
            // Not in tenant context, use fallback
        }

        $users = [];

        // Create default admin user for tenant
        if ($tenantId) {
            $users[] = [
                'name' => 'Admin',
                'email' => "admin@{$tenantId}.ma",
                'password' => "{$tenantId}@1234",
                'role' => 'admin'
            ];
        } else {
            // Fallback for central database
            $users = [
                ['name' => 'Admin',     'email' => 'admin@o2app.ma',     'password' => 'Admin@1234',     'role' => 'admin'],
                ['name' => 'Manager',   'email' => 'manager@o2app.ma',   'password' => 'Manager@1234',   'role' => 'manager'],
                ['name' => 'Cashier',   'email' => 'cashier@o2app.ma',   'password' => 'Cashier@1234',   'role' => 'cashier'],
                ['name' => 'Warehouse', 'email' => 'warehouse@o2app.ma', 'password' => 'Warehouse@1234', 'role' => 'warehouse'],
            ];
        }

        foreach ($users as $userData) {
            if (User::where('email', $userData['email'])->exists()) {
                continue;
            }

            $role = Role::where('name', $userData['role'])->first();

            User::create([
                'name'         => $userData['name'],
                'email'        => $userData['email'],
                'password'     => Hash::make($userData['password']),
                'role_id'      => $role?->id,
                'is_active'    => true,
                'user_code'    => $structure?->generateCode(),
                'structure_id' => $structure?->id,
            ]);

            $structure?->refresh();
        }
    }
}
