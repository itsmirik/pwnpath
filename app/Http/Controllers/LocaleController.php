<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController
{
    private const SUPPORTED = ['ru', 'uz', 'en'];

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|in:'.implode(',', self::SUPPORTED),
        ]);

        return back()->withCookie(
            Cookie::make('locale', $validated['locale'], 60 * 24 * 365, '/', null, false, false, false, 'lax')
        );
    }
}
