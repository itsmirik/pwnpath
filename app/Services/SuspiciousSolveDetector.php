<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Suspicious-solve detection (plan §11): if an account solves more than N hard
 * challenges inside a short window it is flagged for admin review. Flagging is a
 * detection signal only — it records an audit-log entry (and log warning), never
 * an automatic ban. Runs on a cron (every 5 minutes).
 */
class SuspiciousSolveDetector
{
    /**
     * @return list<array{user_id: int, hard_solves: int, window_minutes: int}>
     */
    public function detect(): array
    {
        $threshold = (int) config('challenges.suspicious_hard_solves');
        $windowMinutes = (int) config('challenges.suspicious_window_minutes');
        $since = now()->subMinutes($windowMinutes);

        $rows = Solve::query()
            ->join('challenges', 'challenges.id', '=', 'solves.challenge_id')
            ->where('challenges.difficulty', 'hard')
            ->where('solves.created_at', '>=', $since)
            ->groupBy('solves.user_id')
            ->havingRaw('count(*) > ?', [$threshold])
            ->select('solves.user_id')
            ->selectRaw('count(*) as hard_solves')
            ->get();

        $flagged = [];

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $hardSolves = (int) $row->getAttribute('hard_solves');

            // Idempotent: don't re-flag the same account within the window.
            $already = AuditLog::query()
                ->forAction(AuditLog::ACTION_SECURITY_SUSPICIOUS_SOLVES)
                ->where('entity_id', $userId)
                ->where('created_at', '>=', $since)
                ->exists();

            if ($already) {
                continue;
            }

            $user = User::query()->find($userId);

            if ($user === null) {
                continue;
            }

            AuditLog::record(
                AuditLog::ACTION_SECURITY_SUSPICIOUS_SOLVES,
                actor: null,
                entity: $user,
                meta: [
                    'hard_solves' => $hardSolves,
                    'window_minutes' => $windowMinutes,
                    'threshold' => $threshold,
                ],
            );

            Log::warning('security.suspicious_solves', [
                'user_id' => $userId,
                'username' => $user->username,
                'hard_solves' => $hardSolves,
                'window_minutes' => $windowMinutes,
            ]);

            $flagged[] = [
                'user_id' => $userId,
                'hard_solves' => $hardSolves,
                'window_minutes' => $windowMinutes,
            ];
        }

        return $flagged;
    }
}
