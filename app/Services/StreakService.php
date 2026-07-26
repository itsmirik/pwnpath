<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Consecutive-day streak tracking (plan §5 Streaks).
 *
 * Days are counted in Asia/Tashkent. Freeze tokens auto-consume when the
 * user misses exactly one calendar day between solves.
 */
class StreakService
{
    public const TIMEZONE = 'Asia/Tashkent';

    /**
     * Apply streak rules after a successful first-time solve today.
     * Mutates the user model in place (caller is responsible for persistence
     * when already inside a transaction — we save here for safety).
     */
    public function recordSolve(User $user, ?CarbonInterface $at = null): void
    {
        $today = $this->day($at);
        $last = $user->last_solve_date;

        if ($last !== null && $this->day($last)->equalTo($today)) {
            // Already solved something earlier today — streak unchanged.
            return;
        }

        if ($last === null) {
            $user->streak_count = 1;
        } else {
            $lastDay = $this->day($last);
            $yesterday = $today->subDay();

            if ($lastDay->equalTo($yesterday)) {
                $user->streak_count = $user->streak_count + 1;
            } elseif ($lastDay->equalTo($yesterday->subDay()) && $user->streak_freeze_available > 0) {
                // Missed exactly one day — consume freeze, keep streak going.
                $user->streak_freeze_available = $user->streak_freeze_available - 1;
                $user->streak_count = $user->streak_count + 1;
            } else {
                $user->streak_count = 1;
            }
        }

        $user->last_solve_date = Carbon::instance($today);
        $user->save();
    }

    public function day(?CarbonInterface $at = null): CarbonImmutable
    {
        $moment = $at === null
            ? CarbonImmutable::now(self::TIMEZONE)
            : CarbonImmutable::instance($at)->timezone(self::TIMEZONE);

        return $moment->startOfDay();
    }
}
