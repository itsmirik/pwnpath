<?php

namespace App\Services;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeTranslation;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * "Sync from repo" pipeline (plan §9).
 *
 * Reads the challenges git working copy, validates each folder's challenge.yml,
 * upserts DB rows, and mirrors files onto the challenges disk. Never
 * auto-publishes: new challenges land as drafts, existing ones keep their
 * status, and an admin publishes them explicitly afterward.
 *
 * Folder layout (one folder per challenge):
 *   <slug>/
 *     challenge.yml        category, difficulty, flag_type, flag_format, title{}
 *     ru.md / uz.md / en.md  per-locale description
 *     hints/<locale>-1.md    optional soft hint
 *     hints/<locale>-2.md    optional strong hint
 *     files/*                attachments
 *     flag.template          static flag value (fallback to yml static_flag)
 */
class ChallengeRepoSync
{
    /** Default readable-flag pattern (matches ChallengeSeeder). */
    private const DEFAULT_FLAG_FORMAT = 'HTP\{[a-zA-Z0-9_]+\}';

    /**
     * @return array{
     *   created: list<string>,
     *   updated: list<string>,
     *   errors: array<string, string>,
     *   pulled: bool,
     *   path: string
     * }
     */
    public function sync(?string $path = null): array
    {
        $path = rtrim($path ?? (string) config('challenges.source_path'), '/');

        $report = [
            'created' => [],
            'updated' => [],
            'errors' => [],
            'pulled' => false,
            'path' => $path,
        ];

        if (! is_dir($path)) {
            $report['errors']['_repo'] = "Challenge source path not found: {$path}";

            return $report;
        }

        if (config('challenges.git_pull') && is_dir($path.'/.git')) {
            $report['pulled'] = $this->gitPull($path, $report);
        }

        foreach ($this->challengeFolders($path) as $folder) {
            try {
                $result = $this->syncFolder($folder);
                $report[$result['action']][] = $result['slug'];
            } catch (Throwable $e) {
                $report['errors'][basename($folder)] = $e->getMessage();
            }
        }

        sort($report['created']);
        sort($report['updated']);

        return $report;
    }

    /**
     * @return array{action: 'created'|'updated', slug: string}
     */
    private function syncFolder(string $folder): array
    {
        $ymlPath = is_file($folder.'/challenge.yml')
            ? $folder.'/challenge.yml'
            : $folder.'/challenge.yaml';

        if (! is_file($ymlPath)) {
            throw new RuntimeException('Missing challenge.yml');
        }

        /** @var array<string, mixed> $meta */
        $meta = Yaml::parseFile($ymlPath) ?: [];

        $slug = $this->resolveSlug($meta, $folder);
        $category = $this->requireEnum($meta, 'category', Challenge::CATEGORIES);
        $difficulty = $this->requireEnum($meta, 'difficulty', Challenge::DIFFICULTIES);

        $flagType = isset($meta['flag_type'])
            ? $this->requireEnum($meta, 'flag_type', [Challenge::FLAG_STATIC, Challenge::FLAG_DYNAMIC])
            : Challenge::FLAG_STATIC;

        $staticFlag = $this->resolveStaticFlag($meta, $folder, $flagType);

        $flagFormat = is_string($meta['flag_format'] ?? null) && $meta['flag_format'] !== ''
            ? (string) $meta['flag_format']
            : self::DEFAULT_FLAG_FORMAT;

        $translations = $this->collectTranslations($folder, $meta, $slug);

        if ($translations === []) {
            throw new RuntimeException('No locale content found (add at least one <locale>.md)');
        }

        $files = $this->collectFiles($folder);

        $existing = Challenge::query()->where('slug', $slug)->first();
        $action = $existing === null ? 'created' : 'updated';

        $attrs = [
            'category' => $category,
            'difficulty' => $difficulty,
            'points' => Challenge::DIFFICULTY_POINTS[$difficulty],
            'flag_type' => $flagType,
            'static_flag' => $flagType === Challenge::FLAG_STATIC ? $staticFlag : null,
            'flag_format' => $flagFormat,
            'author_id' => $this->resolveAuthorId($meta) ?? $existing?->author_id,
        ];

        // New challenges start as drafts (plan §9). Existing challenges keep
        // whatever status they already have so re-syncing never unpublishes.
        if ($existing === null) {
            $attrs['status'] = Challenge::STATUS_DRAFT;
        }

        $challenge = Challenge::query()->updateOrCreate(['slug' => $slug], $attrs);

        foreach ($translations as $locale => $tr) {
            ChallengeTranslation::query()->updateOrCreate(
                ['challenge_id' => $challenge->id, 'locale' => $locale],
                $tr,
            );
        }

        $this->syncFiles($challenge, $files);

        return ['action' => $action, 'slug' => $slug];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveSlug(array $meta, string $folder): string
    {
        if (is_string($meta['slug'] ?? null) && $meta['slug'] !== '') {
            return Str::slug((string) $meta['slug']);
        }

        // Strip an optional ordering prefix like "001-" from the folder name.
        $name = preg_replace('/^\d+[-_]/', '', basename($folder)) ?? basename($folder);

        return Str::slug($name);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $allowed
     */
    private function requireEnum(array $meta, string $key, array $allowed): string
    {
        $value = is_string($meta[$key] ?? null) ? strtolower((string) $meta[$key]) : '';

        if (! in_array($value, $allowed, true)) {
            throw new RuntimeException(sprintf(
                "Invalid or missing '%s' (allowed: %s)",
                $key,
                implode(', ', $allowed),
            ));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveStaticFlag(array $meta, string $folder, string $flagType): ?string
    {
        if ($flagType !== Challenge::FLAG_STATIC) {
            return null;
        }

        if (is_string($meta['static_flag'] ?? null) && $meta['static_flag'] !== '') {
            return trim((string) $meta['static_flag']);
        }

        $template = $folder.'/flag.template';
        if (is_file($template)) {
            $value = trim((string) file_get_contents($template));
            if ($value !== '') {
                return $value;
            }
        }

        throw new RuntimeException('Static challenge needs static_flag in challenge.yml or a flag.template file');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, array{title: string, description: string, hint_1: string|null, hint_2: string|null}>
     */
    private function collectTranslations(string $folder, array $meta, string $slug): array
    {
        /** @var array<string, string> $titles */
        $titles = is_array($meta['title'] ?? null) ? $meta['title'] : [];

        $out = [];

        foreach (User::LOCALES as $locale) {
            $descPath = $folder.'/'.$locale.'.md';

            if (! is_file($descPath)) {
                continue;
            }

            $description = trim((string) file_get_contents($descPath));

            if ($description === '') {
                continue;
            }

            $title = is_string($titles[$locale] ?? null) && $titles[$locale] !== ''
                ? (string) $titles[$locale]
                : Str::headline($slug);

            $out[$locale] = [
                'title' => mb_substr($title, 0, 128),
                'description' => $description,
                'hint_1' => $this->readHint($folder, $locale, 1),
                'hint_2' => $this->readHint($folder, $locale, 2),
            ];
        }

        return $out;
    }

    private function readHint(string $folder, string $locale, int $n): ?string
    {
        $path = sprintf('%s/hints/%s-%d.md', $folder, $locale, $n);

        if (! is_file($path)) {
            return null;
        }

        $value = trim((string) file_get_contents($path));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, array{path: string, mime: string, size: int}>
     */
    private function collectFiles(string $folder): array
    {
        $dir = $folder.'/files';

        if (! is_dir($dir)) {
            return [];
        }

        $maxFile = (int) config('challenges.max_file_bytes');
        $maxTotal = (int) config('challenges.max_total_bytes');

        $out = [];
        $total = 0;

        foreach ((array) glob($dir.'/*') as $path) {
            if (! is_file($path)) {
                continue;
            }

            $filename = basename($path);
            $size = (int) filesize($path);

            if ($size > $maxFile) {
                throw new RuntimeException(sprintf(
                    "File '%s' is %s, over the %s per-file limit",
                    $filename,
                    $this->humanBytes($size),
                    $this->humanBytes($maxFile),
                ));
            }

            $total += $size;

            $out[$filename] = [
                'path' => $path,
                'mime' => mime_content_type($path) ?: 'application/octet-stream',
                'size' => $size,
            ];
        }

        if ($total > $maxTotal) {
            throw new RuntimeException(sprintf(
                'Attachments total %s, over the %s per-challenge limit',
                $this->humanBytes($total),
                $this->humanBytes($maxTotal),
            ));
        }

        return $out;
    }

    /**
     * @param  array<string, array{path: string, mime: string, size: int}>  $files
     */
    private function syncFiles(Challenge $challenge, array $files): void
    {
        $disk = Storage::disk((string) config('challenges.disk'));

        foreach ($files as $filename => $file) {
            $storagePath = $challenge->slug.'/'.$filename;

            $disk->put($storagePath, (string) file_get_contents($file['path']));

            ChallengeFile::query()->updateOrCreate(
                ['challenge_id' => $challenge->id, 'filename' => $filename],
                [
                    'mime_type' => $file['mime'],
                    'storage_path' => $storagePath,
                    'size_bytes' => $file['size'],
                ],
            );
        }

        // Prune DB rows + disk objects for files removed from the repo.
        $keep = array_keys($files);

        $stale = $challenge->files()
            ->when($keep !== [], fn ($q) => $q->whereNotIn('filename', $keep))
            ->get();

        foreach ($stale as $row) {
            $disk->delete($row->storage_path);
            $row->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveAuthorId(array $meta): ?int
    {
        $username = is_string($meta['author'] ?? null) ? trim((string) $meta['author']) : '';

        if ($username === '') {
            return null;
        }

        return User::query()->where('username', $username)->value('id');
    }

    /**
     * @return list<string>
     */
    private function challengeFolders(string $path): array
    {
        $folders = [];

        foreach ((array) glob($path.'/*', GLOB_ONLYDIR) as $dir) {
            if (is_file($dir.'/challenge.yml') || is_file($dir.'/challenge.yaml')) {
                $folders[] = $dir;
            }
        }

        return $folders;
    }

    /**
     * @param  array{errors: array<string, string>, ...}  $report
     */
    private function gitPull(string $path, array &$report): bool
    {
        try {
            $result = Process::path($path)->run('git pull --ff-only');

            if (! $result->successful()) {
                $report['errors']['_git'] = trim($result->errorOutput()) ?: 'git pull failed';

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $report['errors']['_git'] = $e->getMessage();

            return false;
        }
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
