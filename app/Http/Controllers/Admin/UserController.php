<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User management (plan §8 screen 6, admin only): search, change role, soft-ban.
 * Suspending preserves all data (plan §11) — no cascade delete.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'role' => ['nullable', 'string', Rule::in(User::ROLES)],
            'status' => ['nullable', 'string', Rule::in(User::STATUSES)],
        ]);

        $search = trim($filters['q'] ?? '');

        $query = User::query()
            ->withCount('solves')
            ->latest('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $users = $query->paginate(25)->withQueryString();

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('admin/users/Index', [
            'users' => [
                'data' => $users->getCollection()->map(fn (User $u): array => [
                    'id' => $u->id,
                    'username' => $u->username,
                    'display_name' => $u->display_name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'status' => $u->status,
                    'xp_total' => $u->xp_total,
                    'solves_count' => $u->solves_count,
                    'country_code' => $u->country_code,
                    'ban_reason' => $u->ban_reason,
                    'created_at' => $u->created_at?->toIso8601String(),
                    'banned_at' => $u->banned_at?->toIso8601String(),
                    'is_self' => $u->id === $actor->id,
                    'profile_url' => route('profile.show', $u->username),
                    'role_url' => route('admin.users.role', $u->username),
                    'ban_url' => route('admin.users.ban', $u->username),
                    'unban_url' => route('admin.users.unban', $u->username),
                ])->all(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'total' => $users->total(),
                ],
                'links' => [
                    'prev' => $users->previousPageUrl(),
                    'next' => $users->nextPageUrl(),
                ],
            ],
            'filters' => [
                'q' => $search,
                'role' => $filters['role'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'options' => [
                'roles' => User::ROLES,
                'statuses' => User::STATUSES,
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        // Guard against self-lockout by role change.
        abort_if($user->id === $actor->id, 403);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(User::ROLES)],
        ]);

        // Never demote the last remaining admin.
        if ($user->isAdmin() && $validated['role'] !== User::ROLE_ADMIN) {
            $admins = User::query()->where('role', User::ROLE_ADMIN)->count();

            if ($admins <= 1) {
                throw ValidationException::withMessages([
                    'role' => __('Cannot demote the last admin.'),
                ]);
            }
        }

        $from = $user->role;
        // forceFill: 'role' is intentionally not mass-assignable (see User model).
        $user->forceFill(['role' => $validated['role']])->save();

        AuditLog::record(AuditLog::ACTION_USER_ROLE_CHANGE, $actor, $user, [
            'from' => $from,
            'to' => $validated['role'],
        ]);

        $this->toast(__('Role updated.'));

        return back();
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_if($user->id === $actor->id, 403);
        // Admins cannot be soft-banned through the UI (protect staff accounts).
        abort_if($user->isAdmin(), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user->forceFill([
            'status' => User::STATUS_SUSPENDED,
            'ban_reason' => $validated['reason'] ?? null,
            'banned_at' => now(),
        ])->save();

        AuditLog::record(AuditLog::ACTION_USER_BAN, $actor, $user, [
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->toast(__('User suspended.'));

        return back();
    }

    public function unban(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'ban_reason' => null,
            'banned_at' => null,
        ])->save();

        AuditLog::record(AuditLog::ACTION_USER_UNBAN, $actor, $user);

        $this->toast(__('User reinstated.'));

        return back();
    }

    private function toast(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
