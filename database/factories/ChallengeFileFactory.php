<?php

namespace Database\Factories;

use App\Models\ChallengeFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeFile>
 */
class ChallengeFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.txt';

        return [
            'filename' => $name,
            'storage_path' => 'seed/'.fake()->uuid().'/'.$name,
            'size_bytes' => fake()->numberBetween(1024, 1024 * 500),
            'mime_type' => 'text/plain',
        ];
    }
}
