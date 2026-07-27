<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name_ru' => $name,
            'name_uz' => $name,
            'name_en' => $name,
            'description_ru' => fake()->sentence(),
            'description_uz' => fake()->sentence(),
            'description_en' => fake()->sentence(),
            'icon' => '🏅',
            'criteria_json' => ['type' => 'solve_count', 'count' => 1],
            'sort_order' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function criteria(array $criteria): static
    {
        return $this->state(fn () => ['criteria_json' => $criteria]);
    }
}
