<?php

namespace App\Http\Middleware;

use App\Rules\HCaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class EnsureHCaptcha
{
    /**
     * Routes that must pass hCaptcha before Fortify handles them.
     *
     * @var array<int, string>
     */
    private const PROTECTED_ROUTES = ['password.email'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(self::PROTECTED_ROUTES)) {
            return $next($request);
        }

        $secret = config('services.hcaptcha.secret');
        if (! is_string($secret) || $secret === '') {
            return $next($request);
        }

        Validator::make(
            $request->all(),
            ['h-captcha-response' => ['required', new HCaptcha]],
            ['h-captcha-response.required' => __('validation.captcha.missing')],
        )->validate();

        return $next($request);
    }
}
