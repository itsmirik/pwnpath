<?php

namespace App\Models;

use Database\Factories\WriteupVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $writeup_id
 * @property int $user_id
 * @property Carbon|null $created_at
 */
#[Fillable([
    'writeup_id',
    'user_id',
])]
class WriteupVote extends Model
{
    /** @use HasFactory<WriteupVoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Writeup, $this> */
    public function writeup(): BelongsTo
    {
        return $this->belongsTo(Writeup::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
