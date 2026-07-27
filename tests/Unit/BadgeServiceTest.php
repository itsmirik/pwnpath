<?php

namespace Tests\Unit;

use App\Models\Badge;
use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private BadgeService $badges;

    protected function setUp(): void
    {
        parent::setUp();
        $this->badges = app(BadgeService::class);
    }

    public function test_first_solve_awards_a_solve_count_badge(): void
    {
        $user = User::factory()->create();
        $badge = Badge::factory()->criteria(['type' => 'solve_count', 'count' => 1])->create(['slug' => 'first-blood']);

        $this->solve($user);

        $awarded = $this->badges->evaluate($user);

        $this->assertCount(1, $awarded);
        $this->assertSame('first-blood', $awarded->first()?->slug);
        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id, 'badge_id' => $badge->id]);
    }

    public function test_solve_count_threshold_not_met_awards_nothing(): void
    {
        $user = User::factory()->create();
        Badge::factory()->criteria(['type' => 'solve_count', 'count' => 5])->create(['slug' => 'getting-started']);

        for ($i = 0; $i < 4; $i++) {
            $this->solve($user);
        }

        $this->assertCount(0, $this->badges->evaluate($user));

        $this->solve($user); // fifth

        $this->assertCount(1, $this->badges->evaluate($user));
    }

    public function test_category_badge_counts_only_that_category(): void
    {
        $user = User::factory()->create();
        Badge::factory()
            ->criteria(['type' => 'category_solve_count', 'category' => 'web', 'count' => 5])
            ->create(['slug' => 'web-warrior']);

        // 4 web + 3 crypto → not enough web yet.
        $this->solveCategory($user, 'web', 4);
        $this->solveCategory($user, 'crypto', 3);
        $this->assertCount(0, $this->badges->evaluate($user));

        $this->solveCategory($user, 'web', 1); // 5th web
        $awarded = $this->badges->evaluate($user);

        $this->assertCount(1, $awarded);
        $this->assertSame('web-warrior', $awarded->first()?->slug);
    }

    public function test_streak_badge_reads_user_streak_count(): void
    {
        $user = User::factory()->create(['streak_count' => 7]);
        Badge::factory()->criteria(['type' => 'streak', 'days' => 7])->create(['slug' => 'streak-7']);
        Badge::factory()->criteria(['type' => 'streak', 'days' => 30])->create(['slug' => 'streak-30']);

        $awarded = $this->badges->evaluate($user);

        $this->assertEqualsCanonicalizing(['streak-7'], $awarded->pluck('slug')->all());
    }

    public function test_speedrun_badge_needs_a_fast_solve(): void
    {
        $user = User::factory()->create();
        Badge::factory()->criteria(['type' => 'speedrun', 'seconds' => 300])->create(['slug' => 'speedrun']);

        $slow = Challenge::factory()->create();
        Solve::factory()->create([
            'user_id' => $user->id,
            'challenge_id' => $slow->id,
            'time_to_solve_seconds' => 600,
        ]);
        $this->assertCount(0, $this->badges->evaluate($user));

        $fast = Challenge::factory()->create();
        Solve::factory()->create([
            'user_id' => $user->id,
            'challenge_id' => $fast->id,
            'time_to_solve_seconds' => 120,
        ]);
        $this->assertCount(1, $this->badges->evaluate($user));
    }

    public function test_evaluate_is_idempotent(): void
    {
        $user = User::factory()->create();
        Badge::factory()->criteria(['type' => 'solve_count', 'count' => 1])->create();
        $this->solve($user);

        $this->assertCount(1, $this->badges->evaluate($user));
        $this->assertCount(0, $this->badges->evaluate($user));
        $this->assertSame(1, $user->userBadges()->count());
    }

    private function solve(User $user): void
    {
        $challenge = Challenge::factory()->create();
        Solve::factory()->create(['user_id' => $user->id, 'challenge_id' => $challenge->id]);
    }

    private function solveCategory(User $user, string $category, int $count): void
    {
        Challenge::factory()->count($count)->create(['category' => $category])
            ->each(fn (Challenge $c) => Solve::factory()->create([
                'user_id' => $user->id,
                'challenge_id' => $c->id,
            ]));
    }
}
