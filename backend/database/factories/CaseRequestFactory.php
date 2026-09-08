<?php

namespace Database\Factories;

use App\Models\Needy;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseRequestFactory extends Factory
{
    public function definition(): array
    {
        $titles = ['هزینه اجاره‌بها', 'هزینه درمان', 'خرید لوازم‌التحریر', 'هزینه دارو', 'کمک‌هزینه معیشت', 'هزینه عمل جراحی', 'خرید ویلچر', 'هزینه لوازم خانه'];
        $amount = fake()->numberBetween(3, 60) * 1_000_000;
        $funded = fake()->boolean(50) ? fake()->numberBetween(0, $amount) : 0;

        return [
            'needy_id' => Needy::factory(),
            'need_group_id' => null,
            'title' => $titles[array_rand($titles)],
            'plan' => fake()->randomElement(['once', 'monthly', 'period']),
            'period_days' => null,
            'amount' => $amount,
            'amount_funded' => $funded,
            'requested_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'deadline_at' => null,
            'status' => 'published',
            'published_at' => now(),
            'priority' => fake()->numberBetween(1, 3),
            'slot' => null,
            'meta' => [],
        ];
    }
}
