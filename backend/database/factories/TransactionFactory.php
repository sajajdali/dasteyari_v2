<?php

namespace Database\Factories;

use App\Models\CaseRequest;
use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kind' => 'in',
            'donor_id' => Donor::factory(),
            'request_id' => CaseRequest::factory(),
            'campaign_id' => null,
            'amount' => fake()->numberBetween(1, 10) * 1_000_000,
            'way' => fake()->randomElement(['gateway', 'card', 'cash', 'deposit']),
            'ref' => fake()->unique()->uuid(),
            'gateway_id' => null,
            'status' => 'ok',
            'paid_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'registered_by' => null,
            'manual' => false,
            'description' => null,
            'meta' => [],
        ];
    }
}
