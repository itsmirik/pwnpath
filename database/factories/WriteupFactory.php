<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\User;
use App\Models\Writeup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Writeup>
 */
class WriteupFactory extends Factory
{
    protected $model = Writeup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'challenge_id' => Challenge::factory(),
            'locale' => 'en',
            'content' => "## Approach\n\n".$this->faker->paragraph()."\n\n```\n".$this->faker->sha256()."\n```",
            'status' => Writeup::STATUS_PENDING,
            'upvote_count' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => Writeup::STATUS_APPROVED,
            'moderated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => Writeup::STATUS_REJECTED,
            'moderation_note' => $this->faker->sentence(),
            'moderated_at' => now(),
        ]);
    }
}
