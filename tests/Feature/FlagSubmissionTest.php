<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeTranslation;
use App\Models\ChallengeView;
use App\Models\FlagSubmission;
use App\Models\LeakedFlag;
use App\Models\Solve;
use App\Models\User;
use App\Services\FlagGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private FlagGenerator $flags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flags = app(FlagGenerator::class);
        config(['app.flag_secret' => 'testing-flag-secret-do-not-use-in-prod']);
    }

    public function test_correct_dynamic_flag_awards_xp_and_solve(): void
    {
        $user = User::factory()->create(['xp_total' => 0, 'streak_count' => 0]);
        $challenge = $this->publishedChallenge(['points' => 100]);
        $flag = $this->flags->generate($user, $challenge);

        $response = $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $flag]);

        $response->assertOk()
            ->assertJson([
                'status' => 'solved',
                'points' => 100,
                'xp_total' => 100,
                'streak_count' => 1,
            ]);

        $this->assertDatabaseHas('solves', [
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'points_awarded' => 100,
        ]);

        $this->assertDatabaseHas('flag_submissions', [
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'submitted_flag' => $flag,
            'is_correct' => true,
        ]);

        $user->refresh();
        $challenge->refresh();

        $this->assertSame(100, $user->xp_total);
        $this->assertSame(1, $user->streak_count);
        $this->assertNotNull($user->last_solve_date);
        $this->assertSame(1, $challenge->solve_count);
    }

    public function test_wrong_flag_is_recorded_without_award(): void
    {
        $user = User::factory()->create(['xp_total' => 0]);
        $challenge = $this->publishedChallenge(['points' => 300]);

        $response = $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", [
                'flag' => 'HTP{definitely_wrong_flag_xx}',
            ]);

        $response->assertOk()->assertJson(['status' => 'wrong']);

        $this->assertDatabaseCount('solves', 0);
        $this->assertDatabaseHas('flag_submissions', [
            'user_id' => $user->id,
            'is_correct' => false,
        ]);

        $user->refresh();
        $this->assertSame(0, $user->xp_total);
        $this->assertSame(0, $challenge->fresh()->solve_count);
    }

    public function test_static_flag_challenge_accepts_static_value(): void
    {
        $user = User::factory()->create();
        $static = 'HTP{static_tutorial_flag_0001}';
        $challenge = $this->publishedChallenge([
            'flag_type' => Challenge::FLAG_STATIC,
            'static_flag' => $static,
            'points' => 100,
        ]);

        // Dynamic flag for this user must NOT work on a static challenge.
        $dynamic = $this->flags->generate($user, $challenge);
        $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $dynamic])
            ->assertOk()
            ->assertJson(['status' => 'wrong']);

        $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $static])
            ->assertOk()
            ->assertJson(['status' => 'solved', 'points' => 100]);
    }

    public function test_double_solve_is_idempotent(): void
    {
        $user = User::factory()->create(['xp_total' => 0]);
        $challenge = $this->publishedChallenge(['points' => 100]);
        $flag = $this->flags->generate($user, $challenge);

        $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $flag])
            ->assertOk()
            ->assertJson(['status' => 'solved']);

        $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $flag])
            ->assertOk()
            ->assertJson(['status' => 'already_solved']);

        $this->assertSame(1, Solve::query()->count());
        $this->assertSame(100, $user->fresh()->xp_total);
        $this->assertSame(1, $challenge->fresh()->solve_count);
        $this->assertSame(2, FlagSubmission::query()->where('is_correct', true)->count());
    }

    public function test_rate_limit_trips_after_30_per_challenge_per_hour(): void
    {
        $user = User::factory()->create();
        $challenge = $this->publishedChallenge();

        FlagSubmission::factory()->count(30)->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'is_correct' => false,
            'created_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", [
                'flag' => 'HTP{rate_limit_probe}',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => 'rate_limited',
                'error' => 'rate_limited',
            ]);

        // The rate-limited attempt itself must not be recorded.
        $this->assertSame(30, FlagSubmission::query()->where('user_id', $user->id)->count());
    }

    public function test_global_rate_limit_trips_after_100_across_challenges(): void
    {
        $user = User::factory()->create();
        $challengeA = $this->publishedChallenge(['slug' => 'rate-a']);
        $challengeB = $this->publishedChallenge(['slug' => 'rate-b']);

        FlagSubmission::factory()->count(100)->create([
            'user_id' => $user->id,
            'challenge_id' => $challengeA->id,
            'is_correct' => false,
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/challenges/{$challengeB->slug}/submit", [
                'flag' => 'HTP{global_probe}',
            ]);

        $response->assertStatus(429)->assertJson(['status' => 'rate_limited']);
    }

    public function test_other_users_dynamic_flag_is_rejected(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $challenge = $this->publishedChallenge();

        $ownerFlag = $this->flags->generate($owner, $challenge);

        $this->actingAs($attacker)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $ownerFlag])
            ->assertOk()
            ->assertJson(['status' => 'wrong']);

        $this->assertDatabaseCount('solves', 0);
    }

    public function test_challenge_view_is_recorded_and_used_for_time_to_solve(): void
    {
        $user = User::factory()->create();
        $challenge = $this->publishedChallenge();

        $this->actingAs($user)->get("/challenges/{$challenge->slug}")->assertOk();

        $this->assertDatabaseHas('challenge_views', [
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
        ]);

        // Backdate first view so time_to_solve is measurable.
        ChallengeView::query()
            ->where('user_id', $user->id)
            ->where('challenge_id', $challenge->id)
            ->update(['first_viewed_at' => now()->subMinutes(5)]);

        $flag = $this->flags->generate($user, $challenge);
        $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $flag])
            ->assertOk()
            ->assertJson(['status' => 'solved']);

        $solve = Solve::query()->firstOrFail();
        $this->assertNotNull($solve->time_to_solve_seconds);
        $this->assertGreaterThanOrEqual(5 * 60 - 5, $solve->time_to_solve_seconds);
    }

    public function test_unverified_user_cannot_submit(): void
    {
        $user = User::factory()->unverified()->create();
        $challenge = $this->publishedChallenge();

        $this->actingAs($user)
            ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => 'HTP{x}'])
            ->assertForbidden();
    }

    public function test_flag_leak_detection_marks_shared_wrong_flag(): void
    {
        $challenge = $this->publishedChallenge();
        $shared = 'HTP{leakedsharedflagvalue0001}';

        // Three distinct users submit the same wrong flag.
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create(['username' => "leaker{$i}"]);
            $this->actingAs($user)
                ->postJson("/challenges/{$challenge->slug}/submit", ['flag' => $shared])
                ->assertOk()
                ->assertJson(['status' => 'wrong']);
        }

        $this->assertDatabaseHas('leaked_flags', [
            'flag_value' => $shared,
            'distinct_submitters' => 3,
        ]);

        $this->assertTrue(LeakedFlag::query()->where('flag_value', $shared)->exists());
    }

    public function test_signup_ip_cap_blocks_sixth_account(): void
    {
        $ip = '203.0.113.50';

        for ($i = 1; $i <= 5; $i++) {
            User::factory()->create([
                'username' => "capped{$i}",
                'email' => "capped{$i}@example.com",
                'signup_ip' => $ip,
                'created_at' => now(),
            ]);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('register.store'), [
                'username' => 'capped6',
                'display_name' => 'Cap Six',
                'email' => 'capped6@example.com',
                'locale' => 'ru',
                'password' => 'password1234',
                'password_confirmation' => 'password1234',
                'h-captcha-response' => 'dev-bypass',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull(User::query()->where('username', 'capped6')->first());
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
