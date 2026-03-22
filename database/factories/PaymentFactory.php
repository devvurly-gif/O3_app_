<?php

namespace Database\Factories;

use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payment_code'       => 'PAY-' . fake()->unique()->numerify('####'),
            'document_header_id' => DocumentHeader::factory(),
            'amount'             => fake()->randomFloat(2, 50, 5000),
            'method'             => fake()->randomElement(['cash', 'bank_transfer', 'cheque', 'effet', 'credit']),
            'paid_at'            => now(),
            'user_id'            => User::factory(),
        ];
    }
}
