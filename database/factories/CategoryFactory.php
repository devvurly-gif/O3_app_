<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'ctg_title'  => fake()->word(),
            'ctg_code'   => 'CTG-' . fake()->unique()->numerify('####'),
            'ctg_status' => true,
        ];
    }
}
