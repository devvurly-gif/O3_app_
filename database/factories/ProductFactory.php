<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'p_title'         => fake()->words(3, true),
            'p_code'          => 'PRD-' . fake()->unique()->numerify('####'),
            'p_description'   => fake()->sentence(),
            'p_sku'           => fake()->unique()->ean8(),
            'p_ean13'         => fake()->ean13(),
            'p_purchasePrice' => fake()->randomFloat(2, 10, 500),
            'p_salePrice'     => fake()->randomFloat(2, 20, 1000),
            'p_cost'          => fake()->randomFloat(2, 5, 200),
            'p_status'        => true,
            'p_taxRate'       => 20.00,
            'p_unit'          => 'pcs',
            'category_id'     => \App\Models\Category::factory(),
            'brand_id'        => \App\Models\Brand::factory(),
        ];
    }
}
