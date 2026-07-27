<?php

namespace App\Services;

use App\Events\SolveRecorded;
use App\Models\Challenge;
use App\Models\ChallengeView;
use App\Models\FlagSubmission;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Flag submit pipeline (plan §10):
 * rate limit → validate → record submission → award solve + XP + streak.
 */
class SubmitFlagService
{
    public const PER_CHALLENGE_LIMIT = 30;

    public const GLOBAL_LIMIT = 100;

    public const WINDOW_HOURS = 1;

    public function __construct(
        private readonly FlagGenerator $flags,
        private readonly StreakService $streaks,
        private readonly FlagLeakDetector $leakDetector,
    ) {}

    /**
     * @return array{status: string, points?: int, error?: string, retry_after_seconds?: int}
     */
    public function submit(User $user, Challenge $challenge, string $flag, Request $request): array
    {
        $flag = trim($flag);

        if ($rateLimited = $this->checkRateLimit($user, $challenge)) {
            return $rateLimited;
        }

        $isCorrect = $this->flags->matches($user, $challenge, $flag);

        FlagSubmission::query()->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'submitted_flag' => mb_substr($flag, 0, 256),
            'is_correct' => $isCorrect,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);

        if (! $isCorrect) {
            $this->leakDetector->inspectWrong($user, $challenge, $flag);

            return ['status' => 'wrong'];
        }

        $alreadySolved = Solve::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->exists();

        if ($alreadySolved) {
            return ['status' => 'already_solved'];
        }

        $points = (int) $challenge->points;

        DB::transaction(function () use ($user, $challenge, $request, $points): void {
            // Lock the user row so concurrent correct submits can't double-award.
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $exists = Solve::query()
                ->where('user_id', $locked->id)
                ->where('challenge_id', $challenge->id)
                ->exists();

            if ($exists) {
                return;
            }

            Solve::query()->create([
                'user_id' => $locked->id,
                'challenge_id' => $challenge->id,
                'points_awarded' => $points,
                'time_to_solve_seconds' => $this->timeToSolveSeconds($locked, $challenge),
                'ip_address' => $request->ip(),
            ]);

            $locked->increment('xp_total', $points);
            $challenge->increment('solve_count');

            // Refresh attributes mutated by increment before streak write.
            $locked->refresh();
            $this->streaks->recordSolve($locked);
        });

        // Re-check in case a race lost the insert (already_solved).
        $awarded = Solve::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->exists();

        if (! $awarded) {
            return ['status' => 'already_solved'];
        }

        $user->refresh();

        // Post-commit so listeners see fully-updated XP/streak/solve counts and
        // can safely touch external stores (Redis) outside the DB transaction.
        $solve = Solve::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->firstOrFail();

        $event = new SolveRecorded($user, $challenge, $solve);
        event($event);

        return [
            'status' => 'solved',
            'points' => $points,
            'xp_total' => $user->xp_total,
            'streak_count' => $user->streak_count,
            'badges' => $event->awardedBadges
                ->map(fn ($badge): array => [
                    'slug' => $badge->slug,
                    'name' => $badge->name(),
                    'icon' => $badge->icon,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{status: string, error: string, retry_after_seconds: int}|null
     */
    private function checkRateLimit(User $user, Challenge $challenge): ?array
    {
        $windowStart = now()->subHours(self::WINDOW_HOURS);

        $perChallenge = FlagSubmission::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->where('created_at', '>', $windowStart)
            ->count();

        if ($perChallenge >= self::PER_CHALLENGE_LIMIT) {
            return [
                'status' => 'rate_limited',
                'error' => 'rate_limited',
                'retry_after_seconds' => 30 * 60,
            ];
        }

        $global = FlagSubmission::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', $windowStart)
            ->count();

        if ($global >= self::GLOBAL_LIMIT) {
            return [
                'status' => 'rate_limited',
                'error' => 'rate_limited',
                'retry_after_seconds' => 60 * 60,
            ];
        }

        return null;
    }

    private function timeToSolveSeconds(User $user, Challenge $challenge): ?int
    {
        $view = ChallengeView::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->first();

        if ($view === null) {
            return null;
        }

        $seconds = $view->first_viewed_at->diffInSeconds(now());

        return max(0, (int) $seconds);
    }
}
