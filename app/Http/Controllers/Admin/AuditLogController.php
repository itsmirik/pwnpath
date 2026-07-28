<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit log viewer (plan §8 screen 7, admin only). Read-only, filterable by
 * actor, action and date range.
 */
class AuditLogController extends Controller
{
    /** Known action slugs, for the filter dropdown. */
    private const ACTIONS = [
        AuditLog::ACTION_CHALLENGE_CREATE,
        AuditLog::ACTION_CHALLENGE_UPDATE,
        AuditLog::ACTION_CHALLENGE_SUBMIT_REVIEW,
        AuditLog::ACTION_CHALLENGE_PUBLISH,
        AuditLog::ACTION_CHALLENGE_UNPUBLISH,
        AuditLog::ACTION_CHALLENGE_ARCHIVE,
        AuditLog::ACTION_CHALLENGE_SYNC,
        AuditLog::ACTION_WRITEUP_APPROVE,
        AuditLog::ACTION_WRITEUP_REJECT,
        AuditLog::ACTION_COMMENT_HIDE,
        AuditLog::ACTION_REPORT_DISMISS,
        AuditLog::ACTION_USER_ROLE_CHANGE,
        AuditLog::ACTION_USER_BAN,
        AuditLog::ACTION_USER_UNBAN,
        AuditLog::ACTION_SECURITY_SUSPICIOUS_SOLVES,
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'actor' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->with('actor:id,username')
            ->latest('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['actor'])) {
            $actor = $filters['actor'];
            $query->whereHas('actor', fn ($q) => $q->where('username', 'like', "%{$actor}%"));
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', "{$filters['from']} 00:00:00");
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', "{$filters['to']} 23:59:59");
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('admin/audit/Index', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor?->username,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'meta' => $log->meta,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->all(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                ],
                'links' => [
                    'prev' => $logs->previousPageUrl(),
                    'next' => $logs->nextPageUrl(),
                ],
            ],
            'filters' => [
                'action' => $filters['action'] ?? null,
                'actor' => $filters['actor'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'options' => ['actions' => self::ACTIONS],
        ]);
    }
}
