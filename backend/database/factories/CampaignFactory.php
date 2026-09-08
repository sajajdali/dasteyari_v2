<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CampaignFactory extends Factory
{
    private static int $seq = 0;

    public function definition(): array
    {
        $titles = ['کمپین حمایت از کودکان کار', 'کمپین شب یلدا', 'کمپین درمان بیماران خاص', 'کمپین بازگشایی مدارس', 'کمپین زمستان گرم'];
        $title = $titles[array_rand($titles)];
        $goal = fake()->numberBetween(100, 800) * 1_000_000;

        static::$seq++;

        return [
            'code' => 'CP-'.str_pad((string) static::$seq, 4, '0', STR_PAD_LEFT),
            'title' => $title,
            'slug' => Str::slug($title).'-'.static::$seq,
            'category_id' => null,
            'state' => 'running',
            'goal' => $goal,
            'raised' => fake()->numberBetween(0, $goal),
            'starts_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'ends_at' => fake()->dateTimeBetween('now', '+2 months'),
            'cover_path' => null,
            'short' => 'کمک به تامین نیازهای ضروری خانواده‌های نیازمند',
            'about' => 'شرح کامل کمپین در این‌جا قرار می‌گیرد.',
            'meta' => [],
        ];
    }
}
