<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HCaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.hcaptcha.secret');

        if (! is_string($secret) || $secret === '') {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail(__('validation.captcha.missing'));

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(config('services.hcaptcha.verify_url'), [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('hCaptcha siteverify failed', ['error' => $e->getMessage()]);
            $fail(__('validation.captcha.failed'));

            return;
        }

        if (! $response->ok() || $response->json('success') !== true) {
            $fail(__('validation.captcha.failed'));
        }
    }
}
