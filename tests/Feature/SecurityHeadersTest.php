<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_hardening_headers_present_on_web_responses(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function test_csp_is_enforced_in_production_and_forbids_inline_scripts(): void
    {
        // CSP is production-gated so the Vite dev server survives local dev.
        app()->detectEnvironment(fn (): string => 'production');

        $csp = $this->get(route('home'))->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://hcaptcha.com', $csp);
        // Scripts run off a per-request nonce, never 'unsafe-inline'.
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
    }

    public function test_csp_is_absent_outside_production(): void
    {
        $this->assertNull(
            $this->get(route('home'))->headers->get('Content-Security-Policy'),
        );
    }
}
