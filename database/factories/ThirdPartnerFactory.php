<?php

namespace Database\Factories;

use App\Models\ThirdPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ThirdPartner> */
class ThirdPartnerFactory extends Factory
{
    protected $model = ThirdPartner::class;

    public function definition(): array
    {
        return [
            'tp_title'  => fake()->company(),
            'tp_code'   => 'TP-' . fake()->unique()->numerify('####'),
            'tp_Role'   => 'customer',
            'tp_status' => true,
            'tp_phone'  => fake()->phoneNumber(),
            'tp_email'  => fake()->companyEmail(),
            'tp_address'=> fake()->address(),
            'tp_city'   => fake()->city(),
            'encours_actuel' => 0,
            'seuil_credit'   => 50000,
        ];
    }

    public function customer(): static { return $this->state(['tp_Role' => 'customer']); }
    public function supplier(): static { return $this->state(['tp_Role' => 'supplier']); }
}
