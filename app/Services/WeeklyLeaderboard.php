<?php

namespace App\Services;

use App\Models\Solve;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Rolling 7-day XP leaderboard (plan §5, phase-5 item 5).
 *
 * Production path: one Redis sorted set per Tashkent day
 * (`leaderboard:weekly:{Y-m-d}`). Each solve does ZINCRBY on today's key with
 * an 8-day TTL; a query ZUNIONSTOREs the last 7 daily keys and ZREVRANGEs the
 * result. Reading unions daily buckets so the window truly rolls day-by-day.
 *
 * Fallback path: when the driver is "database" (tests) or Redis is
 * unavailable, the same window is aggregated straight from the solves table.
 * The solves table is the source of truth, so a Redis outage degrades to a
 * slower query rather than losing data.
 */
class WeeklyLeaderboard
{
    public const TIMEZONE = 'Asia/Tashkent';

    private const KEY_PREFIX = 'leaderboard:weekly:';

    private const WINDOW_DAYS = 7;

    /**
     * Add points earned by a solve to today's bucket.
     */
    public function record(int $userId, int $points, ?CarbonInterface $at = null): void
    {
        if ($points <= 0 || ! $this->usingRedis()) {
            return;
        }

        $key = $this->keyFor($this->day($at));

        try {
            $redis = Redis::connection();
            $redis->zincrby($key, $points, (string) $userId);
            $redis->expire($key, $this->ttlDays() * 86400);
        } catch (Throwable $e) {
            // A cache write must never break a user's solve; the solves table
            // still holds the truth and the DB fallback can serve reads.
            Log::warning('weekly_leaderboard.record_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Top scorers over the rolling window, highest first.
     *
     * @return array<int, int> map of user_id => xp_this_week, insertion order = rank
     */
    public function top(int $limit = 100): array
    {
        if ($this->usingRedis()) {
            $fromRedis = $this->topFromRedis($limit);

            if ($fromRedis !== null) {
                return $fromRedis;
            }
        }

        return $this->topFromDatabase($limit);
    }

    /**
     * Replay the last 7 days from the solves table into Redis. Useful after a
     * Redis restart or a driver switch. No-op unless the Redis driver is active.
     */
    public function rebuild(): void
    {
        if (! $this->usingRedis()) {
            return;
        }

        try {
            $redis = Redis::connection();

            foreach ($this->windowDays() as $day) {
                $key = $this->keyFor($day);
                $totals = $this->dayTotals($day);

                $redis->del($key);

                foreach ($totals as $userId => $points) {
                    $redis->zadd($key, $points, (string) $userId);
                }

                if ($totals !== []) {
                    $redis->expire($key, $this->ttlDays() * 86400);
                }
            }
        } catch (Throwable $e) {
            Log::warning('weekly_leaderboard.rebuild_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<int, int>|null null signals a Redis failure → fall back to DB
     */
    private function topFromRedis(int $limit): ?array
    {
        try {
            $redis = Redis::connection();
            $keys = array_map(fn (CarbonImmutable $d) => $this->keyFor($d), $this->windowDays());

            $dest = self::KEY_PREFIX.'_window';
            $redis->zunionstore($dest, $keys);
            $redis->expire($dest, 60);

            /** @var array<string, float|string> $raw */
            $raw = $redis->zrevrange($dest, 0, $limit - 1, true);

            $out = [];
            foreach ($raw as $userId => $score) {
                $out[(int) $userId] = (int) $score;
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('weekly_leaderboard.query_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, int>
     */
    private function topFromDatabase(int $limit): array
    {
        $windowStart = $this->windowStart();

        /** @var array<int, object{user_id: int, aggregate: int}> $rows */
        $rows = Solve::query()
            ->where('created_at', '>=', $windowStart)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('sum(points_awarded) as aggregate'))
            ->orderByDesc('aggregate')
            ->orderBy('user_id')
            ->limit($limit)
            ->get()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->user_id] = (int) $row->aggregate;
        }

        return $out;
    }

    /**
     * XP earned per user on a single Tashkent day (for rebuild).
     *
     * @return array<int, int>
     */
    private function dayTotals(CarbonImmutable $day): array
    {
        $start = $day->utc();
        $end = $day->addDay()->utc();

        /** @var array<int, object{user_id: int, aggregate: int}> $rows */
        $rows = Solve::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('sum(points_awarded) as aggregate'))
            ->get()
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->user_id] = (int) $row->aggregate;
        }

        return $out;
    }

    /**
     * @return list<CarbonImmutable> today first, then the previous 6 Tashkent days
     */
    private function windowDays(): array
    {
        $today = $this->day();

        return array_map(
            fn (int $i): CarbonImmutable => $today->subDays($i),
            range(0, self::WINDOW_DAYS - 1),
        );
    }

    private function windowStart(): CarbonImmutable
    {
        return $this->day()->subDays(self::WINDOW_DAYS - 1)->utc();
    }

    private function day(?CarbonInterface $at = null): CarbonImmutable
    {
        $moment = $at === null
            ? CarbonImmutable::now(self::TIMEZONE)
            : CarbonImmutable::instance($at)->timezone(self::TIMEZONE);

        return $moment->startOfDay();
    }

    private function keyFor(CarbonImmutable $day): string
    {
        return self::KEY_PREFIX.$day->format('Y-m-d');
    }

    private function usingRedis(): bool
    {
        return config('leaderboard.weekly_driver') === 'redis';
    }

    private function ttlDays(): int
    {
        return (int) config('leaderboard.weekly_ttl_days', 8);
    }
}
