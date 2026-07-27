<?php

namespace App\Listeners;

use App\Events\SolveRecorded;
use App\Services\BadgeService;

/**
 * Badge criteria engine trigger (plan phase-5 item 3): on every recorded solve,
 * re-check all badges and award the newly-earned ones. The awarded badges are
 * written back onto the event so the caller can surface them to the user.
 */
class AwardBadges
{
    public function __construct(
        private readonly BadgeService $badges,
    ) {}

    public function handle(SolveRecorded $event): void
    {
        // concat (not assign) so the bag survives any accidental double-dispatch;
        // evaluate() is idempotent so a second pass simply adds nothing.
        $event->awardedBadges = $event->awardedBadges->concat(
            $this->badges->evaluate($event->user),
        );
    }
}
