<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|in:'.implode(',', User::LOCALES),
        ]);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $validated['locale']])->save();
        }

        return back()->withCookie(
            Cookie::make('locale', $validated['locale'], 60 * 24 * 365, '/', null, false, false, false, 'lax')
        );
    }
}
