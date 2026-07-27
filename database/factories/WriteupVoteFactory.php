<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Writeup;
use App\Models\WriteupVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WriteupVote>
 */
class WriteupVoteFactory extends Factory
{
    protected $model = WriteupVote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'writeup_id' => Writeup::factory(),
            'user_id' => User::factory(),
            'created_at' => now(),
        ];
    }
}
