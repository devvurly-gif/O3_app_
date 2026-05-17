<?php
/**
 * One-shot script to create a "demo" tenant on o3app.ma.
 * Run: php create-demo-tenant.php
 * Then delete this file.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;

// Check if demo tenant already exists
$existing = Tenant::find('demo');
if ($existing) {
    echo "Demo tenant already exists. Running seed fix...\n";
    $existing->run(function () {
        // Fix: seed brand + any missing data
        if (\App\Models\Brand::count() === 0) {
            \App\Models\Brand::create(['br_title' => 'Générique', 'br_code' => 'GEN']);
            echo "Brand seeded.\n";
        }
    });
    echo "\n✅ Demo tenant ready!\n";
    echo "URL:      https://demo.o3app.ma\n";
    echo "Email:    demo@o3app.ma\n";
    echo "Password: demo1234\n";
    exit(0);
}

echo "Creating demo tenant...\n";

$tenant = Tenant::create([
    'id'            => 'demo',
    'name'          => 'Demo O3 App',
    'email'         => 'demo@o3app.ma',
    'plan'          => 'enterprise',
    'trial_ends_at' => now()->addYears(10),
]);

$tenant->pos_enabled = true;
$tenant->paiement_bl_enabled = true;
$tenant->ecom_enabled = true;
$tenant->ecom_api_key = 'ecom_' . bin2hex(random_bytes(20));
$tenant->is_active = true;
$tenant->save();

$tenant->domains()->create([
    'domain' => 'demo.o3app.ma',
]);

echo "Domain: demo.o3app.ma\n";

// Seed the tenant database
$tenant->run(function () use ($tenant) {
    // Roles & permissions
    (new \Database\Seeders\RolePermissionSeeder())->run();

    // Create demo admin user
    $role = \App\Models\Role::where('name', 'admin')->first();
    \App\Models\User::create([
        'name'      => 'Admin Demo',
        'email'     => 'demo@o3app.ma',
        'password'  => bcrypt('demo1234'),
        'role_id'   => $role->id,
        'is_active' => true,
    ]);

    // Settings
    (new \Database\Seeders\SettingSeeder())->run();
    \App\Models\Setting::set('general', 'company_name', 'Demo O3 App');
    \App\Models\Setting::set('general', 'currency', 'MAD');
    \App\Models\Setting::set('general', 'tax_rate', '20');
    \App\Models\Setting::set('company', 'name', 'Demo O3 App');
    \App\Models\Setting::set('company', 'email', 'demo@o3app.ma');
    \App\Models\Setting::set('ventes', 'paiement_sur_bl', 'true');

    // Document & structure incrementors
    (new \Database\Seeders\DocumentIncrementorSeeder())->run();
    (new \Database\Seeders\StructureIncrementorSeeder())->run();

    // Default warehouse
    \App\Models\Warehouse::create([
        'wh_title'     => 'Dépôt Principal',
        'wh_code'      => 'DP-001',
        'wh_address'   => 'Casablanca, Maroc',
        'wh_is_active' => true,
        'is_default'   => true,
    ]);

    // Default POS terminal
    \App\Models\PosTerminal::create([
        'name'         => 'Caisse 1',
        'code'         => 'POS_1',
        'warehouse_id' => \App\Models\Warehouse::first()->id,
        'is_active'    => true,
    ]);

    // Default price list
    \App\Models\PriceList::create([
        'name'       => 'Tarif Public',
        'code'       => 'PUB',
        'is_default' => true,
        'is_active'  => true,
    ]);

    // Sample categories
    $cats = ['Laptops', 'Écrans', 'Accessoires', 'Téléphones', 'Imprimantes'];
    foreach ($cats as $i => $cat) {
        \App\Models\Category::create([
            'ctg_title' => $cat,
            'ctg_code'  => 'CAT-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
        ]);
    }

    // Sample brand
    \App\Models\Brand::create([
        'br_title' => 'Générique',
        'br_code'  => 'GEN',
    ]);

    echo "Tenant database seeded.\n";
});

echo "\n✅ Demo tenant created successfully!\n";
echo "URL:      https://demo.o3app.ma\n";
echo "Email:    demo@o3app.ma\n";
echo "Password: demo1234\n";
