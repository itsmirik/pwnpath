<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\User;
use App\Services\ChallengeRepoSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSyncTest extends TestCase
{
    use RefreshDatabase;

    private ?string $repo = null;

    protected function tearDown(): void
    {
        if ($this->repo !== null && is_dir($this->repo)) {
            File::deleteDirectory($this->repo);
        }

        parent::tearDown();
    }

    /**
     * Build a throwaway challenges repo working copy with one valid challenge.
     */
    private function makeRepo(bool $valid = true): string
    {
        $base = sys_get_temp_dir().'/htp_sync_'.uniqid();
        $folder = $base.'/010-web-demo';
        File::ensureDirectoryExists($folder.'/files');
        File::ensureDirectoryExists($folder.'/hints');

        $category = $valid ? 'web' : ''; // empty category = invalid schema

        File::put($folder.'/challenge.yml', <<<YAML
        slug: web-demo
        category: {$category}
        difficulty: medium
        flag_type: static
        static_flag: HTP{demo_flag}
        title:
          ru: Демо
          en: Demo
        YAML);

        File::put($folder.'/ru.md', 'Описание демонстрационного задания.');
        File::put($folder.'/en.md', 'Demo challenge description.');
        File::put($folder.'/hints/ru-1.md', 'Мягкая подсказка.');
        File::put($folder.'/files/dump.txt', 'sample data');

        $this->repo = $base;

        return $base;
    }

    public function test_service_imports_a_valid_challenge_as_draft(): void
    {
        Storage::fake('challenges');
        $path = $this->makeRepo();

        $report = app(ChallengeRepoSync::class)->sync($path);

        $this->assertSame(['web-demo'], $report['created']);
        $this->assertSame([], $report['errors']);

        $this->assertDatabaseHas('challenges', [
            'slug' => 'web-demo',
            'status' => 'draft',
            'points' => 300,
            'flag_type' => 'static',
            'static_flag' => 'HTP{demo_flag}',
        ]);

        $this->assertDatabaseHas('challenge_translations', [
            'locale' => 'ru',
            'title' => 'Демо',
        ]);

        $this->assertDatabaseHas('challenge_files', ['filename' => 'dump.txt']);
        Storage::disk('challenges')->assertExists('web-demo/dump.txt');
    }

    public function test_re_sync_updates_without_republishing(): void
    {
        Storage::fake('challenges');
        $path = $this->makeRepo();
        $sync = app(ChallengeRepoSync::class);

        $sync->sync($path);
        Challenge::query()->where('slug', 'web-demo')->update(['status' => 'published']);

        $report = $sync->sync($path);

        $this->assertSame(['web-demo'], $report['updated']);
        $this->assertSame('published', Challenge::query()->where('slug', 'web-demo')->value('status'));
    }

    public function test_invalid_folder_is_reported_and_skipped(): void
    {
        Storage::fake('challenges');
        $path = $this->makeRepo(valid: false);

        $report = app(ChallengeRepoSync::class)->sync($path);

        $this->assertNotEmpty($report['errors']);
        $this->assertDatabaseMissing('challenges', ['slug' => 'web-demo']);
    }

    public function test_sync_endpoint_is_admin_only(): void
    {
        $this->actingAs(User::factory()->author()->create())
            ->post(route('admin.sync'))
            ->assertForbidden();
    }

    public function test_admin_triggers_sync_and_it_is_audited(): void
    {
        Storage::fake('challenges');
        $path = $this->makeRepo();
        config()->set('challenges.source_path', $path);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.sync'))
            ->assertRedirect();

        $this->assertDatabaseHas('challenges', ['slug' => 'web-demo']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_CHALLENGE_SYNC,
            'actor_id' => $admin->id,
        ]);
    }
}
