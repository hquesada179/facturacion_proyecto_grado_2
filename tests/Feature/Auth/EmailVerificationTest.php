<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationCodeMail;
use App\Models\Company;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedUser(): User
    {
        // Administrador, matching what a freshly self-registered user
        // actually is (see RegisteredUserController) — needed so the
        // "reaches onboarding once verified" tests exercise the same
        // CompanyPolicy::update/`manage-company` gate a real registration
        // would hit, not a role-driven 403 unrelated to verification.
        return User::factory()->for(Company::factory())->administrador()->unverified()->create();
    }

    private function issueCodeFor(User $user): string
    {
        return app(EmailVerificationService::class)->issue($user);
    }

    public function test_verification_notice_requires_authentication(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    }

    public function test_verify_endpoint_requires_authentication(): void
    {
        $this->post(route('verification.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));
    }

    public function test_otp_mail_carries_a_six_digit_code_matching_the_stored_hash(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();

        $this->actingAs($user)->post(route('verification.send'));

        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use ($user): bool {
            $record = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first();

            return strlen($mail->code) === 6
                && ctype_digit($mail->code)
                && Hash::check($mail->code, $record->code_hash);
        });
    }

    public function test_code_is_never_persisted_in_plain_text(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        $this->assertDatabaseMissing('email_verification_codes', ['code_hash' => $code]);

        $record = EmailVerificationCode::where('user_id', $user->id)->first();
        $this->assertNotSame($code, $record->code_hash);
        $this->assertTrue(Hash::check($code, $record->code_hash));
    }

    public function test_correct_code_verifies_the_email_and_shows_the_success_state(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->post(route('verification.verify'), ['code' => $code]);

        $response->assertOk()->assertSee('Correo verificado');
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_incorrect_code_fails_and_does_not_verify(): void
    {
        $user = $this->unverifiedUser();
        $this->issueCodeFor($user);

        $this->actingAs($user)->post(route('verification.verify'), ['code' => '000111'])
            ->assertRedirect(route('verification.notice'));

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_expired_code_fails(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        EmailVerificationCode::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('otp_error_type', 'expired');

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_a_used_code_cannot_be_reused(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code]);
        $this->assertNotNull($user->refresh()->email_verified_at);

        // Logging out/in again just to reach the endpoint with a clean
        // session flash isn't needed — hasVerifiedEmail() short-circuits
        // resend/verify before the stale code is even looked at, so the
        // real proof is at the service layer: the same code never
        // verifies a second time if asked to directly.
        $result = app(EmailVerificationService::class)->verify($user, $code);
        $this->assertNotEquals(\App\Enums\OtpVerificationResult::Success, $result);
    }

    public function test_resend_invalidates_the_previous_code(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();
        $oldCode = $this->issueCodeFor($user);

        $this->travel(EmailVerificationService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->actingAs($user)->post(route('verification.send'));

        // The old row is gone (replaced, not merely marked used), so the
        // old code no longer matches anything — attempting it just reads
        // as a wrong code against whatever is now the active record.
        $this->actingAs($user)->post(route('verification.verify'), ['code' => $oldCode])
            ->assertSessionHas('otp_error_type', 'invalid');

        $this->assertNull($user->refresh()->email_verified_at);
        $this->assertSame(1, EmailVerificationCode::where('user_id', $user->id)->count());
    }

    public function test_resend_generates_a_brand_new_code_with_a_different_hash(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();
        $this->issueCodeFor($user);
        $oldHash = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first()->code_hash;

        $this->travel(EmailVerificationService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('verification.notice'));

        $newRecord = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first();

        $this->assertNotSame($oldHash, $newRecord->code_hash);
        $this->assertFalse($newRecord->expires_at->isPast());
        Mail::assertSentTimes(EmailVerificationCodeMail::class, 1);
    }

    public function test_resend_is_blocked_during_the_visible_cooldown(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();
        $this->issueCodeFor($user);

        $this->actingAs($user)->post(route('verification.send'));

        Mail::assertSentTimes(EmailVerificationCodeMail::class, 0);
    }

    public function test_too_many_wrong_attempts_locks_the_code(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        for ($i = 0; $i < EmailVerificationService::MAX_ATTEMPTS; $i++) {
            $this->actingAs($user)->post(route('verification.verify'), ['code' => '000111']);
        }

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code])
            ->assertSessionHas('otp_error_type', 'locked');

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_verify_endpoint_is_rate_limited(): void
    {
        RateLimiter::clear('otp-verify:'.($user = $this->unverifiedUser())->id);
        $this->issueCodeFor($user);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('verification.verify'), ['code' => '000111'])
                ->assertStatus(302);
        }

        $this->actingAs($user)->post(route('verification.verify'), ['code' => '000111'])
            ->assertStatus(429);
    }

    public function test_resend_endpoint_is_rate_limited(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();
        RateLimiter::clear('otp-resend:'.$user->id);
        $this->issueCodeFor($user);

        for ($i = 0; $i < 3; $i++) {
            $this->travel(EmailVerificationService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
            $this->actingAs($user)->post(route('verification.send'))->assertStatus(302);
        }

        $this->travel(EmailVerificationService::RESEND_COOLDOWN_SECONDS + 1)->seconds();
        $this->actingAs($user)->post(route('verification.send'))->assertStatus(429);
    }

    public function test_one_users_code_never_verifies_another_users_account(): void
    {
        $userA = $this->unverifiedUser();
        $userB = $this->unverifiedUser();
        $codeA = $this->issueCodeFor($userA);

        $this->actingAs($userB)->post(route('verification.verify'), ['code' => $codeA]);

        $this->assertNull($userB->refresh()->email_verified_at);
    }

    public function test_unverified_user_cannot_reach_onboarding_directly(): void
    {
        $user = $this->unverifiedUser();

        $this->actingAs($user)->get(route('onboarding.show'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_reach_onboarding(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code]);

        $this->actingAs($user)->get(route('onboarding.show'))->assertOk();
    }

    public function test_an_already_verified_factory_user_is_unaffected_by_the_onboarding_gate(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->administrador()->create();

        $this->actingAs($user)->get(route('onboarding.show'))->assertOk();
    }

    public function test_a_verified_user_is_never_asked_for_the_otp_again(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->issueCodeFor($user);

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code]);
        $this->assertNotNull($user->refresh()->email_verified_at);

        // Immediately after verifying: the one-time "Correo verificado"
        // success state (not the OTP form) — see the otp_just_verified
        // session flag in EmailVerificationController::notice().
        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Correo verificado');

        // Any later visit — the flash is gone, the OTP form must never
        // reappear. The user is bounced straight to onboarding instead.
        $this->actingAs($user)->get(route('verification.notice'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_registration_persists_a_hashed_otp_row_for_the_new_user(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Carlos Pérez',
            'email' => 'carlos.perez@example.com',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
            'terms' => '1',
        ]);

        $user = User::where('email', 'carlos.perez@example.com')->first();
        $this->assertNotNull($user);

        $record = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($record, 'El registro debió generar un código OTP.');
        $this->assertFalse($record->expires_at->isPast());
        $this->assertSame(0, $record->attempts);
        $this->assertNull($record->used_at);
    }

    /**
     * End-to-end: real registration → real OTP (captured from the mailed
     * Mailable, never hardcoded) → real verification → first onboarding
     * screen. Guards against the newly created Company ever looking
     * "already in progress" to OnboardingService::resolveDisplayStep()
     * (which would skip straight to billing/data/done) or the dashboard's
     * ?start_tour=1 auto-start (only ever set by
     * OnboardingController::complete(), never by registration).
     */
    public function test_a_newly_registered_and_verified_user_starts_onboarding_at_the_welcome_step_not_the_tutorial(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo.usuario@example.com',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
            'terms' => '1',
        ]);

        $user = User::where('email', 'nuevo.usuario@example.com')->first();
        $this->assertNotNull($user, 'El registro debió crear el usuario.');

        $capturedCode = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$capturedCode): bool {
            $capturedCode = $mail->code;

            return true;
        });

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $capturedCode])
            ->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user->refresh()->email_verified_at);

        $company = $user->company;

        // Nothing about registration pre-seeds onboarding progress or
        // demo data — a brand-new company must look completely untouched.
        $this->assertNull($company->onboarding_started_at);
        $this->assertNull($company->onboarding_step);
        $this->assertNull($company->onboarding_completed_at);
        $this->assertFalse(\App\Models\Customer::withoutGlobalScopes()->where('company_id', $company->id)->exists());
        $this->assertFalse(\App\Models\ProductService::withoutGlobalScopes()->where('company_id', $company->id)->exists());

        // First visit: the welcome gate, never the tutorial, never a
        // later step.
        $welcome = $this->actingAs($user)->get(route('onboarding.show'));
        $welcome->assertOk();
        $welcome->assertSee('Configuremos Fiscora para tu empresa');
        $welcome->assertSee('Comenzar');
        $welcome->assertDontSee('Datos de tu empresa');

        // Clicking "Comenzar" must land exactly on step 1 (Empresa) —
        // never billing/data/done, never ?start_tour=1.
        $this->actingAs($user)->post(route('onboarding.start'))
            ->assertRedirect(route('onboarding.show'));

        $this->assertSame('company', $company->refresh()->onboarding_step);

        $companyStep = $this->actingAs($user)->get(route('onboarding.show'));
        $companyStep->assertOk();
        $companyStep->assertSee('Datos de tu empresa');
        $companyStep->assertDontSee('Configuremos Fiscora para tu empresa');
    }
}
