<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeFile;
use App\Models\ChallengeView;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Solve;
use App\Models\User;
use App\Models\Writeup;
use App\Models\WriteupVote;
use App\Services\MarkdownRenderer;
use App\Services\SubmitFlagService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChallengeController extends Controller
{
    private const SORT_OPTIONS = ['newest', 'oldest', 'points_desc', 'points_asc', 'solves_desc'];

    private const SOLVED_FILTERS = ['all', 'solved', 'unsolved'];

    public function __construct(
        private readonly SubmitFlagService $submitFlag,
        private readonly MarkdownRenderer $markdown,
    ) {}

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
        $user = $request->user();

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

        if ($user !== null && $solvedFilter !== 'all') {
            $solvedSub = Solve::query()
                ->select('challenge_id')
                ->where('user_id', $user->id);

            if ($solvedFilter === 'solved') {
                $query->whereIn('id', $solvedSub);
            } else {
                $query->whereNotIn('id', $solvedSub);
            }
        }

        match ($sort) {
            'oldest' => $query->orderBy('published_at'),
            'points_desc' => $query->orderByDesc('points')->orderByDesc('published_at'),
            'points_asc' => $query->orderBy('points')->orderByDesc('published_at'),
            'solves_desc' => $query->orderByDesc('solve_count')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };

        $challenges = $query->paginate(24)->withQueryString();

        $solvedIds = [];
        if ($user !== null && $challenges->isNotEmpty()) {
            $solvedIds = Solve::query()
                ->where('user_id', $user->id)
                ->whereIn('challenge_id', $challenges->getCollection()->pluck('id'))
                ->pluck('challenge_id')
                ->all();
        }

        return Inertia::render('challenges/Index', [
            'challenges' => [
                'data' => $challenges->getCollection()->map(fn (Challenge $c) => [
                    'slug' => $c->slug,
                    'title' => $c->tr('title', $locale) ?? $c->slug,
                    'category' => $c->category,
                    'difficulty' => $c->difficulty,
                    'points' => $c->points,
                    'solve_count' => $c->solve_count,
                    'is_solved' => in_array($c->id, $solvedIds, true),
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
        $user = $request->user();
        $isAuthed = $user !== null;

        if ($isAuthed) {
            ChallengeView::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                ],
                ['first_viewed_at' => now()],
            );
        }

        $isSolved = $isAuthed && $user->hasSolved($challenge);

        return Inertia::render('challenges/Show', [
            'challenge' => [
                'slug' => $challenge->slug,
                'title' => $challenge->tr('title', $locale) ?? $challenge->slug,
                'description_html' => $this->markdown->toHtml($challenge->tr('description', $locale) ?? ''),
                'category' => $challenge->category,
                'difficulty' => $challenge->difficulty,
                'points' => $challenge->points,
                'solve_count' => $challenge->solve_count,
                'flag_format' => $challenge->flag_format,
                'published_at' => $challenge->published_at?->toIso8601String(),
                'is_solved' => $isSolved,
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
                'can_submit' => $isAuthed && ! $isSolved,
                'submit_url' => route('challenges.submit', $challenge->slug),
            ],
            'writeups' => $this->writeupsPayload($challenge, $user, $isSolved),
            'comments' => $this->commentsPayload($challenge, $user, $isSolved),
        ]);
    }

    public function submit(Request $request, Challenge $challenge): JsonResponse
    {
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'flag' => ['required', 'string', 'max:256'],
        ]);

        $result = $this->submitFlag->submit($user, $challenge, $validated['flag'], $request);

        if ($result['status'] === 'rate_limited') {
            return response()->json($result, 429);
        }

        return response()->json($result);
    }

    /**
     * Approved writeups (public to everyone) plus the current user's own
     * writeup + submit-gate state (solvers only).
     *
     * @return array<string, mixed>
     */
    private function writeupsPayload(Challenge $challenge, ?User $user, bool $isSolved): array
    {
        $writeups = $challenge->writeups()
            ->approved()
            ->with('user')
            ->orderByDesc('upvote_count')
            ->orderByDesc('created_at')
            ->get();

        $votedIds = [];
        if ($user !== null && $writeups->isNotEmpty()) {
            $votedIds = WriteupVote::query()
                ->where('user_id', $user->id)
                ->whereIn('writeup_id', $writeups->pluck('id'))
                ->pluck('writeup_id')
                ->all();
        }

        $mine = $user !== null
            ? $challenge->writeups()->where('user_id', $user->id)->first()
            : null;

        return [
            'items' => $writeups->map(fn (Writeup $w): array => [
                'id' => $w->id,
                'author' => $this->authorPayload($w->user),
                'content_html' => $this->markdown->toHtml($w->content),
                'upvote_count' => $w->upvote_count,
                'has_upvoted' => in_array($w->id, $votedIds, true),
                // Solvers may vote on writeups other than their own (plan §5).
                'can_upvote' => $isSolved && $user !== null && $w->user_id !== $user->id,
                'upvote_url' => route('writeups.upvote', $w->id),
                'created_at' => $w->created_at?->toIso8601String(),
            ])->all(),
            'can_write' => $isSolved,
            'create_url' => route('writeups.create', $challenge->slug),
            'mine' => $mine === null ? null : [
                'status' => $mine->status,
                'moderation_note' => $mine->moderation_note,
                'updated_at' => $mine->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Comment thread. Gated behind solving to avoid pre-solve spoilers
     * (plan §5: solved-only). Non-solvers get a locked marker only.
     *
     * @return array<string, mixed>
     */
    private function commentsPayload(Challenge $challenge, ?User $user, bool $isSolved): array
    {
        if (! $isSolved || $user === null) {
            return [
                'locked' => true,
                'can_post' => false,
                'post_url' => route('comments.store', $challenge->slug),
                'items' => [],
            ];
        }

        $comments = $challenge->comments()
            ->visible()
            ->topLevel()
            ->with([
                'user',
                'replies' => fn ($q) => $q->visible()->with('user')->orderBy('created_at'),
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $commentIds = $comments->pluck('id')
            ->merge($comments->flatMap(fn (Comment $c) => $c->replies->pluck('id')))
            ->all();

        $reportedIds = CommentReport::query()
            ->where('reporter_id', $user->id)
            ->whereIn('comment_id', $commentIds)
            ->pluck('comment_id')
            ->all();

        return [
            'locked' => false,
            'can_post' => true,
            'post_url' => route('comments.store', $challenge->slug),
            'items' => $comments->map(fn (Comment $c): array => [
                ...$this->commentPayload($c, $user, $reportedIds),
                'replies' => $c->replies->map(
                    fn (Comment $r): array => $this->commentPayload($r, $user, $reportedIds),
                )->all(),
            ])->all(),
        ];
    }

    /**
     * @param  list<int>  $reportedIds
     * @return array<string, mixed>
     */
    private function commentPayload(Comment $comment, User $user, array $reportedIds): array
    {
        $isOwn = $comment->user_id === $user->id;

        return [
            'id' => $comment->id,
            'author' => $this->authorPayload($comment->user),
            'content' => $comment->content,
            'created_at' => $comment->created_at?->toIso8601String(),
            'is_own' => $isOwn,
            'can_report' => ! $isOwn,
            'has_reported' => in_array($comment->id, $reportedIds, true),
            'report_url' => route('comments.report', $comment->id),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function authorPayload(?User $user): array
    {
        if ($user === null) {
            return [
                'username' => null,
                'display_name' => null,
                'avatar_color' => null,
                'url' => null,
            ];
        }

        return [
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_color' => $user->avatar_color,
            'url' => route('profile.show', $user->username),
        ];
    }
}
