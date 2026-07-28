<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Writeup;
use App\Services\MarkdownRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writeup moderation queue (plan §8 screen 4). Moderators + admins approve or
 * reject pending writeups; every decision writes an audit-log entry.
 */
class WriteupModerationController extends Controller
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', ...Writeup::STATUSES])],
        ]);

        $status = $filters['status'] ?? Writeup::STATUS_PENDING;

        $query = Writeup::query()
            ->with([
                'user:id,username,display_name,avatar_color',
                'challenge:id,slug',
                'challenge.translations',
                'moderator:id,username',
            ])
            ->orderByRaw('case when status = ? then 0 else 1 end', [Writeup::STATUS_PENDING])
            ->latest('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $writeups = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/writeups/Index', [
            'writeups' => [
                'data' => $writeups->getCollection()->map(fn (Writeup $w): array => [
                    'id' => $w->id,
                    'status' => $w->status,
                    'locale' => $w->locale,
                    'content_html' => $this->markdown->toHtml($w->content),
                    'moderation_note' => $w->moderation_note,
                    'upvote_count' => $w->upvote_count,
                    'author' => [
                        'username' => $w->user?->username,
                        'display_name' => $w->user?->display_name,
                        'avatar_color' => $w->user?->avatar_color,
                        'url' => $w->user !== null ? route('profile.show', $w->user->username) : null,
                    ],
                    'challenge' => [
                        'title' => $w->challenge?->tr('title') ?? $w->challenge?->slug,
                        'url' => $w->challenge !== null ? route('challenges.show', $w->challenge->slug) : null,
                    ],
                    'moderator' => $w->moderator?->username,
                    'created_at' => $w->created_at?->toIso8601String(),
                    'moderated_at' => $w->moderated_at?->toIso8601String(),
                    'approve_url' => route('admin.writeups.approve', $w->id),
                    'reject_url' => route('admin.writeups.reject', $w->id),
                ])->all(),
                'meta' => [
                    'current_page' => $writeups->currentPage(),
                    'last_page' => $writeups->lastPage(),
                    'total' => $writeups->total(),
                ],
                'links' => [
                    'prev' => $writeups->previousPageUrl(),
                    'next' => $writeups->nextPageUrl(),
                ],
            ],
            'filters' => ['status' => $status],
            'options' => ['statuses' => ['all', ...Writeup::STATUSES]],
        ]);
    }

    public function approve(Request $request, Writeup $writeup): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->moderate($request, $writeup, Writeup::STATUS_APPROVED, $validated['note'] ?? null);

        $this->toast(__('Writeup approved.'));

        return back();
    }

    public function reject(Request $request, Writeup $writeup): RedirectResponse
    {
        // A rejection must explain itself so the author can fix and resubmit.
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $this->moderate($request, $writeup, Writeup::STATUS_REJECTED, $validated['note']);

        $this->toast(__('Writeup rejected.'));

        return back();
    }

    private function moderate(Request $request, Writeup $writeup, string $status, ?string $note): void
    {
        /** @var User $user */
        $user = $request->user();

        $writeup->update([
            'status' => $status,
            'moderator_id' => $user->id,
            'moderation_note' => $note,
            'moderated_at' => now(),
        ]);

        AuditLog::record(
            $status === Writeup::STATUS_APPROVED
                ? AuditLog::ACTION_WRITEUP_APPROVE
                : AuditLog::ACTION_WRITEUP_REJECT,
            $user,
            $writeup,
            ['note' => $note],
        );
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
