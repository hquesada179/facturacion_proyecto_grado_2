<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public marketing landing at `/`. Purely presentational — no new
 * endpoint, no auth change: an authenticated visitor is simply bounced to
 * the real dashboard, exactly like before this page existed.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_renders_for_guests(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_the_landing_page_links_to_the_real_login_route(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('login'), false);
    }

    public function test_the_landing_page_is_explicit_about_being_an_academic_prototype(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Prototipo académico');
        $response->assertSee('no tienen validez tributaria');
    }

    public function test_the_landing_page_never_claims_the_assistant_acts_without_confirmation(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('confirmación');
    }

    public function test_the_landing_page_does_not_advertise_public_registration(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Regístrate');
        $response->assertDontSee('Crear cuenta');
    }

    public function test_an_authenticated_user_visiting_the_landing_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->for(Company::factory())->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_the_dashboard_route_is_unaffected_and_still_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_is_still_reachable_from_a_guest_session(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
