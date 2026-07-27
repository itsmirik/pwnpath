<?php

namespace Tests\Unit;

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Services\WeeklyLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the database driver (phpunit sets LEADERBOARD_WEEKLY_DRIVER=database),
 * which is also the production fallback when Redis is unavailable.
 */
class WeeklyLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private WeeklyLeaderboard $leaderboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leaderboard = app(WeeklyLeaderboard::class);
    }

    public function test_top_sums_points_in_window_and_orders_desc(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->scoreAt($alice, 100, now()->subDay());
        $this->scoreAt($alice, 300, now()->subDays(2));
        $this->scoreAt($bob, 700, now()->subDays(3));

        $top = $this->leaderboard->top();

        $this->assertSame([$bob->id => 700, $alice->id => 400], $top);
    }

    public function test_top_excludes_solves_older_than_seven_days(): void
    {
        $user = User::factory()->create();

        $this->scoreAt($user, 100, now()->subDay());       // in window
        $this->scoreAt($user, 500, now()->subDays(10));    // out of window

        $this->assertSame([$user->id => 100], $this->leaderboard->top());
    }

    public function test_top_respects_limit(): void
    {
        foreach (range(1, 3) as $i) {
            $this->scoreAt(User::factory()->create(), $i * 100, now()->subDay());
        }

        $this->assertCount(2, $this->leaderboard->top(2));
    }

    public function test_record_is_a_noop_under_database_driver(): void
    {
        // Must not throw even though no Redis connection exists in tests.
        $this->leaderboard->record(1, 100);
        $this->leaderboard->rebuild();

        $this->assertTrue(true);
    }

    private function scoreAt(User $user, int $points, \DateTimeInterface $at): void
    {
        $challenge = Challenge::factory()->create(['points' => $points]);

        Solve::factory()->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'points_awarded' => $points,
            'created_at' => $at,
        ]);
    }
}
