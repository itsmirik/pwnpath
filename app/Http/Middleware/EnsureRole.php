<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate admin-area routes by role (plan §8 capability matrix). Usage:
 * `->middleware('role:author,admin')`. Any listed role passes; guests,
 * suspended accounts and everyone else get 403.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        /** @var User $user */
        abort_if($user->isSuspended(), 403);
        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
