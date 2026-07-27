<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeTranslation;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Solve;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    // --- Posting gate -----------------------------------------------------

    public function test_guest_cannot_post_comment(): void
    {
        $challenge = $this->publishedChallenge();

        $this->post(route('comments.store', $challenge->slug), ['content' => 'hi'])
            ->assertRedirect();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_unsolved_user_cannot_post_comment(): void
    {
        $challenge = $this->publishedChallenge();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), ['content' => 'spoiler?'])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_solver_posts_comment(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), ['content' => 'nice challenge'])
            ->assertRedirect(route('challenges.show', $challenge->slug));

        $this->assertDatabaseHas('comments', [
            'challenge_id' => $challenge->id,
            'user_id' => $user->id,
            'parent_id' => null,
            'content' => 'nice challenge',
        ]);
    }

    public function test_comment_requires_content(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), ['content' => ''])
            ->assertSessionHasErrors('content');
    }

    // --- Rate limit -------------------------------------------------------

    public function test_rate_limit_blocks_sixth_comment_within_hour(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        Comment::factory()->count(Comment::RATE_LIMIT_PER_HOUR)->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'created_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), ['content' => 'one too many'])
            ->assertSessionHasErrors('content');

        $this->assertSame(
            Comment::RATE_LIMIT_PER_HOUR,
            Comment::query()->where('user_id', $user->id)->count(),
        );
    }

    public function test_comments_older_than_an_hour_do_not_count(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        Comment::factory()->count(Comment::RATE_LIMIT_PER_HOUR)->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'created_at' => now()->subHours(2),
        ]);

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), ['content' => 'fresh'])
            ->assertRedirect();

        $this->assertSame(
            Comment::RATE_LIMIT_PER_HOUR + 1,
            Comment::query()->where('user_id', $user->id)->count(),
        );
    }

    // --- 1-level nesting --------------------------------------------------

    public function test_reply_to_top_level_comment_is_created(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);
        $parent = Comment::factory()->create(['challenge_id' => $challenge->id]);

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), [
                'content' => 'replying',
                'parent_id' => $parent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'parent_id' => $parent->id,
            'content' => 'replying',
        ]);
    }

    public function test_cannot_reply_to_a_reply(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);
        $parent = Comment::factory()->create(['challenge_id' => $challenge->id]);
        $reply = Comment::factory()->replyTo($parent)->create();

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), [
                'content' => 'deep',
                'parent_id' => $reply->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_reply_across_challenges(): void
    {
        $challenge = $this->publishedChallenge();
        $other = $this->publishedChallenge(['slug' => 'other-chal']);
        $user = $this->solver($challenge);
        $foreignParent = Comment::factory()->create(['challenge_id' => $other->id]);

        $this->actingAs($user)
            ->post(route('comments.store', $challenge->slug), [
                'content' => 'wrong thread',
                'parent_id' => $foreignParent->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    // --- Visibility -------------------------------------------------------

    public function test_comments_locked_for_non_solvers(): void
    {
        $challenge = $this->publishedChallenge();
        Comment::factory()->create(['challenge_id' => $challenge->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('challenges.show', $challenge->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.locked', true)
                ->has('comments.items', 0),
            );
    }

    public function test_solver_sees_thread_without_hidden_comments(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $visible = Comment::factory()->create(['challenge_id' => $challenge->id]);
        Comment::factory()->replyTo($visible)->create();
        Comment::factory()->hidden()->create(['challenge_id' => $challenge->id]);

        $this->actingAs($user)
            ->get(route('challenges.show', $challenge->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.locked', false)
                ->has('comments.items', 1)
                ->has('comments.items.0.replies', 1),
            );
    }

    // --- Reports ----------------------------------------------------------

    public function test_solver_reports_a_comment(): void
    {
        $challenge = $this->publishedChallenge();
        $reporter = $this->solver($challenge);
        $comment = Comment::factory()->create(['challenge_id' => $challenge->id]);

        $this->actingAs($reporter)
            ->post(route('comments.report', $comment), ['reason' => 'spoiler'])
            ->assertRedirect(route('challenges.show', $challenge->slug));

        $this->assertDatabaseHas('comment_reports', [
            'comment_id' => $comment->id,
            'reporter_id' => $reporter->id,
            'reason' => 'spoiler',
            'status' => CommentReport::STATUS_OPEN,
        ]);
    }

    public function test_cannot_report_own_comment(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);
        $comment = Comment::factory()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('comments.report', $comment), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('comment_reports', 0);
    }

    public function test_report_is_idempotent_per_user(): void
    {
        $challenge = $this->publishedChallenge();
        $reporter = $this->solver($challenge);
        $comment = Comment::factory()->create(['challenge_id' => $challenge->id]);

        $this->actingAs($reporter)->post(route('comments.report', $comment), ['reason' => 'a']);
        $this->actingAs($reporter)->post(route('comments.report', $comment), ['reason' => 'b']);

        $this->assertDatabaseCount('comment_reports', 1);
    }

    public function test_non_solver_cannot_report(): void
    {
        $challenge = $this->publishedChallenge();
        $comment = Comment::factory()->create(['challenge_id' => $challenge->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('comments.report', $comment), ['reason' => 'x'])
            ->assertForbidden();
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function publishedChallenge(array $attrs = []): Challenge
    {
        $challenge = Challenge::factory()->create(array_merge([
            'status' => Challenge::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $attrs));

        ChallengeTranslation::factory()->for($challenge)->create([
            'locale' => 'en',
            'title' => $challenge->slug,
            'description' => 'body',
        ]);

        return $challenge;
    }

    private function solver(Challenge $challenge): User
    {
        $user = User::factory()->create();
        Solve::factory()->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'points_awarded' => $challenge->points,
        ]);

        return $user;
    }
}
