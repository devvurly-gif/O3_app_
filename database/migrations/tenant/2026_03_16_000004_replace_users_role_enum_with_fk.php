<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Seed the 4 default system roles
        $roles = [
            ['name' => 'admin',     'display_name' => 'Administrateur', 'description' => 'Accès complet à toutes les fonctionnalités',       'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'manager',   'display_name' => 'Gestionnaire',   'description' => 'Gestion catalogue, documents, stock',               'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'cashier',   'display_name' => 'Caissier',       'description' => 'Gestion documents et paiements',                    'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'warehouse', 'display_name' => 'Magasinier',     'description' => 'Gestion stock et entrepôts',                        'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('roles')->insert($roles);

        // Step 2: Add nullable role_id FK
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('role');
        });

        // Step 3: Map existing enum values to role IDs
        $roleIds = DB::table('roles')->pluck('id', 'name');
        foreach (['admin', 'manager', 'cashier', 'warehouse'] as $roleName) {
            if (isset($roleIds[$roleName])) {
                DB::table('users')
                    ->where('role', $roleName)
                    ->update(['role_id' => $roleIds[$roleName]]);
            }
        }

        // Assign default role (cashier) to any users without a match
        $cashierId = $roleIds['cashier'] ?? DB::table('roles')->where('name', 'cashier')->value('id');
        DB::table('users')->whereNull('role_id')->update(['role_id' => $cashierId]);

        // Step 4: Drop old enum column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        // Step 5: Make role_id non-nullable + add FK constraint
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Re-add enum column
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'cashier', 'warehouse'])
                  ->default('cashier')
                  ->after('password');
        });

        // Map role_id back to enum
        $roleNames = DB::table('roles')->pluck('name', 'id');
        foreach ($roleNames as $id => $name) {
            if (in_array($name, ['admin', 'manager', 'cashier', 'warehouse'])) {
                DB::table('users')->where('role_id', $id)->update(['role' => $name]);
            }
        }

        // Drop FK and column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
