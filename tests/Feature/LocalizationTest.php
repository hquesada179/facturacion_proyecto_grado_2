<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_default_locale_is_spanish(): void
    {
        $this->assertSame('es', config('app.locale'));
    }

    public function test_validation_messages_render_in_spanish_not_as_raw_translation_keys(): void
    {
        $this->assertSame(
            'El campo correo electrónico es obligatorio.',
            trans('validation.required', ['attribute' => trans('validation.attributes.email')])
        );
        $this->assertStringNotContainsString('validation.required', trans('validation.required', ['attribute' => 'correo electrónico']));
    }

    public function test_a_missing_required_field_on_the_assistant_endpoint_reports_a_spanish_message(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => '',
            'screen' => 'dashboard',
        ])->assertUnprocessable();

        $message = $response->json('message');

        $this->assertStringNotContainsString('validation.required', $message);
        $this->assertStringContainsString('obligatorio', $message);
    }

    public function test_a_failed_login_reports_a_spanish_message(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors');

        $this->assertStringNotContainsString('auth.failed', $errors->first('email'));
        $this->assertStringContainsString('no coinciden', $errors->first('email'));
    }

    public function test_a_password_reset_request_for_an_unknown_email_reports_a_spanish_message(): void
    {
        $response = $this->post('/recuperar-contrasena', [
            'email' => 'no-existe@facturapro.test',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors');

        $this->assertStringNotContainsString('passwords.user', $errors->first('email'));
    }
}
