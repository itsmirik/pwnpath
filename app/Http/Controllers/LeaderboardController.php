<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Services\WeeklyLeaderboard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    private const GLOBAL_LIMIT = 100;

    private const CATEGORY_LIMIT = 20;

    public function __construct(
        private readonly WeeklyLeaderboard $weekly,
    ) {}

    /** Global all-time board — top 100 by total XP (plan §5). */
    public function global(Request $request): Response
    {
        $me = $request->user();

        $users = User::query()
            ->where('xp_total', '>', 0)
            ->withCount('solves')
            ->orderByDesc('xp_total')
            ->orderBy('id')
            ->limit(self::GLOBAL_LIMIT)
            ->get();

        $entries = $users->values()->map(fn (User $u, int $i): array => $this->entry(
            position: $i + 1,
            user: $u,
            score: $u->xp_total,
            solves: (int) ($u->solves_count ?? 0),
            meId: $me?->id,
        ))->all();

        $mine = null;
        if ($me !== null && $me->xp_total > 0) {
            $mine = $this->entry(
                position: User::query()->where('xp_total', '>', $me->xp_total)->count() + 1,
                user: $me,
                score: $me->xp_total,
                solves: $me->solves()->count(),
                meId: $me->id,
            );
        }

        return $this->render('global', $entries, $mine);
    }

    /** Rolling 7-day board — served from Redis, DB fallback (plan §5). */
    public function weekly(Request $request): Response
    {
        $me = $request->user();
        $scores = $this->weekly->top(self::GLOBAL_LIMIT);

        $entries = $this->hydrate($scores, $me?->id);

        $mine = null;
        if ($me !== null) {
            $windowScope = Solve::query()->where('created_at', '>=', $this->weeklyWindowStart());
            $meScore = (int) (clone $windowScope)->where('user_id', $me->id)->sum('points_awarded');

            if ($meScore > 0) {
                $mine = $this->entry(
                    position: $this->countUsersAbove($windowScope, $meScore) + 1,
                    user: $me,
                    score: $meScore,
                    solves: $me->solves()->count(),
                    meId: $me->id,
                );
            }
        }

        return $this->render('weekly', $entries, $mine);
    }

    /** Per-category board — top 20 by XP earned in that category (plan §5). */
    public function category(Request $request, string $category): Response
    {
        abort_unless(in_array($category, Challenge::CATEGORIES, true), 404);

        $me = $request->user();
        $scope = fn (): Builder => Solve::query()
            ->join('challenges', 'challenges.id', '=', 'solves.challenge_id')
            ->where('challenges.category', $category);

        $scores = $this->aggregateTop($scope(), self::CATEGORY_LIMIT);
        $entries = $this->hydrate($scores, $me?->id);

        $mine = null;
        if ($me !== null) {
            $meScore = (int) $scope()->where('solves.user_id', $me->id)->sum('solves.points_awarded');

            if ($meScore > 0) {
                $mine = $this->entry(
                    position: $this->countUsersAbove($scope(), $meScore) + 1,
                    user: $me,
                    score: $meScore,
                    solves: $me->solves()->count(),
                    meId: $me->id,
                );
            }
        }

        return $this->render('category', $entries, $mine, $category);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>|null  $mine
     */
    private function render(string $scope, array $entries, ?array $mine, ?string $category = null): Response
    {
        return Inertia::render('leaderboard/Show', [
            'scope' => $scope,
            'category' => $category,
            'categories' => Challenge::CATEGORIES,
            'entries' => $entries,
            'me' => $mine,
        ]);
    }

    /**
     * Load users for a [user_id => score] map and build ranked entries,
     * preserving the score ordering.
     *
     * @param  array<int, int>  $scores
     * @return list<array<string, mixed>>
     */
    private function hydrate(array $scores, ?int $meId): array
    {
        if ($scores === []) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', array_keys($scores))
            ->withCount('solves')
            ->get()
            ->keyBy('id');

        $entries = [];
        $position = 1;

        foreach ($scores as $userId => $score) {
            $user = $users->get($userId);

            if ($user === null) {
                continue; // user deleted since the score was recorded
            }

            $entries[] = $this->entry(
                position: $position++,
                user: $user,
                score: $score,
                solves: (int) ($user->solves_count ?? 0),
                meId: $meId,
            );
        }

        return $entries;
    }

    /**
     * @param  Builder<Solve>  $scope
     * @return array<int, int> user_id => summed points, highest first
     */
    private function aggregateTop(Builder $scope, int $limit): array
    {
        /** @var array<int, object{user_id: int, aggregate: int}> $rows */
        $rows = $scope
            ->groupBy('solves.user_id')
            ->select('solves.user_id', DB::raw('sum(solves.points_awarded) as aggregate'))
            ->orderByDesc('aggregate')
            ->orderBy('solves.user_id')
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
     * @param  Builder<Solve>  $scope
     */
    private function countUsersAbove(Builder $scope, int $meScore): int
    {
        $sub = $scope
            ->groupBy('solves.user_id')
            ->havingRaw('sum(solves.points_awarded) > ?', [$meScore])
            ->select('solves.user_id');

        return DB::query()->fromSub($sub, 'ranked')->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(int $position, User $user, int $score, int $solves, ?int $meId): array
    {
        return [
            'position' => $position,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_color' => $user->avatar_color,
            'country_code' => $user->country_code,
            'xp' => $score,
            'rank' => User::rankForXp($user->xp_total),
            'solves_count' => $solves,
            'is_me' => $meId !== null && $user->id === $meId,
        ];
    }

    private function weeklyWindowStart(): CarbonImmutable
    {
        return CarbonImmutable::now(WeeklyLeaderboard::TIMEZONE)
            ->subDays(6)
            ->startOfDay()
            ->utc();
    }
}
