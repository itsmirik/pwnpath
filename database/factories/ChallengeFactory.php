<?php

namespace Database\Factories;

use App\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $difficulty = fake()->randomElement(Challenge::DIFFICULTIES);

        return [
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'category' => fake()->randomElement(Challenge::CATEGORIES),
            'difficulty' => $difficulty,
            'points' => Challenge::DIFFICULTY_POINTS[$difficulty],
            'author_id' => null,
            'status' => Challenge::STATUS_PUBLISHED,
            'flag_type' => Challenge::FLAG_DYNAMIC,
            'static_flag' => null,
            'flag_format' => 'HTP\{[a-f0-9]{24}\}',
            'solve_count' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Challenge::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function staticFlag(string $flag = 'HTP{static_tutorial_flag_0001}'): static
    {
        return $this->state(fn () => [
            'flag_type' => Challenge::FLAG_STATIC,
            'static_flag' => $flag,
        ]);
    }
}
