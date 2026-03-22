<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'user_code'         => 'USR-' . fake()->unique()->numerify('####'),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role_id'           => fn () => Role::firstOrCreate(['name' => 'admin'], [
                'display_name' => 'Administrateur',
                'is_system'    => true,
            ])->id,
            'is_active'         => true,
            'remember_token'    => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role_id' => fn () => Role::firstOrCreate(['name' => 'admin'], [
            'display_name' => 'Administrateur', 'is_system' => true,
        ])->id]);
    }

    public function manager(): static
    {
        return $this->state(['role_id' => fn () => Role::firstOrCreate(['name' => 'manager'], [
            'display_name' => 'Manager', 'is_system' => true,
        ])->id]);
    }

    public function cashier(): static
    {
        return $this->state(['role_id' => fn () => Role::firstOrCreate(['name' => 'cashier'], [
            'display_name' => 'Caissier', 'is_system' => true,
        ])->id]);
    }

    public function warehouse(): static
    {
        return $this->state(['role_id' => fn () => Role::firstOrCreate(['name' => 'warehouse'], [
            'display_name' => 'Magasinier', 'is_system' => true,
        ])->id]);
    }

    public function inactive(): static { return $this->state(['is_active' => false]); }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
