<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Id of a system role, carrying the exact grants the seeder gives it in
     * production.
     *
     * Routes are guarded by permission, so a role with an empty pivot is
     * denied everything but the admin bypass. The migration
     * `replace_users_role_enum_with_fk` already inserts the four role rows —
     * without any permission — so the factory fills the pivot rather than
     * creating the role, and only while it is still empty, leaving a test that
     * tunes its own grants in charge.
     */
    private static function systemRoleId(string $name): int
    {
        $attributes = RolePermissionSeeder::systemRoles()[$name] ?? [];

        $role = Role::firstOrCreate(['name' => $name], $attributes + ['is_system' => true]);

        if ($role->permissions()->count() === 0) {
            $ids = [];

            foreach (RolePermissionSeeder::permissionNamesFor($name) as $permission) {
                [$module, $action] = explode('.', $permission, 2);

                $ids[] = Permission::firstOrCreate(
                    ['name' => $permission],
                    [
                        'module'       => $module,
                        'action'       => $action,
                        'display_name' => RolePermissionSeeder::displayNameFor($module, $action),
                    ]
                )->id;
            }

            $role->permissions()->sync($ids);
        }

        return $role->id;
    }

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'user_code'         => 'USR-' . fake()->unique()->numerify('####'),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role_id'           => fn () => static::systemRoleId('admin'),
            'is_active'         => true,
            'remember_token'    => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role_id' => fn () => static::systemRoleId('admin')]);
    }

    public function manager(): static
    {
        return $this->state(['role_id' => fn () => static::systemRoleId('manager')]);
    }

    public function cashier(): static
    {
        return $this->state(['role_id' => fn () => static::systemRoleId('cashier')]);
    }

    public function warehouse(): static
    {
        return $this->state(['role_id' => fn () => static::systemRoleId('warehouse')]);
    }

    public function inactive(): static { return $this->state(['is_active' => false]); }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
