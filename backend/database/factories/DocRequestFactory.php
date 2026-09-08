<?php

namespace Database\Factories;

use App\Models\CaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_id' => CaseRequest::factory(),
            'created_by' => User::factory(),
            'state' => 'open',
            'due_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'note' => null,
        ];
    }
}
