<?php

namespace Database\Factories;

use App\Models\StructureIncrementor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StructureIncrementor> */
class StructureIncrementorFactory extends Factory
{
    protected $model = StructureIncrementor::class;

    public function definition(): array
    {
        return [
            'si_title'     => fake()->company(),
            'si_model'     => 'Product',
            'si_template'  => 'PRD-{000}',
            'si_nextTrick' => 1,
            'si_status'    => true,
        ];
    }
}
