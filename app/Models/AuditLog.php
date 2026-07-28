<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable admin action record (plan §6, §8). Written by admin controllers and
 * anti-abuse crons; never updated (UPDATED_AT disabled).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array<string, mixed>|null $meta
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[Fillable([
    'actor_id',
    'action',
    'entity_type',
    'entity_id',
    'meta',
    'ip_address',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    // Action slugs, "<entity>.<verb>" (plan §6).
    public const ACTION_CHALLENGE_CREATE = 'challenge.create';

    public const ACTION_CHALLENGE_UPDATE = 'challenge.update';

    public const ACTION_CHALLENGE_SUBMIT_REVIEW = 'challenge.submit_review';

    public const ACTION_CHALLENGE_PUBLISH = 'challenge.publish';

    public const ACTION_CHALLENGE_UNPUBLISH = 'challenge.unpublish';

    public const ACTION_CHALLENGE_ARCHIVE = 'challenge.archive';

    public const ACTION_CHALLENGE_SYNC = 'challenge.sync';

    public const ACTION_WRITEUP_APPROVE = 'writeup.approve';

    public const ACTION_WRITEUP_REJECT = 'writeup.reject';

    public const ACTION_COMMENT_HIDE = 'comment.hide';

    public const ACTION_REPORT_DISMISS = 'report.dismiss';

    public const ACTION_USER_ROLE_CHANGE = 'user.role_change';

    public const ACTION_USER_BAN = 'user.ban';

    public const ACTION_USER_UNBAN = 'user.unban';

    public const ACTION_SECURITY_SUSPICIOUS_SOLVES = 'security.suspicious_solves';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Append an audit entry. A null actor marks a system/cron action.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        string $action,
        ?User $actor = null,
        ?Model $entity = null,
        array $meta = [],
        ?string $ip = null,
    ): self {
        return self::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity !== null ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => $ip ?? request()->ip(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeForAction(Builder $q, string $action): Builder
    {
        return $q->where('action', $action);
    }
}
