<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Rules applied on signup (all fields required).
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function signupRules(): array
    {
        return [
            'username' => $this->usernameRules(),
            'email' => $this->emailRules(),
            'display_name' => $this->displayNameRules(),
            'locale' => $this->localeRules(),
        ];
    }

    /**
     * Rules applied when editing an existing profile.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileEditRules(int $userId): array
    {
        return [
            'email' => $this->emailRules($userId),
            'display_name' => $this->displayNameRules(),
            'bio' => ['nullable', 'string', 'max:300'],
            'avatar_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'locale' => $this->localeRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:32',
            'regex:/^[a-z0-9_]+$/',
            $userId === null
                ? Rule::unique(User::class, 'username')
                : Rule::unique(User::class, 'username')->ignore($userId),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function displayNameRules(): array
    {
        return ['required', 'string', 'max:64'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function localeRules(): array
    {
        return ['required', 'string', Rule::in(User::LOCALES)];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
