<?php

namespace Database\Factories;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentFooter> */
class DocumentFooterFactory extends Factory
{
    protected $model = DocumentFooter::class;

    public function definition(): array
    {
        $ht  = fake()->randomFloat(2, 100, 10000);
        $tax = round($ht * 0.2, 2);
        $ttc = $ht + $tax;

        return [
            'document_header_id' => DocumentHeader::factory(),
            'total_ht'           => $ht,
            'total_discount'     => 0,
            'total_tax'          => $tax,
            'total_ttc'          => $ttc,
            'amount_paid'        => 0,
            'amount_due'         => $ttc,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'amount_paid' => $attrs['total_ttc'],
                'amount_due'  => 0,
            ];
        });
    }
}
