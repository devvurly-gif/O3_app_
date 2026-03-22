<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WarehouseTransfer> */
class WarehouseTransferFactory extends Factory
{
    protected $model = WarehouseTransfer::class;

    public function definition(): array
    {
        return [
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id'   => Warehouse::factory(),
            'product_id'        => Product::factory(),
            'quantity'           => fake()->randomFloat(2, 1, 100),
            'status'            => 'pending',
            'user_id'           => User::factory(),
        ];
    }

    public function pending(): static   { return $this->state(['status' => 'pending']); }
    public function completed(): static { return $this->state(['status' => 'completed']); }
}
