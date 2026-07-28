<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeTranslation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Challenge authoring + lifecycle (plan §8 screens 2-3, §9 editor).
 *
 * Authors create/edit their own drafts and submit them for review; admins can
 * touch any challenge and drive the draft → review → published → archived state
 * machine. Coarse role gating lives on the routes; ownership + transition rules
 * are enforced here. Every state change writes an audit-log entry.
 */
class ChallengeController extends Controller
{
    private const STATUS_FILTERS = ['all', 'draft', 'review', 'published', 'archived'];

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(self::STATUS_FILTERS)],
            'category' => ['nullable', 'string', Rule::in(Challenge::CATEGORIES)],
            'q' => ['nullable', 'string', 'max:64'],
        ]);

        $status = $filters['status'] ?? 'all';
        $search = trim($filters['q'] ?? '');

        $query = Challenge::query()
            ->with(['translations', 'author:id,username'])
            ->withCount(['files', 'solves'])
            ->latest('updated_at');

        // Authors only manage their own challenges (plan §8).
        if (! $user->isAdmin()) {
            $query->where('author_id', $user->id);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('slug', 'like', "%{$search}%")
                    ->orWhereHas('translations', fn ($t) => $t->where('title', 'like', "%{$search}%"));
            });
        }

        $challenges = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/challenges/Index', [
            'challenges' => [
                'data' => $challenges->getCollection()->map(fn (Challenge $c): array => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'title' => $c->tr('title') ?? $c->slug,
                    'category' => $c->category,
                    'difficulty' => $c->difficulty,
                    'points' => $c->points,
                    'status' => $c->status,
                    'flag_type' => $c->flag_type,
                    'author' => $c->author?->username,
                    'solve_count' => $c->solve_count,
                    'files_count' => $c->files_count,
                    'locales' => $c->translations->pluck('locale')->all(),
                    'updated_at' => $c->updated_at?->toIso8601String(),
                    'edit_url' => route('admin.challenges.edit', $c->slug),
                    'public_url' => $c->status === Challenge::STATUS_PUBLISHED
                        ? route('challenges.show', $c->slug)
                        : null,
                ])->all(),
                'meta' => [
                    'current_page' => $challenges->currentPage(),
                    'last_page' => $challenges->lastPage(),
                    'total' => $challenges->total(),
                ],
                'links' => [
                    'prev' => $challenges->previousPageUrl(),
                    'next' => $challenges->nextPageUrl(),
                ],
            ],
            'filters' => [
                'status' => $status,
                'category' => $filters['category'] ?? null,
                'q' => $search,
            ],
            'options' => [
                'statuses' => self::STATUS_FILTERS,
                'categories' => Challenge::CATEGORIES,
            ],
            'create_url' => route('admin.challenges.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/challenges/Edit', [
            'challenge' => null,
            'store_url' => route('admin.challenges.store'),
            ...$this->editorOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $this->validateMetadata($request, null);

        $challenge = Challenge::query()->create([
            ...$this->metadataAttributes($validated),
            'author_id' => $user->id,
            'status' => Challenge::STATUS_DRAFT,
        ]);

        AuditLog::record(AuditLog::ACTION_CHALLENGE_CREATE, $user, $challenge, [
            'slug' => $challenge->slug,
        ]);

        $this->toast(__('Challenge draft created. Add descriptions and files below.'));

        return redirect()->route('admin.challenges.edit', $challenge->slug);
    }

    public function edit(Request $request, Challenge $challenge): Response
    {
        $this->authorizeChallenge($request, $challenge);

        $challenge->load(['translations', 'files', 'author:id,username']);

        return Inertia::render('admin/challenges/Edit', [
            'challenge' => $this->challengePayload($request, $challenge),
            'update_url' => route('admin.challenges.update', $challenge->slug),
            ...$this->editorOptions(),
        ]);
    }

    public function update(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->authorizeChallenge($request, $challenge);

        $validated = $this->validateMetadata($request, $challenge);

        $request->validate([
            'translations' => ['array'],
            'translations.*.title' => ['nullable', 'string', 'max:128'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.hint_1' => ['nullable', 'string'],
            'translations.*.hint_2' => ['nullable', 'string'],
        ]);

        $challenge->update($this->metadataAttributes($validated));

        $this->saveTranslations($challenge, (array) $request->input('translations', []));

        /** @var User $user */
        $user = $request->user();
        AuditLog::record(AuditLog::ACTION_CHALLENGE_UPDATE, $user, $challenge, [
            'slug' => $challenge->slug,
        ]);

        $this->toast(__('Challenge saved.'));

        return back();
    }

    public function uploadFile(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->authorizeChallenge($request, $challenge);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.(int) (config('challenges.max_file_bytes') / 1024)],
        ]);

        $upload = $validated['file'];
        $filename = $this->sanitizeFilename($upload->getClientOriginalName());
        $storagePath = $challenge->slug.'/'.$filename;

        $upload->storeAs($challenge->slug, $filename, ['disk' => config('challenges.disk')]);

        ChallengeFile::query()->updateOrCreate(
            ['challenge_id' => $challenge->id, 'filename' => $filename],
            [
                'storage_path' => $storagePath,
                'size_bytes' => $upload->getSize(),
                'mime_type' => Str::limit((string) $upload->getMimeType(), 96, ''),
            ],
        );

        $this->toast(__('File uploaded.'));

        return back();
    }

    public function destroyFile(Request $request, Challenge $challenge, ChallengeFile $file): RedirectResponse
    {
        $this->authorizeChallenge($request, $challenge);
        abort_unless($file->challenge_id === $challenge->id, 404);

        $file->disk()->delete($file->storage_path);
        $file->delete();

        $this->toast(__('File removed.'));

        return back();
    }

    public function submitForReview(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->authorizeChallenge($request, $challenge);
        $this->assertTransition($challenge, [Challenge::STATUS_DRAFT]);

        $challenge->update(['status' => Challenge::STATUS_REVIEW]);

        /** @var User $user */
        $user = $request->user();
        AuditLog::record(AuditLog::ACTION_CHALLENGE_SUBMIT_REVIEW, $user, $challenge);

        $this->toast(__('Submitted for review.'));

        return back();
    }

    public function publish(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->assertTransition($challenge, [
            Challenge::STATUS_DRAFT,
            Challenge::STATUS_REVIEW,
            Challenge::STATUS_ARCHIVED,
        ]);

        $this->assertPublishable($challenge);

        $challenge->update([
            'status' => Challenge::STATUS_PUBLISHED,
            'published_at' => $challenge->published_at ?? now(),
        ]);

        /** @var User $user */
        $user = $request->user();
        AuditLog::record(AuditLog::ACTION_CHALLENGE_PUBLISH, $user, $challenge);

        $this->toast(__('Challenge published.'));

        return back();
    }

    public function unpublish(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->assertTransition($challenge, [Challenge::STATUS_PUBLISHED]);

        $challenge->update(['status' => Challenge::STATUS_DRAFT]);

        /** @var User $user */
        $user = $request->user();
        AuditLog::record(AuditLog::ACTION_CHALLENGE_UNPUBLISH, $user, $challenge);

        $this->toast(__('Challenge unpublished (back to draft).'));

        return back();
    }

    public function archive(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->assertTransition($challenge, [
            Challenge::STATUS_DRAFT,
            Challenge::STATUS_REVIEW,
            Challenge::STATUS_PUBLISHED,
        ]);

        $challenge->update(['status' => Challenge::STATUS_ARCHIVED]);

        /** @var User $user */
        $user = $request->user();
        AuditLog::record(AuditLog::ACTION_CHALLENGE_ARCHIVE, $user, $challenge);

        $this->toast(__('Challenge archived.'));

        return back();
    }

    /**
     * Authors may only touch their own challenges; admins may touch any.
     */
    private function authorizeChallenge(Request $request, Challenge $challenge): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isAdmin() || $challenge->author_id === $user->id, 403);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertTransition(Challenge $challenge, array $allowed): void
    {
        if (! in_array($challenge->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('That action is not allowed from the ":status" state.', [
                    'status' => $challenge->status,
                ]),
            ]);
        }
    }

    private function assertPublishable(Challenge $challenge): void
    {
        $hasContent = $challenge->translations()
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->exists();

        if (! $hasContent) {
            throw ValidationException::withMessages([
                'status' => __('Add at least one complete translation (title + description) before publishing.'),
            ]);
        }

        if ($challenge->usesStaticFlag() && ($challenge->static_flag === null || $challenge->static_flag === '')) {
            throw ValidationException::withMessages([
                'status' => __('A static challenge needs a flag value before publishing.'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMetadata(Request $request, ?Challenge $challenge): array
    {
        return $request->validate([
            'slug' => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('challenges', 'slug')->ignore($challenge?->id),
            ],
            'category' => ['required', Rule::in(Challenge::CATEGORIES)],
            'difficulty' => ['required', Rule::in(Challenge::DIFFICULTIES)],
            'flag_type' => ['required', Rule::in([Challenge::FLAG_STATIC, Challenge::FLAG_DYNAMIC])],
            'static_flag' => ['nullable', 'string', 'max:128', 'required_if:flag_type,'.Challenge::FLAG_STATIC],
            'flag_format' => ['nullable', 'string', 'max:128'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function metadataAttributes(array $validated): array
    {
        $difficulty = $validated['difficulty'];

        return [
            'slug' => $validated['slug'],
            'category' => $validated['category'],
            'difficulty' => $difficulty,
            // Points are derived from difficulty, never entered by hand (plan §9).
            'points' => Challenge::DIFFICULTY_POINTS[$difficulty],
            'flag_type' => $validated['flag_type'],
            'static_flag' => $validated['flag_type'] === Challenge::FLAG_STATIC
                ? ($validated['static_flag'] ?? null)
                : null,
            'flag_format' => ! empty($validated['flag_format'])
                ? $validated['flag_format']
                : 'HTP\{[a-zA-Z0-9_]+\}',
        ];
    }

    /**
     * @param  array<string, array<string, string|null>>  $translations
     */
    private function saveTranslations(Challenge $challenge, array $translations): void
    {
        foreach (User::LOCALES as $locale) {
            $row = $translations[$locale] ?? [];
            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));

            // A locale needs both title and description to exist; clearing both
            // removes the translation so it stops appearing to users.
            if ($title === '' || $description === '') {
                $challenge->translations()->where('locale', $locale)->delete();

                continue;
            }

            ChallengeTranslation::query()->updateOrCreate(
                ['challenge_id' => $challenge->id, 'locale' => $locale],
                [
                    'title' => $title,
                    'description' => $description,
                    'hint_1' => $this->nullableTrim($row['hint_1'] ?? null),
                    'hint_2' => $this->nullableTrim($row['hint_2'] ?? null),
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function challengePayload(Request $request, Challenge $challenge): array
    {
        /** @var User $user */
        $user = $request->user();

        $translations = [];
        foreach ($challenge->translations as $t) {
            $translations[$t->locale] = [
                'title' => $t->title,
                'description' => $t->description,
                'hint_1' => $t->hint_1,
                'hint_2' => $t->hint_2,
            ];
        }

        return [
            'id' => $challenge->id,
            'slug' => $challenge->slug,
            'category' => $challenge->category,
            'difficulty' => $challenge->difficulty,
            'points' => $challenge->points,
            'status' => $challenge->status,
            'flag_type' => $challenge->flag_type,
            'static_flag' => $challenge->static_flag,
            'flag_format' => $challenge->flag_format,
            'author' => $challenge->author?->username,
            'solve_count' => $challenge->solve_count,
            'published_at' => $challenge->published_at?->toIso8601String(),
            'translations' => $translations,
            'files' => $challenge->files->map(fn (ChallengeFile $f): array => [
                'id' => $f->id,
                'filename' => $f->filename,
                'size_bytes' => $f->size_bytes,
                'mime_type' => $f->mime_type,
                'delete_url' => route('admin.challenges.files.destroy', [$challenge->slug, $f->id]),
            ])->all(),
            'public_url' => $challenge->status === Challenge::STATUS_PUBLISHED
                ? route('challenges.show', $challenge->slug)
                : null,
            'upload_url' => route('admin.challenges.files.store', $challenge->slug),
            'actions' => $this->actionsFor($challenge, $user),
        ];
    }

    /**
     * State-machine buttons available to this user for this challenge.
     *
     * @return array<string, string|null>
     */
    private function actionsFor(Challenge $challenge, User $user): array
    {
        $isAdmin = $user->isAdmin();
        $status = $challenge->status;

        return [
            'submit_review' => $status === Challenge::STATUS_DRAFT
                ? route('admin.challenges.submit', $challenge->slug)
                : null,
            'publish' => $isAdmin && in_array($status, [
                Challenge::STATUS_DRAFT,
                Challenge::STATUS_REVIEW,
                Challenge::STATUS_ARCHIVED,
            ], true) ? route('admin.challenges.publish', $challenge->slug) : null,
            'unpublish' => $isAdmin && $status === Challenge::STATUS_PUBLISHED
                ? route('admin.challenges.unpublish', $challenge->slug)
                : null,
            'archive' => $isAdmin && $status !== Challenge::STATUS_ARCHIVED
                ? route('admin.challenges.archive', $challenge->slug)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorOptions(): array
    {
        return [
            'options' => [
                'categories' => Challenge::CATEGORIES,
                'difficulties' => Challenge::DIFFICULTIES,
                'difficulty_points' => Challenge::DIFFICULTY_POINTS,
                'flag_types' => [Challenge::FLAG_STATIC, Challenge::FLAG_DYNAMIC],
                'locales' => User::LOCALES,
            ],
        ];
    }

    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        $filename = $extension !== '' ? "{$base}.".strtolower($extension) : $base;

        return Str::limit($filename, 128, '');
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
