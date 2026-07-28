<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\CommentReport;
use App\Models\Solve;
use App\Models\User;
use App\Models\Writeup;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Admin home (plan §8 screen 1): moderation backlog + this-week activity,
     * plus a recent audit feed.
     */
    public function index(): Response
    {
        $weekStart = now()->startOfWeek();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'pending_writeups' => Writeup::query()->where('status', Writeup::STATUS_PENDING)->count(),
                'open_reports' => CommentReport::query()->where('status', CommentReport::STATUS_OPEN)->count(),
                'draft_challenges' => Challenge::query()->where('status', Challenge::STATUS_DRAFT)->count(),
                'review_challenges' => Challenge::query()->where('status', Challenge::STATUS_REVIEW)->count(),
                'signups_this_week' => User::query()->where('created_at', '>=', $weekStart)->count(),
                'solves_this_week' => Solve::query()->where('created_at', '>=', $weekStart)->count(),
                'flagged_accounts' => AuditLog::query()
                    ->forAction(AuditLog::ACTION_SECURITY_SUSPICIOUS_SOLVES)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->distinct()
                    ->count('entity_id'),
            ],
            'recent_activity' => AuditLog::query()
                ->with('actor:id,username')
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor?->username,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }
}
