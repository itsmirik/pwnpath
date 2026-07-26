<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\FlagSubmission;
use App\Models\LeakedFlag;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Flag-sharing detection (plan §11).
 *
 * If the same wrong-flag string is submitted by >3 distinct users, mark it
 * as leaked. If that string is another user's valid flag, alert admin.
 */
class FlagLeakDetector
{
    public const THRESHOLD = 3;

    public function __construct(
        private readonly FlagGenerator $flags,
    ) {}

    /**
     * Inspect a wrong submission. May create/update a leaked_flags row.
     */
    public function inspectWrong(User $submitter, Challenge $challenge, string $flag): void
    {
        $flag = trim($flag);

        if ($flag === '') {
            return;
        }

        $distinctUsers = FlagSubmission::query()
            ->where('submitted_flag', $flag)
            ->where('is_correct', false)
            ->distinct()
            ->count('user_id');

        if ($distinctUsers < self::THRESHOLD) {
            return;
        }

        $ownerUserId = $this->resolveOwnerUserId($flag, $challenge);
        $ownerChallengeId = $challenge->id;

        if ($ownerUserId !== null) {
            // Confirm the flag belongs to that owner for this (or any) challenge.
            $owner = User::query()->find($ownerUserId);
            if ($owner !== null) {
                $matched = $this->flags->findOwnerChallenge($flag, $owner);
                if ($matched !== null) {
                    $ownerChallengeId = $matched->id;
                }
            }
        }

        $leaked = LeakedFlag::query()->updateOrCreate(
            ['flag_value' => $flag],
            [
                'challenge_id' => $ownerChallengeId,
                'owner_user_id' => $ownerUserId,
                'distinct_submitters' => $distinctUsers,
                'detected_at' => now(),
            ],
        );

        if ($ownerUserId !== null && ! $leaked->admin_alerted) {
            Log::warning('flag.leak_detected', [
                'flag_prefix' => substr($flag, 0, 8).'…',
                'owner_user_id' => $ownerUserId,
                'challenge_id' => $ownerChallengeId,
                'distinct_submitters' => $distinctUsers,
                'reported_by_user_id' => $submitter->id,
            ]);

            $leaked->forceFill(['admin_alerted' => true])->save();
        }
    }

    /**
     * Heuristic: if the submitted flag is a valid dynamic flag for some other
     * user who already solved (or viewed) this challenge, attribute ownership.
     * For static flags the challenge author is not the "owner" — leave null.
     */
    private function resolveOwnerUserId(string $flag, Challenge $challenge): ?int
    {
        if ($challenge->flag_type === Challenge::FLAG_STATIC) {
            return null;
        }

        // Brute-force is impossible at scale; instead check recent solvers of
        // this challenge and any user who submitted the correct form of this flag.
        $candidateIds = FlagSubmission::query()
            ->where('challenge_id', $challenge->id)
            ->where('is_correct', true)
            ->distinct()
            ->pluck('user_id');

        // Also consider solvers of the challenge (covers the case where the
        // real owner hasn't re-submitted after the leak started).
        $solverIds = Solve::query()
            ->where('challenge_id', $challenge->id)
            ->pluck('user_id');

        $ids = $candidateIds->merge($solverIds)->unique()->values();

        foreach ($ids as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            if (hash_equals($this->flags->generate($user, $challenge), $flag)) {
                return (int) $user->id;
            }
        }

        return null;
    }
}
