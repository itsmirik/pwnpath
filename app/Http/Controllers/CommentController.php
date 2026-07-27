<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CommentController extends Controller
{
    /**
     * Post a comment (or a 1-level reply) on a challenge thread. Solvers only,
     * to avoid pre-solve spoiler farming (plan §5). Rate limited per user.
     */
    public function store(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasSolved($challenge), 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:'.Comment::MAX_LENGTH],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $this->assertWithinRateLimit($user);

        $parentId = $this->resolveParentId($challenge, $validated['parent_id'] ?? null);

        $challenge->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'content' => $validated['content'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Comment posted.'),
        ]);

        return redirect()->route('challenges.show', $challenge->slug);
    }

    /**
     * @throws ValidationException
     */
    private function assertWithinRateLimit(User $user): void
    {
        $recent = Comment::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($recent >= Comment::RATE_LIMIT_PER_HOUR) {
            throw ValidationException::withMessages([
                'content' => __('You are posting too fast. Try again later.'),
            ]);
        }
    }

    /**
     * Resolve and validate a reply target. Enforces 1-level nesting: you may
     * only reply to a visible top-level comment on the same challenge.
     *
     * @throws ValidationException
     */
    private function resolveParentId(Challenge $challenge, ?int $parentId): ?int
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Comment::query()
            ->where('id', $parentId)
            ->where('challenge_id', $challenge->id)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => __('That comment can no longer be replied to.'),
            ]);
        }

        return $parent->id;
    }
}
