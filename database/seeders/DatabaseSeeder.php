<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed order matters — respect FK dependencies.
     *
     * 1. Foundation (no FKs)
     * 2. Roles & Permissions (no FKs, needed by users)
     * 3. Users (needs structure_incrementors + roles)
     * 4. Catalog (needs structure_incrementors)
     * 5. Partners & Warehouses (needs structure_incrementors)
     * 6. Products (needs categories, brands, structure_incrementors)
     * 7. Stock (needs products, warehouses, users)
     * 8. Documents (needs everything above)
     * 9. Modules (standalone)
     */
    public function run(): void
    {
        $this->call([
            // ── 1. Foundation ─────────────────────────────────────
            StructureIncrementorSeeder::class,
            DocumentIncrementorSeeder::class,
            SettingSeeder::class,

            // ── 2. Roles & Permissions ──────────────────────────
            RolePermissionSeeder::class,

            // ── 3. Users ──────────────────────────────────────────
            UserSeeder::class,

            // ── 4. Catalog ────────────────────────────────────────
            CategorySeeder::class,
            BrandSeeder::class,

            // ── 5. Partners & Warehouses ──────────────────────────
            ThirdPartnerSeeder::class,
            WarehouseSeeder::class,

            // ── 6. Products ───────────────────────────────────────
            ProductSeeder::class,

            // ── 7. Stock ──────────────────────────────────────────
            WarehouseStockSeeder::class,

            // ── 8. Documents ──────────────────────────────────────
            DocumentSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('  o2_app seeded successfully!');
        $this->command->info('');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',      'admin@o2app.ma',     'Admin@1234'],
                ['Manager',    'manager@o2app.ma',   'Manager@1234'],
                ['Cashier',    'cashier@o2app.ma',   'Cashier@1234'],
                ['Warehouse',  'warehouse@o2app.ma', 'Warehouse@1234'],
            ]
        );
    }
}
