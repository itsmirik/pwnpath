<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\FlagSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlagSubmission>
 */
class FlagSubmissionFactory extends Factory
{
    protected $model = FlagSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_id' => Challenge::factory(),
            'submitted_flag' => 'HTP{'.fake()->regexify('[a-f0-9]{24}').'}',
            'is_correct' => false,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
