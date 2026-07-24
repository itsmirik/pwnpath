<?php

namespace Database\Factories;

use App\Models\ChallengeTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeTranslation>
 */
class ChallengeTranslationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'en',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(6),
            'hint_1' => fake()->sentence(),
            'hint_2' => fake()->sentence(),
        ];
    }
}
