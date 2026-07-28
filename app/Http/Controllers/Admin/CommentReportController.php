<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comment reports queue (plan §8 screen 5). Moderators + admins see reported
 * comments in context and either hide the comment or dismiss the report.
 */
class CommentReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', CommentReport::STATUS_OPEN, CommentReport::STATUS_RESOLVED])],
        ]);

        $status = $filters['status'] ?? CommentReport::STATUS_OPEN;

        $query = CommentReport::query()
            ->with([
                'reporter:id,username',
                'comment' => fn ($q) => $q->with([
                    'user:id,username,display_name,avatar_color',
                    'challenge:id,slug',
                    'challenge.translations',
                ]),
            ])
            ->latest('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $reports = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/reports/Index', [
            'reports' => [
                'data' => $reports->getCollection()->map(fn (CommentReport $r): array => [
                    'id' => $r->id,
                    'reason' => $r->reason,
                    'status' => $r->status,
                    'reporter' => $r->reporter?->username,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'comment' => $r->comment === null ? null : [
                        'id' => $r->comment->id,
                        'content' => $r->comment->content,
                        'is_hidden' => $r->comment->is_hidden,
                        'is_reply' => $r->comment->parent_id !== null,
                        'author' => [
                            'username' => $r->comment->user?->username,
                            'display_name' => $r->comment->user?->display_name,
                            'avatar_color' => $r->comment->user?->avatar_color,
                        ],
                        'challenge' => [
                            'title' => $r->comment->challenge?->tr('title') ?? $r->comment->challenge?->slug,
                            'url' => $r->comment->challenge !== null
                                ? route('challenges.show', $r->comment->challenge->slug)
                                : null,
                        ],
                        'hide_url' => route('admin.comments.hide', $r->comment->id),
                    ],
                    'dismiss_url' => route('admin.reports.dismiss', $r->id),
                ])->all(),
                'meta' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'total' => $reports->total(),
                ],
                'links' => [
                    'prev' => $reports->previousPageUrl(),
                    'next' => $reports->nextPageUrl(),
                ],
            ],
            'filters' => ['status' => $status],
            'options' => ['statuses' => ['all', CommentReport::STATUS_OPEN, CommentReport::STATUS_RESOLVED]],
        ]);
    }

    /**
     * Hide the comment and resolve every open report against it.
     */
    public function hide(Request $request, Comment $comment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $comment->update(['is_hidden' => true]);

        $comment->reports()
            ->where('status', CommentReport::STATUS_OPEN)
            ->update(['status' => CommentReport::STATUS_RESOLVED]);

        AuditLog::record(AuditLog::ACTION_COMMENT_HIDE, $user, $comment, [
            'challenge_id' => $comment->challenge_id,
        ]);

        $this->toast(__('Comment hidden and reports resolved.'));

        return back();
    }

    /**
     * Dismiss a single report without touching the comment.
     */
    public function dismiss(Request $request, CommentReport $report): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $report->update(['status' => CommentReport::STATUS_RESOLVED]);

        AuditLog::record(AuditLog::ACTION_REPORT_DISMISS, $user, $report, [
            'comment_id' => $report->comment_id,
        ]);

        $this->toast(__('Report dismissed.'));

        return back();
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
