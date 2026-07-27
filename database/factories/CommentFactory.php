<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'content' => $this->faker->sentence(),
            'is_hidden' => false,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_hidden' => true]);
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->id,
            'challenge_id' => $parent->challenge_id,
        ]);
    }
}
