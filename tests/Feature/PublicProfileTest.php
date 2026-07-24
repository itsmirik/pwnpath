<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_is_visible_without_auth(): void
    {
        $user = User::factory()->create([
            'username' => 'aziz_dev',
            'display_name' => 'Aziz',
            'bio' => 'CTF fan',
            'xp_total' => 300,
        ]);

        $response = $this->get('/u/aziz_dev');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('profiles/Show')
            ->where('profile.username', 'aziz_dev')
            ->where('profile.display_name', 'Aziz')
            ->where('profile.bio', 'CTF fan')
            ->where('profile.xp_total', 300),
        );
    }

    public function test_unknown_username_returns_404(): void
    {
        $this->get('/u/does_not_exist')->assertNotFound();
    }
}
