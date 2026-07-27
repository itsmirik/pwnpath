<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    private const TIMELINE_LIMIT = 20;

    public function show(User $user): Response
    {
        $locale = app()->getLocale();

        return Inertia::render('profiles/Show', [
            'profile' => [
                'username' => $user->username,
                'display_name' => $user->display_name,
                'bio' => $user->bio,
                'avatar_color' => $user->avatar_color,
                'country_code' => $user->country_code,
                'locale' => $user->locale,
                'xp_total' => $user->xp_total,
                'streak_count' => $user->streak_count,
                'joined_at' => $user->created_at?->toIso8601String(),
                'rank' => $user->rankProgress(),
                'global_position' => $this->globalPosition($user),
                'solves_total' => $user->solves()->count(),
            ],
            'categories' => $this->categoryCounts($user),
            'badges' => $this->badges($user, $locale),
            'timeline' => $this->timeline($user, $locale),
        ]);
    }

    /** All-time rank position, or null for users who have not scored yet. */
    private function globalPosition(User $user): ?int
    {
        if ($user->xp_total <= 0) {
            return null;
        }

        return User::query()->where('xp_total', '>', $user->xp_total)->count() + 1;
    }

    /**
     * Solved count per category, every category present (0 when unsolved).
     *
     * @return array<int, array{category: string, count: int}>
     */
    private function categoryCounts(User $user): array
    {
        $counts = Solve::query()
            ->where('solves.user_id', $user->id)
            ->join('challenges', 'challenges.id', '=', 'solves.challenge_id')
            ->groupBy('challenges.category')
            ->select('challenges.category', DB::raw('count(*) as aggregate'))
            ->pluck('aggregate', 'challenges.category')
            ->all();

        return array_map(fn (string $category): array => [
            'category' => $category,
            'count' => (int) ($counts[$category] ?? 0),
        ], Challenge::CATEGORIES);
    }

    /**
     * Every badge with earned state, so the grid can show locked ones greyed.
     *
     * @return list<array<string, mixed>>
     */
    private function badges(User $user, string $locale): array
    {
        $badges = Badge::query()->orderBy('sort_order')->get();

        $awardedAt = UserBadge::query()
            ->where('user_id', $user->id)
            ->pluck('awarded_at', 'badge_id');

        return $badges->map(function (Badge $badge) use ($awardedAt, $locale): array {
            $earnedAt = $awardedAt->get($badge->id);

            return [
                'slug' => $badge->slug,
                'name' => $badge->name($locale),
                'description' => $badge->description($locale),
                'icon' => $badge->icon,
                'categories' => BadgeService::categoriesForBadge($badge),
                'earned' => $earnedAt !== null,
                'awarded_at' => $earnedAt?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * Most recent solves, newest first (plan §5 solve timeline).
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(User $user, string $locale): array
    {
        $solves = Solve::query()
            ->where('user_id', $user->id)
            ->with('challenge.translations')
            ->orderByDesc('created_at')
            ->limit(self::TIMELINE_LIMIT)
            ->get();

        return $solves->map(function (Solve $solve) use ($locale): array {
            $challenge = $solve->challenge;

            return [
                'challenge_slug' => $challenge?->slug,
                'title' => $challenge?->tr('title', $locale) ?? $challenge?->slug,
                'category' => $challenge?->category,
                'difficulty' => $challenge?->difficulty,
                'points' => $solve->points_awarded,
                'solved_at' => $solve->created_at?->toIso8601String(),
            ];
        })->all();
    }
}
