<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft-ban enforcement (plan §11). A suspended user is logged out on their next
 * request and bounced to login with a message; their data is preserved.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = __('Your account has been suspended.');

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
