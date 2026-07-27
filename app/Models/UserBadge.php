<?php

namespace App\Models;

use Database\Factories\UserBadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $badge_id
 * @property Carbon $awarded_at
 */
#[Fillable([
    'user_id',
    'badge_id',
    'awarded_at',
])]
class UserBadge extends Model
{
    /** @use HasFactory<UserBadgeFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Badge, $this> */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
