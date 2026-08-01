<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommentReportController extends Controller
{
    /**
     * Flag a comment for moderator review (plan §5: report button → mod queue).
     * One report per user per comment; you cannot report your own.
     */
    public function store(Request $request, Comment $comment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($comment->user_id === $user->id, 403);
        abort_unless($user->hasSolved($comment->challenge), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:128'],
        ]);

        CommentReport::query()->firstOrCreate(
            [
                'comment_id' => $comment->id,
                'reporter_id' => $user->id,
            ],
            [
                'reason' => $validated['reason'],
                'status' => CommentReport::STATUS_OPEN,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report submitted. Thanks for keeping PwnPath clean.'),
        ]);

        return redirect()->route('challenges.show', $comment->challenge->slug);
    }
}
