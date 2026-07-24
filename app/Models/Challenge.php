<?php

namespace App\Models;

use App\Concerns\Translatable;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $category
 * @property string $difficulty
 * @property int $points
 * @property int|null $author_id
 * @property string $status
 * @property string $flag_type
 * @property string|null $static_flag
 * @property string $flag_format
 * @property int $solve_count
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'slug',
    'category',
    'difficulty',
    'points',
    'author_id',
    'status',
    'flag_type',
    'static_flag',
    'flag_format',
    'published_at',
])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory, Translatable;

    public const CATEGORIES = ['web', 'crypto', 'forensics', 'osint', 'llm', 'misc'];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public const DIFFICULTY_POINTS = [
        'easy' => 100,
        'medium' => 300,
        'hard' => 700,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const FLAG_DYNAMIC = 'dynamic';

    public const FLAG_STATIC = 'static';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'solve_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function translationModel(): string
    {
        return ChallengeTranslation::class;
    }

    /** @return array<int, string> */
    public function translatableAttributes(): array
    {
        return ['title', 'description', 'hint_1', 'hint_2'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<ChallengeFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ChallengeFile::class);
    }

    /** @return HasMany<ChallengeTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ChallengeTranslation::class);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at');
    }
}
