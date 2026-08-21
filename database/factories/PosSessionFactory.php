<?php

namespace Database\Factories;

use App\Models\PosSession;
use App\Models\PosTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosSession> */
class PosSessionFactory extends Factory
{
    protected $model = PosSession::class;

    public function definition(): array
    {
        return [
            'pos_terminal_id' => PosTerminal::factory(),
            'user_id'         => User::factory()->cashier(),
            'opened_at'       => now(),
            'opening_cash'    => 0,
        ];
    }

    public function closed(float $closingCash = 0): static
    {
        return $this->state([
            'closed_at'    => now(),
            'closing_cash' => $closingCash,
        ]);
    }
}
