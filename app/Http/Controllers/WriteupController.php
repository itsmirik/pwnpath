<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\User;
use App\Models\Writeup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WriteupController extends Controller
{
    /**
     * Writeup editor. Solvers only — you must beat a challenge before you can
     * write it up (plan §5). Pre-fills the user's existing writeup for editing.
     */
    public function create(Request $request, Challenge $challenge): Response
    {
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasSolved($challenge), 403);

        $locale = app()->getLocale();
        $challenge->load('translations');

        $existing = $challenge->writeups()->where('user_id', $user->id)->first();

        return Inertia::render('writeups/Create', [
            'challenge' => [
                'slug' => $challenge->slug,
                'title' => $challenge->tr('title', $locale) ?? $challenge->slug,
                'url' => route('challenges.show', $challenge->slug),
            ],
            'existing' => $existing === null ? null : [
                'content' => $existing->content,
                'locale' => $existing->locale,
                'status' => $existing->status,
                'moderation_note' => $existing->moderation_note,
            ],
            'store_url' => route('writeups.store', $challenge->slug),
            'image_upload_url' => route('writeups.images.store'),
            'locales' => User::LOCALES,
            'default_locale' => $existing->locale ?? $locale,
            'max_content_chars' => (int) config('writeups.max_content_chars'),
        ]);
    }

    /**
     * Create or replace the user's writeup for a challenge. Always re-enters
     * the moderation queue as pending (plan §5: mod approves before public).
     */
    public function store(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_unless($challenge->status === Challenge::STATUS_PUBLISHED, 404);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasSolved($challenge), 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:'.(int) config('writeups.max_content_chars')],
            'locale' => ['nullable', 'string', Rule::in(User::LOCALES)],
        ]);

        $challenge->writeups()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'content' => $validated['content'],
                'locale' => $validated['locale'] ?? app()->getLocale(),
                'status' => Writeup::STATUS_PENDING,
                // Reset moderation state so edited writeups requeue for review.
                'moderator_id' => null,
                'moderation_note' => null,
                'moderated_at' => null,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Writeup submitted for review.'),
        ]);

        return redirect()->route('challenges.show', $challenge->slug);
    }
}
