<?php

namespace App\Models;

use Database\Factories\ChallengeFileFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $challenge_id
 * @property string $filename
 * @property string $storage_path
 * @property int $size_bytes
 * @property string $mime_type
 * @property Carbon|null $created_at
 */
#[Fillable([
    'challenge_id',
    'filename',
    'storage_path',
    'size_bytes',
    'mime_type',
])]
class ChallengeFile extends Model
{
    /** @use HasFactory<ChallengeFileFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function disk(): Filesystem
    {
        return Storage::disk('challenges');
    }
}
