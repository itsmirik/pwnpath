<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'username' => 'aziz_dev',
            'display_name' => 'Aziz',
            'email' => 'aziz@example.com',
            'locale' => 'ru',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'h-captcha-response' => 'dev-bypass',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('username', 'aziz_dev')->firstOrFail();

        $this->assertSame('Aziz', $user->display_name);
        $this->assertSame('aziz@example.com', $user->email);
        $this->assertSame('ru', $user->locale);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_lowercases_mixed_case_username(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'username' => 'Mirsaid',
            'display_name' => 'Mirsaid',
            'email' => 'mirsaid@example.com',
            'locale' => 'ru',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'h-captcha-response' => 'dev-bypass',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['username' => 'mirsaid']);
    }

    public function test_registration_rejects_invalid_username(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'username' => 'BAD USERNAME',
            'display_name' => 'Aziz',
            'email' => 'aziz@example.com',
            'locale' => 'ru',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'h-captcha-response' => 'dev-bypass',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
