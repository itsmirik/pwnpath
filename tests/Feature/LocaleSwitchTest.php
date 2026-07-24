<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_locale_switch_sets_cookie(): void
    {
        $response = $this->from('/')->post(route('locale.switch'), ['locale' => 'uz']);

        $response->assertRedirect('/');
        $response->assertCookie('locale', 'uz', encrypted: false);
    }

    public function test_authed_locale_switch_persists_to_user(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('locale.switch'), ['locale' => 'en'])
            ->assertRedirect('/dashboard');

        $this->assertSame('en', $user->refresh()->locale);
    }

    public function test_locale_switch_rejects_unknown_locale(): void
    {
        $this->from('/')
            ->post(route('locale.switch'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }
}
