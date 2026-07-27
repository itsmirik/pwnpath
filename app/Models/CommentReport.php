<?php

namespace App\Models;

use Database\Factories\CommentReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $comment_id
 * @property int $reporter_id
 * @property string $reason
 * @property string $status
 * @property Carbon|null $created_at
 */
#[Fillable([
    'comment_id',
    'reporter_id',
    'reason',
    'status',
])]
class CommentReport extends Model
{
    /** @use HasFactory<CommentReportFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
