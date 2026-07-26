<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $flag_value
 * @property int|null $challenge_id
 * @property int|null $owner_user_id
 * @property int $distinct_submitters
 * @property bool $admin_alerted
 * @property Carbon $detected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'flag_value',
    'challenge_id',
    'owner_user_id',
    'distinct_submitters',
    'admin_alerted',
    'detected_at',
])]
class LeakedFlag extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'distinct_submitters' => 'integer',
            'admin_alerted' => 'boolean',
            'detected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
