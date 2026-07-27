<?php

namespace App\Models;

use Database\Factories\WriteupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $challenge_id
 * @property string $locale
 * @property string $content
 * @property string $status
 * @property int|null $moderator_id
 * @property string|null $moderation_note
 * @property int $upvote_count
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'challenge_id',
    'locale',
    'content',
    'status',
    'moderator_id',
    'moderation_note',
    'upvote_count',
    'moderated_at',
])]
class Writeup extends Model
{
    /** @use HasFactory<WriteupFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'upvote_count' => 'integer',
            'moderated_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /** @return HasMany<WriteupVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(WriteupVote::class);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }
}
