<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\User;
use RuntimeException;

/**
 * Server-side dynamic flag generator (plan §10).
 *
 * Flags are never stored. They are recomputed on submit via HMAC-SHA256.
 */
class FlagGenerator
{
    public function generate(User $user, Challenge $challenge): string
    {
        $payload = $user->id.':'.$challenge->id;
        $hash = hash_hmac('sha256', $payload, $this->secret());
        $token = substr($hash, 0, 24);

        return "HTP{{$token}}";
    }

    /**
     * Constant-time comparison of submitted flag against the expected value
     * for this user+challenge (dynamic) or the challenge's static flag.
     */
    public function matches(User $user, Challenge $challenge, string $submitted): bool
    {
        $submitted = trim($submitted);

        if ($challenge->flag_type === Challenge::FLAG_STATIC) {
            $expected = $challenge->static_flag ?? '';

            return $expected !== '' && hash_equals($expected, $submitted);
        }

        return hash_equals($this->generate($user, $challenge), $submitted);
    }

    /**
     * Reverse-lookup: does this flag string match any published challenge's
     * expected flag for the given owner? Used by leak detection.
     */
    public function findOwnerChallenge(string $flag, User $owner): ?Challenge
    {
        $flag = trim($flag);

        $dynamics = Challenge::query()
            ->where('flag_type', Challenge::FLAG_DYNAMIC)
            ->get(['id', 'flag_type', 'static_flag']);

        foreach ($dynamics as $challenge) {
            if (hash_equals($this->generate($owner, $challenge), $flag)) {
                return $challenge;
            }
        }

        return Challenge::query()
            ->where('flag_type', Challenge::FLAG_STATIC)
            ->where('static_flag', $flag)
            ->first();
    }

    private function secret(): string
    {
        $secret = config('app.flag_secret');

        if (! is_string($secret) || $secret === '') {
            // Fall back to APP_KEY so local/dev never hard-crashes when FLAG_SECRET
            // is unset, but still produce deterministic per-env tokens.
            $secret = (string) config('app.key');
        }

        if ($secret === '') {
            throw new RuntimeException('FLAG_SECRET (or APP_KEY) is not configured.');
        }

        return $secret;
    }
}
