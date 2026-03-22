<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseHasStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WarehouseHasStock> */
class WarehouseHasStockFactory extends Factory
{
    protected $model = WarehouseHasStock::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id'   => Product::factory(),
            'stockLevel'   => fake()->randomFloat(2, 0, 500),
            'stockAtTime'  => now(),
            'wh_average'   => fake()->randomFloat(2, 10, 200),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(['stockLevel' => fake()->randomFloat(2, 0, 5)]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stockLevel' => 0]);
    }
}
