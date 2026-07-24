<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class ChallengeController extends Controller
{
    private const SORT_OPTIONS = ['newest', 'oldest', 'points_desc', 'points_asc', 'solves_desc'];

    private const SOLVED_FILTERS = ['all', 'solved', 'unsolved'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', Rule::in(Challenge::CATEGORIES)],
            'difficulty' => ['nullable', 'string', Rule::in(Challenge::DIFFICULTIES)],
            'solved' => ['nullable', 'string', Rule::in(self::SOLVED_FILTERS)],
            'sort' => ['nullable', 'string', Rule::in(self::SORT_OPTIONS)],
        ]);

        $locale = app()->getLocale();
        $sort = $filters['sort'] ?? 'newest';
        $solvedFilter = $filters['solved'] ?? 'all';

        /** @var Builder<Challenge> $query */
        $query = Challenge::query()
            ->published()
            ->with(['translations']);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        // Phase 4 hooks solved/unsolved to the solves table. For now the filter
        // is accepted but returns the full list — plumbing so UI is stable.
        // if ($request->user() && $solvedFilter !== 'all') { ... }

        match ($sort) {
            'oldest' => $query->orderBy('published_at'),
            'points_desc' => $query->orderByDesc('points')->orderByDesc('published_at'),
            'points_asc' => $query->orderBy('points')->orderByDesc('published_at'),
            'solves_desc' => $query->orderByDesc('solve_count')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };

        $challenges = $query->paginate(24)->withQueryString();

        return Inertia::render('challenges/Index', [
            'challenges' => [
                'data' => $challenges->getCollection()->map(fn (Challenge $c) => [
                    'slug' => $c->slug,
                    'title' => $c->tr('title', $locale) ?? $c->slug,
                    'category' => $c->category,
                    'difficulty' => $c->difficulty,
                    'points' => $c->points,
                    'solve_count' => $c->solve_count,
                    'url' => route('challenges.show', $c->slug),
                ])->all(),
                'meta' => [
                    'current_page' => $challenges->currentPage(),
                    'last_page' => $challenges->lastPage(),
                    'total' => $challenges->total(),
                    'per_page' => $challenges->perPage(),
                ],
                'links' => [
                    'prev' => $challenges->previousPageUrl(),
                    'next' => $challenges->nextPageUrl(),
                ],
            ],
            'filters' => [
                'category' => $filters['category'] ?? null,
                'difficulty' => $filters['difficulty'] ?? null,
                'solved' => $solvedFilter,
                'sort' => $sort,
            ],
            'options' => [
                'categories' => Challenge::CATEGORIES,
                'difficulties' => Challenge::DIFFICULTIES,
                'solved' => self::SOLVED_FILTERS,
                'sort' => self::SORT_OPTIONS,
            ],
        ]);
    }

    public function show(Request $request, Challenge $challenge): Response
    {
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        $locale = app()->getLocale();
        $challenge->load(['translations', 'files']);
        $isAuthed = $request->user() !== null;

        return Inertia::render('challenges/Show', [
            'challenge' => [
                'slug' => $challenge->slug,
                'title' => $challenge->tr('title', $locale) ?? $challenge->slug,
                'description_html' => $this->renderMarkdown($challenge->tr('description', $locale) ?? ''),
                'category' => $challenge->category,
                'difficulty' => $challenge->difficulty,
                'points' => $challenge->points,
                'solve_count' => $challenge->solve_count,
                'flag_format' => $challenge->flag_format,
                'published_at' => $challenge->published_at?->toIso8601String(),
                'hints' => $isAuthed
                    ? array_values(array_filter([
                        $challenge->tr('hint_1', $locale),
                        $challenge->tr('hint_2', $locale),
                    ]))
                    : [],
                'hints_locked' => ! $isAuthed,
                'files' => $challenge->files->map(fn (ChallengeFile $f) => [
                    'id' => $f->id,
                    'filename' => $f->filename,
                    'size_bytes' => $f->size_bytes,
                    'mime_type' => $f->mime_type,
                    'download_url' => $isAuthed
                        ? URL::signedRoute(
                            'challenges.files.download',
                            ['challenge' => $challenge->slug, 'file' => $f->id],
                            now()->addMinutes(15),
                        )
                        : null,
                ])->all(),
                'can_submit' => $isAuthed,
                'submit_url' => route('challenges.submit', $challenge->slug),
            ],
        ]);
    }

    public function submit(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        // Phase 4: dynamic-flag validation + rate limits.
        return response()->json([
            'error' => 'not_implemented',
            'message' => 'Flag submission ships in Phase 4.',
        ], 501);
    }

    private function renderMarkdown(string $md): string
    {
        // html_input=escape drops any inline <script>/<iframe> authored content — safe against XSS.
        $env = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new AutolinkExtension);

        return (string) (new MarkdownConverter($env))->convert($md);
    }
}
