<?php

namespace Database\Factories;

use App\Models\PosTerminal;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosTerminal> */
class PosTerminalFactory extends Factory
{
    protected $model = PosTerminal::class;

    public function definition(): array
    {
        return [
            'name'         => 'Caisse ' . fake()->unique()->numerify('##'),
            'code'         => 'TERM-' . fake()->unique()->numerify('####'),
            'warehouse_id' => Warehouse::factory(),
            'is_active'    => true,
            'auto_print'   => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
