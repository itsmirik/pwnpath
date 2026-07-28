<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->author()->create();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_author_creates_a_draft(): void
    {
        $author = $this->author();

        $this->actingAs($author)
            ->post(route('admin.challenges.store'), [
                'slug' => 'my-first-web',
                'category' => 'web',
                'difficulty' => 'medium',
                'flag_type' => 'static',
                'static_flag' => 'HTP{hello}',
                'flag_format' => '',
            ])
            ->assertRedirect(route('admin.challenges.edit', 'my-first-web'));

        $this->assertDatabaseHas('challenges', [
            'slug' => 'my-first-web',
            'status' => 'draft',
            'points' => 300,
            'author_id' => $author->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_CHALLENGE_CREATE,
            'actor_id' => $author->id,
        ]);
    }

    public function test_static_challenge_requires_a_flag(): void
    {
        $this->actingAs($this->author())
            ->post(route('admin.challenges.store'), [
                'slug' => 'no-flag',
                'category' => 'web',
                'difficulty' => 'easy',
                'flag_type' => 'static',
                'static_flag' => '',
            ])
            ->assertSessionHasErrors('static_flag');

        $this->assertDatabaseMissing('challenges', ['slug' => 'no-flag']);
    }

    public function test_update_saves_metadata_and_translations(): void
    {
        $author = $this->author();
        $challenge = Challenge::factory()->draft()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->put(route('admin.challenges.update', $challenge->slug), [
                'slug' => $challenge->slug,
                'category' => 'crypto',
                'difficulty' => 'hard',
                'flag_type' => 'static',
                'static_flag' => 'HTP{x}',
                'flag_format' => 'HTP\{.*\}',
                'translations' => [
                    'ru' => ['title' => 'Заголовок', 'description' => 'Описание', 'hint_1' => 'подсказка', 'hint_2' => null],
                    'en' => ['title' => '', 'description' => '', 'hint_1' => null, 'hint_2' => null],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'category' => 'crypto',
            'difficulty' => 'hard',
            'points' => 700,
        ]);

        $this->assertDatabaseHas('challenge_translations', [
            'challenge_id' => $challenge->id,
            'locale' => 'ru',
            'title' => 'Заголовок',
        ]);

        // Empty locale must not be persisted.
        $this->assertDatabaseMissing('challenge_translations', [
            'challenge_id' => $challenge->id,
            'locale' => 'en',
        ]);
    }

    public function test_author_cannot_edit_another_authors_challenge(): void
    {
        $challenge = Challenge::factory()->draft()->create(['author_id' => $this->author()->id]);

        $this->actingAs($this->author())
            ->get(route('admin.challenges.edit', $challenge->slug))
            ->assertForbidden();
    }

    public function test_admin_can_edit_any_challenge(): void
    {
        $challenge = Challenge::factory()->draft()->create(['author_id' => $this->author()->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.challenges.edit', $challenge->slug))
            ->assertOk();
    }

    public function test_author_submits_for_review_but_cannot_publish(): void
    {
        $author = $this->author();
        $challenge = Challenge::factory()->draft()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->post(route('admin.challenges.submit', $challenge->slug))
            ->assertRedirect();

        $this->assertSame('review', $challenge->fresh()->status);

        $this->actingAs($author)
            ->post(route('admin.challenges.publish', $challenge->slug))
            ->assertForbidden();
    }

    public function test_publish_requires_content(): void
    {
        $challenge = Challenge::factory()->draft()->staticFlag()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.challenges.publish', $challenge->slug))
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $challenge->fresh()->status);
    }

    public function test_admin_publishes_a_ready_challenge(): void
    {
        $admin = $this->admin();
        $challenge = Challenge::factory()->draft()->staticFlag()->create();
        ChallengeTranslation::factory()->create([
            'challenge_id' => $challenge->id,
            'locale' => 'ru',
            'title' => 'Ready',
            'description' => 'Body',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.challenges.publish', $challenge->slug))
            ->assertRedirect();

        $fresh = $challenge->fresh();
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_CHALLENGE_PUBLISH,
            'actor_id' => $admin->id,
            'entity_id' => $challenge->id,
        ]);
    }

    public function test_admin_unpublishes_and_archives(): void
    {
        $admin = $this->admin();
        $challenge = Challenge::factory()->create(); // published by default

        $this->actingAs($admin)
            ->post(route('admin.challenges.unpublish', $challenge->slug))
            ->assertRedirect();
        $this->assertSame('draft', $challenge->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.challenges.archive', $challenge->slug))
            ->assertRedirect();
        $this->assertSame('archived', $challenge->fresh()->status);
    }

    public function test_file_upload_and_delete(): void
    {
        Storage::fake('challenges');

        $author = $this->author();
        $challenge = Challenge::factory()->draft()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->post(route('admin.challenges.files.store', $challenge->slug), [
                'file' => UploadedFile::fake()->create('dump.txt', 10, 'text/plain'),
            ])
            ->assertRedirect();

        Storage::disk('challenges')->assertExists($challenge->slug.'/dump.txt');
        $this->assertDatabaseHas('challenge_files', [
            'challenge_id' => $challenge->id,
            'filename' => 'dump.txt',
        ]);

        $file = ChallengeFile::query()->where('challenge_id', $challenge->id)->firstOrFail();

        $this->actingAs($author)
            ->delete(route('admin.challenges.files.destroy', [$challenge->slug, $file->id]))
            ->assertRedirect();

        Storage::disk('challenges')->assertMissing($challenge->slug.'/dump.txt');
        $this->assertDatabaseMissing('challenge_files', ['id' => $file->id]);
    }
}
