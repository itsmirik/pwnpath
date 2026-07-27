<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * v1 badge set (plan §5 — 10 badges). Idempotent: matched by slug so it can be
 * re-run after copy or criteria tweaks without duplicating rows.
 */
class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->badges() as $sort => $badge) {
            Badge::query()->updateOrCreate(
                ['slug' => $badge['slug']],
                [...$badge, 'sort_order' => $sort],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function badges(): array
    {
        return [
            [
                'slug' => 'first-blood',
                'icon' => '🩸',
                'criteria_json' => ['type' => 'solve_count', 'count' => 1],
                'name_ru' => 'Первая кровь',
                'name_uz' => 'Ilk qon',
                'name_en' => 'First Blood',
                'description_ru' => 'Решите свою первую задачу.',
                'description_uz' => 'Birinchi topshiriqni yeching.',
                'description_en' => 'Solve your very first challenge.',
            ],
            [
                'slug' => 'getting-started',
                'icon' => '🚀',
                'criteria_json' => ['type' => 'solve_count', 'count' => 5],
                'name_ru' => 'Разминка',
                'name_uz' => 'Boshlanish',
                'name_en' => 'Getting Started',
                'description_ru' => 'Решите 5 задач.',
                'description_uz' => '5 ta topshiriqni yeching.',
                'description_en' => 'Solve 5 challenges.',
            ],
            [
                'slug' => 'warmed-up',
                'icon' => '🔥',
                'criteria_json' => ['type' => 'solve_count', 'count' => 10],
                'name_ru' => 'Набираем обороты',
                'name_uz' => 'Qizib olindi',
                'name_en' => 'Warmed Up',
                'description_ru' => 'Решите 10 задач.',
                'description_uz' => '10 ta topshiriqni yeching.',
                'description_en' => 'Solve 10 challenges.',
            ],
            [
                'slug' => 'half-century',
                'icon' => '💯',
                'criteria_json' => ['type' => 'solve_count', 'count' => 50],
                'name_ru' => 'Полтинник',
                'name_uz' => 'Yarim yuzlik',
                'name_en' => 'Half Century',
                'description_ru' => 'Решите 50 задач.',
                'description_uz' => '50 ta topshiriqni yeching.',
                'description_en' => 'Solve 50 challenges.',
            ],
            [
                'slug' => 'web-warrior',
                'icon' => '🕸️',
                'criteria_json' => ['type' => 'category_solve_count', 'category' => 'web', 'count' => 5],
                'name_ru' => 'Веб-воин',
                'name_uz' => 'Veb jangchi',
                'name_en' => 'Web Warrior',
                'description_ru' => 'Решите 5 веб-задач.',
                'description_uz' => '5 ta veb topshiriqni yeching.',
                'description_en' => 'Solve 5 web challenges.',
            ],
            [
                'slug' => 'crypto-cracker',
                'icon' => '🔐',
                'criteria_json' => ['type' => 'category_solve_count', 'category' => 'crypto', 'count' => 5],
                'name_ru' => 'Взломщик шифров',
                'name_uz' => 'Kripto buzuvchi',
                'name_en' => 'Crypto Cracker',
                'description_ru' => 'Решите 5 крипто-задач.',
                'description_uz' => '5 ta kripto topshiriqni yeching.',
                'description_en' => 'Solve 5 crypto challenges.',
            ],
            [
                'slug' => 'ai-whisperer',
                'icon' => '🤖',
                'criteria_json' => ['type' => 'category_solve_count', 'category' => 'llm', 'count' => 3],
                'name_ru' => 'Заклинатель ИИ',
                'name_uz' => 'AI sehrgari',
                'name_en' => 'AI Whisperer',
                'description_ru' => 'Решите 3 задачи по AI/LLM.',
                'description_uz' => '3 ta AI/LLM topshiriqni yeching.',
                'description_en' => 'Solve 3 AI/LLM challenges.',
            ],
            [
                'slug' => 'streak-7',
                'icon' => '📅',
                'criteria_json' => ['type' => 'streak', 'days' => 7],
                'name_ru' => 'Серия 7',
                'name_uz' => '7 kunlik seriya',
                'name_en' => 'Streak 7',
                'description_ru' => 'Решайте задачи 7 дней подряд.',
                'description_uz' => '7 kun ketma-ket topshiriq yeching.',
                'description_en' => 'Keep a 7-day solving streak.',
            ],
            [
                'slug' => 'streak-30',
                'icon' => '🗓️',
                'criteria_json' => ['type' => 'streak', 'days' => 30],
                'name_ru' => 'Серия 30',
                'name_uz' => '30 kunlik seriya',
                'name_en' => 'Streak 30',
                'description_ru' => 'Держите серию 30 дней подряд.',
                'description_uz' => '30 kun ketma-ket topshiriq yeching.',
                'description_en' => 'Keep a 30-day solving streak.',
            ],
            [
                'slug' => 'speedrun',
                'icon' => '⚡',
                'criteria_json' => ['type' => 'speedrun', 'seconds' => 300],
                'name_ru' => 'Спидран',
                'name_uz' => 'Spidran',
                'name_en' => 'Speedrun',
                'description_ru' => 'Решите задачу менее чем за 5 минут после открытия.',
                'description_uz' => 'Topshiriqni ochgandan keyin 5 daqiqada yeching.',
                'description_en' => 'Solve a challenge within 5 minutes of opening it.',
            ],
        ];
    }
}
