<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cap signups per IP (plan §11): 5 accounts / IP / calendar day.
 */
class SignupIpLimiter
{
    public const MAX_PER_DAY = 5;

    public const TIMEZONE = 'Asia/Tashkent';

    /**
     * @throws ValidationException
     */
    public function assertAllowed(?string $ip): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $start = CarbonImmutable::now(self::TIMEZONE)->startOfDay()->utc();
        $end = CarbonImmutable::now(self::TIMEZONE)->endOfDay()->utc();

        $count = User::query()
            ->where('signup_ip', $ip)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        if ($count >= self::MAX_PER_DAY) {
            throw ValidationException::withMessages([
                'email' => [__('auth.signup_ip_limit')],
            ]);
        }
    }
}
