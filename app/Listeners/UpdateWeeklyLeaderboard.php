<?php

namespace App\Listeners;

use App\Events\SolveRecorded;
use App\Services\WeeklyLeaderboard;

/**
 * Weekly leaderboard writer (plan phase-5 item 5): push the points earned by a
 * solve into today's Redis bucket. The service swallows Redis failures, so a
 * cache outage never bubbles up into the solve request.
 */
class UpdateWeeklyLeaderboard
{
    public function __construct(
        private readonly WeeklyLeaderboard $leaderboard,
    ) {}

    public function handle(SolveRecorded $event): void
    {
        $this->leaderboard->record(
            $event->user->id,
            $event->solve->points_awarded,
            $event->solve->created_at,
        );
    }
}
