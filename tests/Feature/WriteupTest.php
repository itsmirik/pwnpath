<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeTranslation;
use App\Models\Solve;
use App\Models\User;
use App\Models\Writeup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WriteupTest extends TestCase
{
    use RefreshDatabase;

    // --- Editor gate ------------------------------------------------------

    public function test_guest_is_redirected_from_editor(): void
    {
        $challenge = $this->publishedChallenge();

        $this->get(route('writeups.create', $challenge->slug))->assertRedirect();
    }

    public function test_unsolved_user_cannot_open_editor(): void
    {
        $challenge = $this->publishedChallenge();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('writeups.create', $challenge->slug))
            ->assertForbidden();
    }

    public function test_solver_can_open_editor(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $this->actingAs($user)
            ->get(route('writeups.create', $challenge->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('writeups/Create')
                ->where('challenge.slug', $challenge->slug)
                ->where('existing', null),
            );
    }

    // --- Submit -----------------------------------------------------------

    public function test_solver_submits_writeup_as_pending(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $this->actingAs($user)
            ->post(route('writeups.store', $challenge->slug), [
                'content' => '# My solve\n\nHere is how.',
                'locale' => 'en',
            ])
            ->assertRedirect(route('challenges.show', $challenge->slug));

        $this->assertDatabaseHas('writeups', [
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'status' => Writeup::STATUS_PENDING,
        ]);
    }

    public function test_unsolved_user_cannot_submit_writeup(): void
    {
        $challenge = $this->publishedChallenge();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('writeups.store', $challenge->slug), ['content' => 'nope'])
            ->assertForbidden();

        $this->assertDatabaseCount('writeups', 0);
    }

    public function test_writeup_requires_content(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $this->actingAs($user)
            ->post(route('writeups.store', $challenge->slug), ['content' => ''])
            ->assertSessionHasErrors('content');
    }

    public function test_resubmit_requeues_and_preserves_votes(): void
    {
        $challenge = $this->publishedChallenge();
        $user = $this->solver($challenge);

        $writeup = Writeup::factory()->approved()->create([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
            'upvote_count' => 4,
            'moderator_id' => User::factory()->create()->id,
            'moderation_note' => 'ok',
        ]);

        $this->actingAs($user)
            ->post(route('writeups.store', $challenge->slug), ['content' => 'edited body'])
            ->assertRedirect();

        $this->assertDatabaseCount('writeups', 1);

        $writeup->refresh();
        $this->assertSame(Writeup::STATUS_PENDING, $writeup->status);
        $this->assertSame('edited body', $writeup->content);
        $this->assertSame(4, $writeup->upvote_count);
        $this->assertNull($writeup->moderator_id);
        $this->assertNull($writeup->moderation_note);
        $this->assertNull($writeup->moderated_at);
    }

    // --- Public listing ---------------------------------------------------

    public function test_approved_writeups_are_public_pending_hidden(): void
    {
        $challenge = $this->publishedChallenge();

        Writeup::factory()->approved()->create([
            'user_id' => User::factory()->create()->id,
            'challenge_id' => $challenge->id,
        ]);
        Writeup::factory()->create([
            'user_id' => User::factory()->create()->id,
            'challenge_id' => $challenge->id,
        ]);

        // Guest — no auth — still sees approved writeups.
        $this->get(route('challenges.show', $challenge->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('writeups.items', 1)
                ->where('writeups.items.0.content_html', fn ($html) => is_string($html) && str_contains($html, '<')),
            );
    }

    // --- Image upload -----------------------------------------------------

    public function test_image_upload_stores_and_returns_url(): void
    {
        $disk = (string) config('writeups.image_disk');
        Storage::fake($disk);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('writeups.images.store'), [
            'image' => UploadedFile::fake()->image('shot.png', 32, 32),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertSame(1, count(Storage::disk($disk)->allFiles('writeups')));
    }

    public function test_image_upload_rejects_non_image(): void
    {
        Storage::fake((string) config('writeups.image_disk'));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('writeups.images.store'), [
                'image' => UploadedFile::fake()->create('malware.txt', 10, 'text/plain'),
            ])
            ->assertStatus(422);
    }

    public function test_image_upload_rejects_oversize(): void
    {
        Storage::fake((string) config('writeups.image_disk'));
        $user = User::factory()->create();

        // 3MB > 2MB cap.
        $this->actingAs($user)
            ->postJson(route('writeups.images.store'), [
                'image' => UploadedFile::fake()->image('big.jpg')->size(3072),
            ])
            ->assertStatus(422);
    }

    public function test_image_upload_requires_auth(): void
    {
        $this->postJson(route('writeups.images.store'), [
            'image' => UploadedFile::fake()->image('x.png'),
        ])->assertUnauthorized();
    }

    // --- Upvotes ----------------------------------------------------------

    public function test_solver_upvotes_another_writeup(): void
    {
        $challenge = $this->publishedChallenge();
        $author = $this->solver($challenge);
        $voter = $this->solver($challenge);

        $writeup = Writeup::factory()->approved()->create([
            'user_id' => $author->id,
            'challenge_id' => $challenge->id,
            'upvote_count' => 0,
        ]);

        $this->actingAs($voter)
            ->post(route('writeups.upvote', $writeup))
            ->assertRedirect(route('challenges.show', $challenge->slug));

        $this->assertSame(1, $writeup->fresh()->upvote_count);
        $this->assertDatabaseHas('writeup_votes', [
            'writeup_id' => $writeup->id,
            'user_id' => $voter->id,
        ]);
    }

    public function test_upvote_is_idempotent(): void
    {
        $challenge = $this->publishedChallenge();
        $writeup = Writeup::factory()->approved()->create([
            'user_id' => $this->solver($challenge)->id,
            'challenge_id' => $challenge->id,
            'upvote_count' => 0,
        ]);
        $voter = $this->solver($challenge);

        $this->actingAs($voter)->post(route('writeups.upvote', $writeup));
        $this->actingAs($voter)->post(route('writeups.upvote', $writeup));

        $this->assertSame(1, $writeup->fresh()->upvote_count);
        $this->assertDatabaseCount('writeup_votes', 1);
    }

    public function test_remove_upvote_decrements(): void
    {
        $challenge = $this->publishedChallenge();
        $writeup = Writeup::factory()->approved()->create([
            'user_id' => $this->solver($challenge)->id,
            'challenge_id' => $challenge->id,
            'upvote_count' => 0,
        ]);
        $voter = $this->solver($challenge);

        $this->actingAs($voter)->post(route('writeups.upvote', $writeup));
        $this->actingAs($voter)->delete(route('writeups.upvote.destroy', $writeup));

        $this->assertSame(0, $writeup->fresh()->upvote_count);
        $this->assertDatabaseCount('writeup_votes', 0);
    }

    public function test_cannot_upvote_own_writeup(): void
    {
        $challenge = $this->publishedChallenge();
        $author = $this->solver($challenge);
        $writeup = Writeup::factory()->approved()->create([
            'user_id' => $author->id,
            'challenge_id' => $challenge->id,
        ]);

        $this->actingAs($author)
            ->post(route('writeups.upvote', $writeup))
            ->assertForbidden();
    }

    public function test_non_solver_cannot_upvote(): void
    {
        $challenge = $this->publishedChallenge();
        $writeup = Writeup::factory()->approved()->create([
            'user_id' => $this->solver($challenge)->id,
            'challenge_id' => $challenge->id,
        ]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('writeups.upvote', $writeup))
            ->assertForbidden();
    }

    public function test_cannot_upvote_pending_writeup(): void
    {
        $challenge = $this->publishedChallenge();
        $writeup = Writeup::factory()->create([
            'user_id' => $this->solver($challenge)->id,
            'challenge_id' => $challenge->id,
        ]);
        $voter = $this->solver($challenge);

        $this->actingAs($voter)
            ->post(route('writeups.upvote', $writeup))
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
