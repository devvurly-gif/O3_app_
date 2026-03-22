<?php

namespace Database\Factories;

use App\Models\DocumentHeader;
use App\Models\DocumentLigne;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentLigne> */
class DocumentLigneFactory extends Factory
{
    protected $model = DocumentLigne::class;

    public function definition(): array
    {
        $qty   = fake()->randomFloat(2, 1, 50);
        $price = fake()->randomFloat(2, 10, 500);
        $tax   = 20.00;
        $ht    = $qty * $price;
        $taxAmt = $ht * $tax / 100;

        return [
            'document_header_id' => DocumentHeader::factory(),
            'product_id'         => Product::factory(),
            'sort_order'         => 1,
            'line_type'          => 'product',
            'designation'        => fake()->words(3, true),
            'reference'          => fake()->ean8(),
            'quantity'           => $qty,
            'unit'               => 'pcs',
            'unit_price'         => $price,
            'discount_percent'   => 0,
            'tax_percent'        => $tax,
            'total_ligne_ht'     => $ht,
            'total_tax'          => $taxAmt,
            'total_ttc'          => $ht + $taxAmt,
            'status'             => 'active',
        ];
    }
}
