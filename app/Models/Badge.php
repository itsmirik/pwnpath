<?php

namespace App\Models;

use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name_ru
 * @property string $name_uz
 * @property string $name_en
 * @property string $description_ru
 * @property string $description_uz
 * @property string $description_en
 * @property string $icon
 * @property array<string, mixed> $criteria_json
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug',
    'name_ru',
    'name_uz',
    'name_en',
    'description_ru',
    'description_uz',
    'description_en',
    'icon',
    'criteria_json',
    'sort_order',
])]
class Badge extends Model
{
    /** @use HasFactory<BadgeFactory> */
    use HasFactory;

    /** Locale fallback order for badge copy (plan §12: RU → EN → UZ). */
    private const FALLBACKS = ['ru', 'en', 'uz'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'criteria_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot('awarded_at');
    }

    public function name(?string $locale = null): string
    {
        return $this->localized('name', $locale);
    }

    public function description(?string $locale = null): string
    {
        return $this->localized('description', $locale);
    }

    private function localized(string $attribute, ?string $locale): string
    {
        $locale ??= app()->getLocale();

        foreach ([$locale, ...self::FALLBACKS] as $candidate) {
            $value = $this->getAttribute("{$attribute}_{$candidate}");

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }
}
