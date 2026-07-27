<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Writeup;
use App\Models\WriteupVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WriteupVoteController extends Controller
{
    /**
     * Upvote a writeup. Only solvers of the challenge may vote, and not on
     * their own writeup (plan §5: "Vote (up only) by other solvers").
     */
    public function store(Request $request, Writeup $writeup): RedirectResponse
    {
        $user = $this->authorizeVoter($request, $writeup);

        DB::transaction(function () use ($writeup, $user): void {
            /** @var Writeup $locked */
            $locked = Writeup::query()->whereKey($writeup->id)->lockForUpdate()->firstOrFail();

            $vote = WriteupVote::query()->firstOrCreate([
                'writeup_id' => $locked->id,
                'user_id' => $user->id,
            ]);

            if ($vote->wasRecentlyCreated) {
                $locked->increment('upvote_count');
            }
        });

        return $this->back($writeup);
    }

    /**
     * Remove the current user's upvote.
     */
    public function destroy(Request $request, Writeup $writeup): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($writeup, $user): void {
            /** @var Writeup $locked */
            $locked = Writeup::query()->whereKey($writeup->id)->lockForUpdate()->firstOrFail();

            $deleted = WriteupVote::query()
                ->where('writeup_id', $locked->id)
                ->where('user_id', $user->id)
                ->delete();

            if ($deleted > 0 && $locked->upvote_count > 0) {
                $locked->decrement('upvote_count');
            }
        });

        return $this->back($writeup);
    }

    private function authorizeVoter(Request $request, Writeup $writeup): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($writeup->isApproved(), 403);
        abort_if($writeup->user_id === $user->id, 403);
        abort_unless($user->hasSolved($writeup->challenge), 403);

        return $user;
    }

    private function back(Writeup $writeup): RedirectResponse
    {
        return redirect()->route('challenges.show', $writeup->challenge->slug);
    }
}
