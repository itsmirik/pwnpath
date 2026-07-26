<?php

namespace App\Models;

use Database\Factories\SolveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $challenge_id
 * @property int $points_awarded
 * @property int|null $time_to_solve_seconds
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'challenge_id',
    'points_awarded',
    'time_to_solve_seconds',
    'ip_address',
])]
class Solve extends Model
{
    /** @use HasFactory<SolveFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points_awarded' => 'integer',
            'time_to_solve_seconds' => 'integer',
            'created_at' => 'datetime',
        ];
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
}
