<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChallengeBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_published_challenges_with_localised_titles(): void
    {
        $this->makeChallenge('easy-web', 'web', 'easy', 100, [
            'en' => ['title' => 'Cookie Monster', 'description' => 'find the cookie'],
            'ru' => ['title' => 'Куки-монстр', 'description' => 'найди куку'],
        ]);
        $this->makeChallenge('hard-crypto', 'crypto', 'hard', 700, [
            'en' => ['title' => 'RSA Broken', 'description' => 'break RSA'],
        ]);
        $draft = Challenge::factory()->draft()->create(['slug' => 'draft-hidden']);
        ChallengeTranslation::factory()->for($draft)->create(['locale' => 'en', 'title' => 'HIDDEN']);

        $response = $this->withHeaders(['Accept-Language' => 'ru'])->get('/challenges');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('challenges/Index')
            ->has('challenges.data', 2)
            ->where('challenges.data.0.slug', fn ($slug) => in_array($slug, ['easy-web', 'hard-crypto']))
            ->where('options.categories.0', 'web'),
        );

        $response->assertDontSee('HIDDEN');
    }

    public function test_index_filters_by_category(): void
    {
        $this->makeChallenge('web-1', 'web', 'easy', 100, ['en' => ['title' => 'Web One']]);
        $this->makeChallenge('crypto-1', 'crypto', 'easy', 100, ['en' => ['title' => 'Crypto One']]);

        $response = $this->get('/challenges?category=web');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('challenges.data', 1)
            ->where('challenges.data.0.slug', 'web-1')
            ->where('filters.category', 'web'),
        );
    }

    public function test_index_filters_by_difficulty(): void
    {
        $this->makeChallenge('hard-1', 'web', 'hard', 700, ['en' => ['title' => 'Hard One']]);
        $this->makeChallenge('easy-1', 'web', 'easy', 100, ['en' => ['title' => 'Easy One']]);

        $response = $this->get('/challenges?difficulty=hard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('challenges.data', 1)
            ->where('challenges.data.0.slug', 'hard-1'),
        );
    }

    public function test_index_rejects_invalid_filter_value(): void
    {
        $this->get('/challenges?category=not-a-category')->assertSessionHasErrors('category');
    }

    public function test_index_sort_points_desc(): void
    {
        $this->makeChallenge('a', 'web', 'easy', 100, ['en' => ['title' => 'A']]);
        $this->makeChallenge('b', 'web', 'hard', 700, ['en' => ['title' => 'B']]);
        $this->makeChallenge('c', 'web', 'medium', 300, ['en' => ['title' => 'C']]);

        $response = $this->get('/challenges?sort=points_desc');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('challenges.data.0.slug', 'b')
            ->where('challenges.data.1.slug', 'c')
            ->where('challenges.data.2.slug', 'a'),
        );
    }

    public function test_show_renders_challenge_with_hints_locked_for_guest(): void
    {
        $this->makeChallenge('web-1', 'web', 'easy', 100, [
            'en' => [
                'title' => 'Hint Guard',
                'description' => 'find flag',
                'hint_1' => 'peek at cookies',
                'hint_2' => 'try admin',
            ],
        ]);

        $response = $this->get('/challenges/web-1');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('challenges/Show')
            ->where('challenge.title', 'Hint Guard')
            ->where('challenge.hints_locked', true)
            ->has('challenge.hints', 0),
        );
    }

    public function test_show_reveals_hints_and_download_urls_for_auth_user(): void
    {
        $challenge = $this->makeChallenge('web-2', 'web', 'easy', 100, [
            'en' => [
                'title' => 'Hint Unlocked',
                'description' => 'go',
                'hint_1' => 'try harder',
            ],
        ]);
        ChallengeFile::factory()->for($challenge)->create([
            'filename' => 'dump.txt',
            'storage_path' => 'web-2/dump.txt',
            'size_bytes' => 42,
            'mime_type' => 'text/plain',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/challenges/web-2');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('challenge.hints_locked', false)
            ->has('challenge.hints', 1)
            ->where('challenge.hints.0', 'try harder')
            ->has('challenge.files', 1)
            ->where('challenge.files.0.filename', 'dump.txt')
            ->where('challenge.files.0.download_url', fn ($url) => is_string($url) && str_contains($url, '/challenges/web-2/files/')),
        );
    }

    public function test_show_returns_404_for_draft(): void
    {
        $draft = Challenge::factory()->draft()->create(['slug' => 'not-live']);
        ChallengeTranslation::factory()->for($draft)->create(['locale' => 'en']);

        $this->get('/challenges/not-live')->assertNotFound();
    }

    public function test_submit_stub_returns_501_for_verified_user(): void
    {
        $this->makeChallenge('web-1', 'web', 'easy', 100, ['en' => ['title' => 'Stub']]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/challenges/web-1/submit', ['flag' => 'HTP{deadbeef}']);

        $response->assertStatus(501)
            ->assertJson(['error' => 'not_implemented']);
    }

    public function test_submit_requires_auth(): void
    {
        $this->makeChallenge('web-1', 'web', 'easy', 100, ['en' => ['title' => 'Guard']]);

        $this->postJson('/challenges/web-1/submit', ['flag' => 'HTP{}'])
            ->assertStatus(401);
    }

    public function test_signed_file_download_streams_local_disk(): void
    {
        $root = storage_path('framework/testing/challenges-'.uniqid());
        config()->set('filesystems.disks.challenges', [
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
        ]);

        Storage::disk('challenges')->put('web-1/dump.txt', 'flagfile-content');

        $challenge = $this->makeChallenge('web-1', 'web', 'easy', 100, ['en' => ['title' => 'DL']]);
        ChallengeFile::factory()->for($challenge)->create([
            'filename' => 'dump.txt',
            'storage_path' => 'web-1/dump.txt',
            'size_bytes' => 16,
            'mime_type' => 'text/plain',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/challenges/web-1');
        $response->assertOk();
        $downloadUrl = $response->viewData('page')['props']['challenge']['files'][0]['download_url'];
        $this->assertIsString($downloadUrl);

        $download = $this->actingAs($user)->get($downloadUrl);
        $download->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('content-disposition') ?? '');
        $this->assertStringContainsString('dump.txt', $download->headers->get('content-disposition') ?? '');
        $this->assertSame('flagfile-content', $download->streamedContent());

        Storage::disk('challenges')->deleteDirectory('web-1');
    }

    /**
     * @param  array<string, array<string, string|null>>  $translations
     */
    private function makeChallenge(
        string $slug,
        string $category,
        string $difficulty,
        int $points,
        array $translations,
    ): Challenge {
        $challenge = Challenge::factory()->create([
            'slug' => $slug,
            'category' => $category,
            'difficulty' => $difficulty,
            'points' => $points,
        ]);

        foreach ($translations as $locale => $fields) {
            ChallengeTranslation::factory()->for($challenge)->create(array_merge(
                ['locale' => $locale, 'title' => $slug, 'description' => 'body', 'hint_1' => null, 'hint_2' => null],
                $fields,
            ));
        }

        return $challenge;
    }
}
