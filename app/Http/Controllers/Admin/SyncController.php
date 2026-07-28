<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ChallengeRepoSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "Sync from repo" button (plan §9). Admin-only: pulls the challenges repo,
 * validates YAML, upserts DB rows and uploads files, then reports the result.
 */
class SyncController extends Controller
{
    public function store(Request $request, ChallengeRepoSync $sync): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $report = $sync->sync();

        AuditLog::record(AuditLog::ACTION_CHALLENGE_SYNC, $user, null, [
            'created' => $report['created'],
            'updated' => $report['updated'],
            'errors' => $report['errors'],
            'source' => 'dashboard',
        ]);

        $summary = __(':created created, :updated updated, :errors error(s)', [
            'created' => count($report['created']),
            'updated' => count($report['updated']),
            'errors' => count($report['errors']),
        ]);

        // Surface the first failure inline so the admin knows what to fix.
        if ($report['errors'] !== []) {
            $first = array_key_first($report['errors']);
            $summary .= ' — '.$first.': '.$report['errors'][$first];
        }

        Inertia::flash('toast', [
            'type' => $report['errors'] === [] ? 'success' : 'error',
            'message' => __('Sync: :summary', ['summary' => $summary]),
        ]);

        return back();
    }
}
