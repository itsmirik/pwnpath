<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        $user = $request->user();
        if ($user instanceof User && in_array($user->locale, User::LOCALES, true)) {
            $locale = $user->locale;
        }

        if ($locale === null) {
            $cookie = $request->cookie('locale');
            if (is_string($cookie) && in_array($cookie, User::LOCALES, true)) {
                $locale = $cookie;
            }
        }

        App::setLocale($locale ?? config('app.locale'));

        return $next($request);
    }
}
