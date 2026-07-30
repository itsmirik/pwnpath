<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hardening response headers for every web response (pre-launch security).
 *
 * A per-request nonce is generated up front and shared with both the Vite
 * asset tags and the inline theme script in app.blade.php, so the CSP can
 * forbid inline scripts outright — no 'unsafe-inline' escape hatch that would
 * hand stored/reflected XSS a free pass.
 *
 * The CSP is only *enforced* in production: locally the Vite dev server serves
 * an inline HMR client (plus eval + a websocket) that a strict policy blocks.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Must run before the view renders so @vite and the inline theme
        // script both carry this request's nonce.
        Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), browsing-topics=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        // HSTS is meaningless (and ignored) over plain HTTP; only send it on TLS.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (app()->isProduction()) {
            $headers['Content-Security-Policy'] = $this->contentSecurityPolicy(Vite::cspNonce());
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * hCaptcha (signup widget) and Plausible (analytics) are the only third-party
     * origins; everything else is same-origin. Scripts are pinned to 'self' + the
     * request nonce — never 'unsafe-inline'. If hCaptcha or Plausible ever need a
     * new host, widen the relevant directive here.
     */
    private function contentSecurityPolicy(?string $nonce): string
    {
        $script = "'self'".($nonce !== null ? " 'nonce-{$nonce}'" : '');
        $hcaptcha = 'https://hcaptcha.com https://*.hcaptcha.com';

        $directives = [
            'default-src' => "'self'",
            'base-uri' => "'self'",
            'object-src' => "'none'",
            'frame-ancestors' => "'none'",
            'form-action' => "'self'",
            'script-src' => "{$script} {$hcaptcha} https://plausible.io",
            'style-src' => "'self' 'unsafe-inline' {$hcaptcha}",
            'img-src' => "'self' data: https:",
            'font-src' => "'self' data:",
            'connect-src' => "'self' {$hcaptcha} https://plausible.io",
            'frame-src' => $hcaptcha,
        ];

        return collect($directives)
            ->map(fn (string $value, string $name): string => "{$name} {$value}")
            ->implode('; ');
    }
}
