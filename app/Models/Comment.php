<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $challenge_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $content
 * @property bool $is_hidden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'challenge_id',
    'user_id',
    'parent_id',
    'content',
    'is_hidden',
])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /** Max comments a user may post per rolling hour (plan §5 anti-abuse). */
    public const RATE_LIMIT_PER_HOUR = 5;

    public const MAX_LENGTH = 2000;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /** @return HasMany<CommentReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(CommentReport::class);
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_hidden', false);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }
}
