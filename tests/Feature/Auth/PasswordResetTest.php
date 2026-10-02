<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->for(Company::factory())->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->for(Company::factory())->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, function (ResetPassword $notification) {
            $response = $this->get(route('password.reset', ['token' => $notification->token]));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->for(Company::factory())->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, function (ResetPassword $notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response->assertRedirect(route('login'));

            $loginResponse = $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'new-password',
            ]);

            $this->assertAuthenticatedAs($user->fresh());
            $loginResponse->assertRedirect(route('dashboard'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_an_invalid_token(): void
    {
        $user = User::factory()->for(Company::factory())->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
