<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\HCaptcha;
use App\Services\SignupIpLimiter;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    private const DEFAULT_AVATAR_COLORS = [
        '#22d3ee', '#a855f7', '#f97316', '#f43f5e', '#22c55e', '#eab308', '#0ea5e9', '#ec4899',
    ];

    public function __construct(
        private readonly SignupIpLimiter $signupIpLimiter,
    ) {}

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Usernames are stored lowercase; normalize before validating so that
        // mixed-case input (e.g. "Mirsaid") passes the regex and unique checks
        // instead of being rejected.
        if (isset($input['username']) && is_string($input['username'])) {
            $input['username'] = strtolower($input['username']);
        }

        $rules = [
            ...$this->signupRules(),
            'password' => $this->passwordRules(),
        ];

        $secret = config('services.hcaptcha.secret');
        if (is_string($secret) && $secret !== '') {
            $rules['h-captcha-response'] = ['required', new HCaptcha];
        }

        Validator::make($input, $rules, [
            'h-captcha-response.required' => __('validation.captcha.missing'),
        ])->validate();

        $ip = request()->ip();
        $this->signupIpLimiter->assertAllowed($ip);

        return User::create([
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
            'display_name' => $input['display_name'],
            'locale' => $input['locale'],
            'avatar_color' => self::DEFAULT_AVATAR_COLORS[array_rand(self::DEFAULT_AVATAR_COLORS)],
            'signup_ip' => $ip,
        ]);
    }
}
