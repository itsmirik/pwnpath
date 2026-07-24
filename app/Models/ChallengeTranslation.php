<?php

namespace App\Models;

use Database\Factories\ChallengeTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $challenge_id
 * @property string $locale
 * @property string $title
 * @property string $description
 * @property string|null $hint_1
 * @property string|null $hint_2
 */
#[Fillable([
    'challenge_id',
    'locale',
    'title',
    'description',
    'hint_1',
    'hint_2',
])]
class ChallengeTranslation extends Model
{
    /** @use HasFactory<ChallengeTranslationFactory> */
    use HasFactory;

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
