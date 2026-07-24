<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $display_name
 * @property string|null $bio
 * @property string $avatar_color
 * @property string|null $country_code
 * @property string $locale
 * @property string $role
 * @property int $streak_count
 * @property int $streak_freeze_available
 * @property Carbon|null $last_solve_date
 * @property int $xp_total
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'username',
    'email',
    'password',
    'display_name',
    'bio',
    'avatar_color',
    'country_code',
    'locale',
    'role',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const ROLES = ['user', 'moderator', 'author', 'admin'];

    public const LOCALES = ['ru', 'uz', 'en'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_solve_date' => 'date',
            'streak_count' => 'integer',
            'streak_freeze_available' => 'integer',
            'xp_total' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }
}
