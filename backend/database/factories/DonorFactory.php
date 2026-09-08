<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonorFactory extends Factory
{
    public function definition(): array
    {
        $cities = ['تهران', 'مشهد', 'شیراز', 'اصفهان', 'کرج', 'تبریز'];

        return [
            'user_id' => User::factory()->donor(),
            'kind' => 'person',
            'city' => $cities[array_rand($cities)],
            'capacity_cases' => fake()->numberBetween(1, 5),
            'monthly_day' => fake()->numberBetween(1, 28),
            'anon_default' => fake()->boolean(20),
            'status' => 'active',
            'joined_at' => fake()->dateTimeBetween('-3 years', 'now'),
            'meta' => [],
        ];
    }
}
