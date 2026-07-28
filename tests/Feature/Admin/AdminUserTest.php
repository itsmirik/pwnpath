<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_search_users(): void
    {
        $admin = $this->admin();
        User::factory()->create(['username' => 'findme', 'display_name' => 'Find Me']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => 'findme']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/Index')
                ->where('users.data.0.username', 'findme'));
    }

    public function test_admin_changes_a_role(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.role', $target->username), ['role' => 'moderator'])
            ->assertRedirect();

        $this->assertSame('moderator', $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_USER_ROLE_CHANGE,
            'actor_id' => $admin->id,
            'entity_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.role', $admin->username), ['role' => 'user'])
            ->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_can_demote_another_admin(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.role', $other->username), ['role' => 'user'])
            ->assertRedirect();

        $this->assertSame('user', $other->fresh()->role);
    }

    public function test_admin_suspends_and_reinstates_a_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $target->username), ['reason' => 'spamming'])
            ->assertRedirect();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->isSuspended());
        $this->assertSame('spamming', $fresh->ban_reason);
        $this->assertNotNull($fresh->banned_at);

        $this->actingAs($admin)
            ->post(route('admin.users.unban', $target->username))
            ->assertRedirect();

        $reinstated = $target->fresh();
        $this->assertFalse($reinstated->isSuspended());
        $this->assertNull($reinstated->ban_reason);
    }

    public function test_admin_cannot_suspend_self_or_other_admins(): void
    {
        $admin = $this->admin();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $admin->username))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $other->username))
            ->assertForbidden();

        $this->assertFalse($other->fresh()->isSuspended());
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->moderator()->create())
            ->post(route('admin.users.ban', $target->username))
            ->assertForbidden();
    }
}
