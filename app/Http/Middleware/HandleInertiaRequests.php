<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->userPayload($request->user()),
            ],
            'locale' => app()->getLocale(),
            'hcaptcha' => [
                'sitekey' => config('services.hcaptcha.sitekey'),
            ],
            'admin' => $this->adminShare($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Admin-area capabilities for the current user (plan §8 capability matrix).
     * Null for non-staff, so the client never renders admin chrome for them.
     *
     * @return array{can: array<string, bool>}|null
     */
    private function adminShare(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isStaff()) {
            return null;
        }

        return [
            'can' => [
                'author' => $user->canAuthorChallenges(),
                'review' => $user->isAdmin(),
                'moderate' => $user->canModerateContent(),
                'manage_users' => $user->isAdmin(),
                'view_audit' => $user->isAdmin(),
            ],
        ];
    }

    /**
     * Only the current user's fields the client actually reads. Sharing the raw
     * model would leak columns like signup_ip / ban_reason / status / streak into
     * every page's props (visible in the page source). Guests get null.
     *
     * @return array<string, mixed>|null
     */
    private function userPayload(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'avatar_color' => $user->avatar_color,
            'role' => $user->role,
            'locale' => $user->locale,
            'country_code' => $user->country_code,
            'bio' => $user->bio,
        ];
    }
}
