<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function show(User $user): Response
    {
        return Inertia::render('profiles/Show', [
            'profile' => [
                'username' => $user->username,
                'display_name' => $user->display_name,
                'bio' => $user->bio,
                'avatar_color' => $user->avatar_color,
                'country_code' => $user->country_code,
                'locale' => $user->locale,
                'xp_total' => $user->xp_total,
                'streak_count' => $user->streak_count,
                'joined_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }
}
