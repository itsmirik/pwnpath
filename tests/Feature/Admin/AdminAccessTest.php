<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_regular_user_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_each_staff_role_reaches_the_dashboard(): void
    {
        foreach (['author', 'moderator', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin')
                ->assertOk();
        }
    }

    public function test_author_scope_of_access(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)->get(route('admin.challenges.index'))->assertOk();
        $this->actingAs($author)->get(route('admin.writeups.index'))->assertForbidden();
        $this->actingAs($author)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($author)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($author)->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_moderator_scope_of_access(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator)->get(route('admin.writeups.index'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($moderator)->get(route('admin.challenges.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_reaches_every_section(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            'admin.challenges.index',
            'admin.writeups.index',
            'admin.reports.index',
            'admin.users.index',
            'admin.audit.index',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }

    public function test_suspended_staff_are_logged_out_and_bounced(): void
    {
        $admin = User::factory()->admin()->suspended()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_suspended_user_is_blocked_from_the_whole_app(): void
    {
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_capabilities_are_shared_to_the_frontend(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertInertia(fn ($page) => $page
                ->where('admin.can.manage_users', true)
                ->where('admin.can.review', true)
                ->where('admin.can.moderate', true));
    }
}
