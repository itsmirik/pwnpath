<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string $status
 * @property string|null $ban_reason
 * @property Carbon|null $banned_at
 * @property int $streak_count
 * @property int $streak_freeze_available
 * @property Carbon|null $last_solve_date
 * @property int $xp_total
 * @property string|null $signup_ip
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
    // 'role' is deliberately NOT fillable — a privilege-escalation guard. It is
    // set explicitly via forceFill() in Admin\UserController::updateRole so no
    // request payload can ever mass-assign it. New users get the DB default.
    'signup_ip',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const ROLES = ['user', 'moderator', 'author', 'admin'];

    public const ROLE_USER = 'user';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_AUTHOR = 'author';

    public const ROLE_ADMIN = 'admin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SUSPENDED];

    public const LOCALES = ['ru', 'uz', 'en'];

    /**
     * Rank tiers keyed by minimum XP (plan §5 Scoring). Ordered ascending;
     * a user's rank is the highest tier whose floor they have reached.
     *
     * @var array<int, array{tier: string, min: int}>
     */
    public const RANKS = [
        ['tier' => 'bronze', 'min' => 0],
        ['tier' => 'silver', 'min' => 500],
        ['tier' => 'gold', 'min' => 2000],
        ['tier' => 'platinum', 'min' => 5000],
        ['tier' => 'diamond', 'min' => 10000],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'banned_at' => 'datetime',
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

    /** @return HasMany<Solve, $this> */
    public function solves(): HasMany
    {
        return $this->hasMany(Solve::class);
    }

    /** @return HasMany<FlagSubmission, $this> */
    public function flagSubmissions(): HasMany
    {
        return $this->hasMany(FlagSubmission::class);
    }

    /** @return HasMany<ChallengeView, $this> */
    public function challengeViews(): HasMany
    {
        return $this->hasMany(ChallengeView::class);
    }

    /** @return HasMany<UserBadge, $this> */
    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    /** @return HasMany<Writeup, $this> */
    public function writeups(): HasMany
    {
        return $this->hasMany(Writeup::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return BelongsToMany<Badge, $this> */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('awarded_at')
            ->orderBy('badges.sort_order');
    }

    public function hasSolved(Challenge $challenge): bool
    {
        return $this->solves()->where('challenge_id', $challenge->id)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function isAuthor(): bool
    {
        return $this->role === self::ROLE_AUTHOR;
    }

    /** Any role with access to the admin area (plan §8 capability matrix). */
    public function isStaff(): bool
    {
        return in_array(
            $this->role,
            [self::ROLE_AUTHOR, self::ROLE_MODERATOR, self::ROLE_ADMIN],
            true,
        );
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /** Author or admin: create/edit challenge drafts + submit for review. */
    public function canAuthorChallenges(): bool
    {
        return $this->isAuthor() || $this->isAdmin();
    }

    /** Moderator or admin: approve writeups, hide comments, resolve reports. */
    public function canModerateContent(): bool
    {
        return $this->isModerator() || $this->isAdmin();
    }

    /**
     * Rank tier slug derived from total XP (plan §5). Computed, never stored.
     *
     * @return Attribute<string, never>
     */
    protected function rank(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::rankForXp($this->xp_total),
        );
    }

    public static function rankForXp(int $xp): string
    {
        $tier = self::RANKS[0]['tier'];

        foreach (self::RANKS as $rank) {
            if ($xp >= $rank['min']) {
                $tier = $rank['tier'];
            }
        }

        return $tier;
    }

    /**
     * Current rank plus progress toward the next tier, for the profile UI.
     *
     * @return array{tier: string, next_tier: string|null, floor: int, next: int|null, xp: int, progress: float}
     */
    public function rankProgress(): array
    {
        $xp = $this->xp_total;
        $current = self::RANKS[0];
        $next = null;

        foreach (self::RANKS as $index => $rank) {
            if ($xp >= $rank['min']) {
                $current = $rank;
                $next = self::RANKS[$index + 1] ?? null;
            }
        }

        $progress = 1.0;
        if ($next !== null) {
            $span = $next['min'] - $current['min'];
            $progress = $span > 0 ? ($xp - $current['min']) / $span : 0.0;
        }

        return [
            'tier' => $current['tier'],
            'next_tier' => $next['tier'] ?? null,
            'floor' => $current['min'],
            'next' => $next['min'] ?? null,
            'xp' => $xp,
            'progress' => round(max(0.0, min(1.0, $progress)), 4),
        ];
    }
}
