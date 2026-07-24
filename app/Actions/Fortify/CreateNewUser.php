<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\HCaptcha;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    private const DEFAULT_AVATAR_COLORS = [
        '#22d3ee', '#a855f7', '#f97316', '#f43f5e', '#22c55e', '#eab308', '#0ea5e9', '#ec4899',
    ];

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
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

        return User::create([
            'username' => strtolower($input['username']),
            'email' => $input['email'],
            'password' => $input['password'],
            'display_name' => $input['display_name'],
            'locale' => $input['locale'],
            'avatar_color' => self::DEFAULT_AVATAR_COLORS[array_rand(self::DEFAULT_AVATAR_COLORS)],
        ]);
    }
}
