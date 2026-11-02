<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Mail\EmailVerificationCodeMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'María Fernanda Rojas',
            'email' => 'maria.rojas@example.com',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
            'terms' => '1',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_valid_registration_creates_user_logs_in_and_redirects_to_verification(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), $this->validPayload());

        $user = User::where('email', 'maria.rojas@example.com')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_registration_creates_a_company_and_associates_the_user_to_it(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->validPayload());

        $user = User::where('email', 'maria.rojas@example.com')->first();

        $this->assertNotNull($user->company_id);
        $this->assertInstanceOf(Company::class, $user->company);
        $this->assertSame(1, Company::count());
    }

    public function test_the_first_user_of_a_new_company_becomes_administrador(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->validPayload());

        $user = User::where('email', 'maria.rojas@example.com')->first();

        $this->assertSame(UserRole::Administrador, $user->role);
        $this->assertTrue($user->isAdministrador());
    }

    public function test_password_is_hashed_and_never_stored_in_plain_text(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->validPayload());

        $user = User::where('email', 'maria.rojas@example.com')->first();

        $this->assertNotSame('correct-password', $user->password);
        $this->assertTrue(Hash::check('correct-password', $user->password));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        User::factory()->for($company)->create(['email' => 'maria.rojas@example.com']);

        $response = $this->post(route('register'), $this->validPayload());

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $response = $this->post(route('register'), $this->validPayload([
            'password_confirmation' => 'something-else',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_unaccepted_terms_are_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['terms']);

        $response = $this->post(route('register'), $payload);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_failed_validation_never_creates_a_partial_company_or_user(): void
    {
        $this->post(route('register'), $this->validPayload(['password_confirmation' => 'nope']));

        $this->assertSame(0, Company::count());
        $this->assertSame(0, User::count());
    }

    public function test_registration_sends_the_otp_code_by_mail(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->validPayload());

        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail): bool {
            return strlen($mail->code) === 6 && ctype_digit($mail->code);
        });
    }

    public function test_landing_and_login_both_link_to_registration_without_removing_login(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('register'), false);
        $this->get(route('login'))->assertOk()
            ->assertSee(route('register'), false)
            ->assertSee('Iniciar sesión');
    }
}
