<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_board_lists_top_users_by_xp_descending(): void
    {
        $top = User::factory()->create(['xp_total' => 3000]);
        $mid = User::factory()->create(['xp_total' => 1500]);
        $low = User::factory()->create(['xp_total' => 100]);
        User::factory()->create(['xp_total' => 0]); // unranked, excluded

        $this->get('/leaderboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('leaderboard/Show')
            ->where('scope', 'global')
            ->has('entries', 3)
            ->where('entries.0.username', $top->username)
            ->where('entries.0.position', 1)
            ->where('entries.0.xp', 3000)
            ->where('entries.0.rank', 'gold')
            ->where('entries.1.username', $mid->username)
            ->where('entries.2.username', $low->username)
            ->where('me', null),
        );
    }

    public function test_global_board_shows_current_user_position(): void
    {
        foreach ([5000, 4000, 3000] as $xp) {
            User::factory()->create(['xp_total' => $xp]);
        }
        $me = User::factory()->create(['xp_total' => 1000]);

        $this->actingAs($me)->get('/leaderboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('me.username', $me->username)
            ->where('me.position', 4)
            ->where('me.is_me', true),
        );
    }

    public function test_weekly_board_aggregates_recent_solves(): void
    {
        $active = User::factory()->create();
        $stale = User::factory()->create();

        $this->solveWorth($active, 300, now()->subDay());
        $this->solveWorth($active, 100, now()->subDays(2));
        $this->solveWorth($stale, 700, now()->subDays(10)); // outside window

        $this->get('/leaderboard/weekly')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'weekly')
            ->has('entries', 1)
            ->where('entries.0.username', $active->username)
            ->where('entries.0.xp', 400),
        );
    }

    public function test_category_board_scopes_to_one_category(): void
    {
        $webber = User::factory()->create();
        $cryptor = User::factory()->create();

        $this->solveWorth($webber, 300, now(), 'web');
        $this->solveWorth($webber, 100, now(), 'crypto');
        $this->solveWorth($cryptor, 700, now(), 'crypto');

        $this->get('/leaderboard/category/web')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'category')
            ->where('category', 'web')
            ->has('entries', 1)
            ->where('entries.0.username', $webber->username)
            ->where('entries.0.xp', 300),
        );
    }

    public function test_unknown_category_is_not_found(): void
    {
        $this->get('/leaderboard/category/bogus')->assertNotFound();
    }

    private function solveWorth(User $user, int $points, \DateTimeInterface $at, ?string $category = null): void
    {
        $challenge = Challenge::factory()->create(array_filter([
            'points' => $points,
            'category' => $category,
        ], fn ($v) => $v !== null));

        Solve::factory()->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'points_awarded' => $points,
            'created_at' => $at,
        ]);
    }
}
