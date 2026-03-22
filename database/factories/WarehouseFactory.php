<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'wh_title'  => 'Dépôt ' . fake()->city(),
            'wh_code'   => 'WH-' . fake()->unique()->numerify('####'),
            'wh_status' => true,
        ];
    }
}
