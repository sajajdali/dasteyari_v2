<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        $titles = ['پیگیری تماس با خیر', 'تایید مدارک پرونده', 'بازدید از منزل نیازمند', 'تماس یادآوری تعهد', 'بررسی درخواست ورودی'];

        return [
            'assignee_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'title' => $titles[array_rand($titles)],
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'due_at' => fake()->dateTimeBetween('-3 days', '+1 week'),
            'done_at' => null,
        ];
    }
}
