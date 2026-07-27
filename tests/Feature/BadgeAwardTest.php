<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Challenge;
use App\Models\ChallengeTranslation;
use App\Models\User;
use App\Services\FlagGenerator;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end: submitting a correct flag fires SolveRecorded, whose AwardBadges
 * listener runs the criteria engine and the submit response reports new badges.
 */
class BadgeAwardTest extends TestCase
{
    use RefreshDatabase;

    private FlagGenerator $flags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flags = app(FlagGenerator::class);
        config(['app.flag_secret' => 'testing-flag-secret-do-not-use-in-prod']);
        $this->seed(BadgeSeeder::class);
    }

    public function test_first_solve_awards_first_blood_and_reports_it(): void
    {
        $user = User::factory()->create();
        $challenge = $this->publishedChallenge();
        $flag = $this->flags->generate($user, $challenge);

        $response = $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $flag]);

        $response->assertOk()
            ->assertJson(['status' => 'solved'])
            ->assertJsonPath('badges.0.slug', 'first-blood');

        $firstBlood = Badge::query()->where('slug', 'first-blood')->firstOrFail();
        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $firstBlood->id,
        ]);
    }

    public function test_badges_are_not_re_reported_on_later_solves(): void
    {
        $user = User::factory()->create();

        $first = $this->publishedChallenge(['slug' => 'ch-1']);
        $this->actingAs($user)
            ->postJson("/challenges/{$first->slug}/submit", ['flag' => $this->flags->generate($user, $first)])
            ->assertJsonPath('badges.0.slug', 'first-blood');

        $second = $this->publishedChallenge(['slug' => 'ch-2']);
        $this->actingAs($user)
            ->postJson("/challenges/{$second->slug}/submit", ['flag' => $this->flags->generate($user, $second)])
            ->assertOk()
            ->assertJsonPath('badges', []);

        $this->assertSame(1, $user->userBadges()->count());
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function publishedChallenge(array $attrs = []): Challenge
    {
        $challenge = Challenge::factory()->create(array_merge([
            'status' => Challenge::STATUS_PUBLISHED,
            'published_at' => now(),
            'flag_type' => Challenge::FLAG_DYNAMIC,
            'category' => 'web',
            'points' => 100,
        ], $attrs));

        ChallengeTranslation::factory()->for($challenge)->create([
            'locale' => 'en',
            'title' => $challenge->slug,
            'description' => 'body',
        ]);

        return $challenge;
    }
}
