<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solve>
 */
class SolveFactory extends Factory
{
    protected $model = Solve::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_id' => Challenge::factory(),
            'points_awarded' => 100,
            'time_to_solve_seconds' => fake()->numberBetween(30, 3600),
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
