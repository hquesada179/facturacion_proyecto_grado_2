<?php

namespace Tests\Feature;

use App\Support\PrototypeScreens;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_main_prototype_routes_are_available(): void
    {
        $uris = array_column(PrototypeScreens::routes(), 'uri');
        $uris = array_merge($uris, ['/login', '/recuperar-contrasena', '/onboarding/empresa']);

        foreach ($uris as $uri) {
            $this->get($uri)->assertOk();
        }
    }
}
