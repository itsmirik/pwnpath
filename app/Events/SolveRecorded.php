<?php

namespace App\Events;

use App\Models\Badge;
use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Fired once, after commit, when a user solves a challenge for the first time.
 *
 * Listeners run synchronously: badge awarding and the weekly-leaderboard write
 * hang off this event so the solve pipeline stays decoupled from them. The
 * `$awardedBadges` bag is filled in by the badge listener so the submit
 * response can tell the user what they just earned.
 */
class SolveRecorded
{
    use Dispatchable;

    /** @var Collection<int, Badge> */
    public Collection $awardedBadges;

    public function __construct(
        public readonly User $user,
        public readonly Challenge $challenge,
        public readonly Solve $solve,
    ) {
        $this->awardedBadges = collect();
    }
}
