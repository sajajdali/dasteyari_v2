<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DocRequestItemFactory extends Factory
{
    public function definition(): array
    {
        $labels = ['کارت ملی', 'سند درآمد', 'مدرک پزشکی', 'اجاره‌نامه', 'شناسنامه فرزندان'];

        return [
            'label' => $labels[array_rand($labels)],
            'type' => 'image',
            'required' => true,
            'order' => fake()->numberBetween(1, 5),
            'path' => null,
            'filled_at' => null,
        ];
    }
}
