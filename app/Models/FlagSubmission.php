<?php

namespace App\Models;

use Database\Factories\FlagSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $challenge_id
 * @property string $submitted_flag
 * @property bool $is_correct
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'challenge_id',
    'submitted_flag',
    'is_correct',
    'ip_address',
    'user_agent',
])]
class FlagSubmission extends Model
{
    /** @use HasFactory<FlagSubmissionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
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
