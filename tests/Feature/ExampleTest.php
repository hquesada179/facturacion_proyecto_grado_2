<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Support\PrototypeScreens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_public_landing_page_at_the_root_url(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Fiscora');
    }

    public function test_authenticated_users_visiting_the_root_url_are_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->for(Company::factory())->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_main_prototype_routes_are_available_to_an_authenticated_administrador(): void
    {
        $user = User::factory()->for(Company::factory())->create(['role' => UserRole::Administrador]);

        $uris = array_column(PrototypeScreens::routes(), 'uri');

        foreach ($uris as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_guest_only_routes_are_available_without_authentication(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/recuperar-contrasena')->assertOk();
    }
}
