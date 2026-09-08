<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BroadcastFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mode' => 'general',
            'subject_id' => null,
            'title' => fake()->randomElement(['اطلاع‌رسانی کمپین زمستانی', 'یادآوری تعهد ماهانه']),
            'body' => 'متن پیامک نمونه برای اطلاع‌رسانی گروهی.',
            'channels' => ['sms'],
            'audience' => ['donors'],
            'total' => 100,
            'sent' => 100,
            'delivered' => 95,
            'opened' => 40,
            'state' => 'done',
            'created_by' => User::factory(),
        ];
    }
}
