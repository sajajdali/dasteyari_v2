<?php

namespace Database\Factories;

use App\Models\CaseRequest;
use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->numberBetween(2, 20) * 1_000_000;
        $months = fake()->numberBetween(1, 12);

        return [
            'donor_id' => Donor::factory(),
            'request_id' => CaseRequest::factory(),
            'plan' => 'monthly',
            'amount' => $amount,
            'started_at' => fake()->dateTimeBetween('-1 year', '-1 month'),
            'ended_at' => null,
            'end_reason_id' => null,
            'status' => 'active',
            'given_total' => $amount * $months,
            'months_count' => $months,
        ];
    }

    public function transferred(): static
    {
        return $this->state(fn () => [
            'status' => 'transferred',
            'ended_at' => now(),
        ]);
    }
}
