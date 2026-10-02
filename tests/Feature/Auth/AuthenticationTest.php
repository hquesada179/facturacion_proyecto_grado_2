<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_a_protected_route(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_users_can_authenticate_with_correct_credentials(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_an_incorrect_password(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_access_a_protected_route(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['role' => UserRole::Administrador]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_users_can_logout(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
