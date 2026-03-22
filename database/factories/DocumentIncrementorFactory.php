<?php

namespace Database\Factories;

use App\Models\DocumentIncrementor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentIncrementor> */
class DocumentIncrementorFactory extends Factory
{
    protected $model = DocumentIncrementor::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numerify('####');

        return [
            'di_title'     => 'Incrementor ' . $suffix,
            'di_model'     => 'TestModel' . $suffix,
            'di_domain'    => 'test' . $suffix,
            'template'     => 'TST-{0000}',
            'nextTrick'    => 1,
            'status'       => true,
            'operatorSens' => 'in',
        ];
    }

    public function forQuote(): static
    {
        return $this->state([
            'di_model'  => 'Quote',
            'template'  => 'DEV-{0000}',
        ]);
    }

    public function forDeliveryNote(): static
    {
        return $this->state([
            'di_model'  => 'DeliveryNote',
            'template'  => 'BL-{0000}',
        ]);
    }

    public function forPurchaseOrder(): static
    {
        return $this->state([
            'di_model'     => 'PurchaseOrder',
            'di_domain'    => 'achat',
            'template'     => 'BC-{0000}',
        ]);
    }

    public function forReceiptNote(): static
    {
        return $this->state([
            'di_model'     => 'ReceiptNote',
            'di_domain'    => 'achat',
            'template'     => 'BR-{0000}',
        ]);
    }
}
