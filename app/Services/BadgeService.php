<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Badge criteria engine (plan §5 badge list, §6 tables).
 *
 * Called after a solve is recorded. Evaluates every badge's `criteria_json`
 * rule against the user's current stats and awards the ones newly earned.
 * Idempotent: badges already held are skipped, and the unique
 * (user_id, badge_id) constraint guards against double awards under races.
 *
 * Supported criteria shapes:
 *   {"type": "solve_count", "count": N}
 *   {"type": "category_solve_count", "category": "web", "count": N}
 *   {"type": "streak", "days": N}
 *   {"type": "speedrun", "seconds": N}
 */
class BadgeService
{
    /**
     * Award any newly-earned badges to the user.
     *
     * @return Collection<int, Badge> the badges awarded by this call
     */
    public function evaluate(User $user): Collection
    {
        $badges = Badge::query()->orderBy('sort_order')->get();

        if ($badges->isEmpty()) {
            return collect();
        }

        $ownedIds = UserBadge::query()
            ->where('user_id', $user->id)
            ->pluck('badge_id')
            ->all();

        $candidates = $badges->reject(fn (Badge $badge) => in_array($badge->id, $ownedIds, true));

        if ($candidates->isEmpty()) {
            return collect();
        }

        $stats = $this->stats($user);

        /** @var Collection<int, Badge> $awarded */
        $awarded = collect();

        foreach ($candidates as $badge) {
            if (! $this->earns($badge, $stats)) {
                continue;
            }

            $created = UserBadge::query()->firstOrCreate(
                ['user_id' => $user->id, 'badge_id' => $badge->id],
                ['awarded_at' => now()],
            );

            if ($created->wasRecentlyCreated) {
                $awarded->push($badge);
            }
        }

        return $awarded;
    }

    /**
     * @param  array{solves: int, categories: array<string, int>, streak: int, best_time: int|null}  $stats
     */
    private function earns(Badge $badge, array $stats): bool
    {
        $criteria = $badge->criteria_json;
        $type = $criteria['type'] ?? null;

        return match ($type) {
            'solve_count' => $stats['solves'] >= (int) ($criteria['count'] ?? 0),
            'category_solve_count' => ($stats['categories'][$criteria['category'] ?? ''] ?? 0)
                >= (int) ($criteria['count'] ?? 0),
            'streak' => $stats['streak'] >= (int) ($criteria['days'] ?? 0),
            'speedrun' => $stats['best_time'] !== null
                && $stats['best_time'] <= (int) ($criteria['seconds'] ?? 0),
            default => false,
        };
    }

    /**
     * Snapshot the stats every badge rule needs, in a fixed number of queries.
     *
     * @return array{solves: int, categories: array<string, int>, streak: int, best_time: int|null}
     */
    private function stats(User $user): array
    {
        $total = 0;
        $categories = [];

        /** @var array<int, object{category: string, aggregate: int}> $rows */
        $rows = Solve::query()
            ->where('solves.user_id', $user->id)
            ->join('challenges', 'challenges.id', '=', 'solves.challenge_id')
            ->groupBy('challenges.category')
            ->select('challenges.category', DB::raw('count(*) as aggregate'))
            ->get()
            ->all();

        foreach ($rows as $row) {
            $count = (int) $row->aggregate;
            $categories[$row->category] = $count;
            $total += $count;
        }

        $bestTime = Solve::query()
            ->where('user_id', $user->id)
            ->whereNotNull('time_to_solve_seconds')
            ->min('time_to_solve_seconds');

        return [
            'solves' => $total,
            'categories' => $categories,
            'streak' => $user->streak_count,
            'best_time' => $bestTime === null ? null : (int) $bestTime,
        ];
    }

    /**
     * Category slugs a badge is scoped to (for display). Empty for global badges.
     *
     * @return list<string>
     */
    public static function categoriesForBadge(Badge $badge): array
    {
        $category = $badge->criteria_json['category'] ?? null;

        return is_string($category) && in_array($category, Challenge::CATEGORIES, true)
            ? [$category]
            : [];
    }
}
