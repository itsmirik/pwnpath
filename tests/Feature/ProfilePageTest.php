<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Services\BadgeService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BadgeSeeder::class);
    }

    public function test_profile_exposes_rank_categories_badges_and_timeline(): void
    {
        $user = User::factory()->create(['xp_total' => 2500, 'streak_count' => 3]);

        // Two web solves + one crypto solve.
        $this->solveCategory($user, 'web', 2);
        $this->solveCategory($user, 'crypto', 1);

        // Award any earned badges through the engine (first solve → first-blood).
        app(BadgeService::class)->evaluate($user);

        $this->get("/u/{$user->username}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('profiles/Show')
            ->where('profile.rank.tier', 'gold')
            ->where('profile.global_position', 1)
            ->where('profile.solves_total', 3)
            ->has('categories', 6)
            ->where('categories.0.category', 'web')
            ->where('categories.0.count', 2)
            ->has('badges', 10)
            ->where('badges.0.slug', 'first-blood')
            ->where('badges.0.earned', true)
            ->where('badges.1.slug', 'getting-started')
            ->where('badges.1.earned', false)
            ->has('timeline', 3),
        );
    }

    public function test_new_user_profile_has_no_rank_position(): void
    {
        $user = User::factory()->create(['xp_total' => 0]);

        $this->get("/u/{$user->username}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('profile.global_position', null)
            ->where('profile.solves_total', 0)
            ->has('timeline', 0)
            ->where('badges.0.earned', false),
        );
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
