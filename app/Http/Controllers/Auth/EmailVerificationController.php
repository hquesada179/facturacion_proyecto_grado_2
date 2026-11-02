<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpVerificationResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Mail\EmailVerificationCodeMail;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The OTP "Verifica tu correo" screen. Route name 'verification.notice'
 * (and 'verification.send' for resend) deliberately match Laravel's own
 * verification route naming so this reads as the framework's standard
 * concept extended with a 6-digit code instead of a signed link — see
 * the `verified` middleware applied to the onboarding route group in
 * routes/web.php, which redirects here by name when email_verified_at
 * is still null.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $otp) {}

    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // One-time flag set by verify() on success: shows the "Correo
        // verificado" state exactly once, then a later visit to this
        // same URL falls through to the hasVerifiedEmail() redirect below.
        if ($request->session()->pull('otp_just_verified') === true) {
            return view('auth.verify-email', ['state' => 'success']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('onboarding.show');
        }

        return view('auth.verify-email', [
            'state' => 'form',
            'email' => $user->email,
            'otpError' => $request->session()->get('otp_error_type'),
            'cooldownRemaining' => $this->otp->remainingCooldown($user),
        ]);
    }

    public function verify(VerifyEmailCodeRequest $request): RedirectResponse
    {
        $result = $this->otp->verify($request->user(), $request->validated('code'));

        return match ($result) {
            OtpVerificationResult::Success => redirect()->route('verification.notice')
                ->with('otp_just_verified', true),
            OtpVerificationResult::Invalid => redirect()->route('verification.notice')
                ->with('otp_error_type', 'invalid'),
            OtpVerificationResult::Expired => redirect()->route('verification.notice')
                ->with('otp_error_type', 'expired'),
            OtpVerificationResult::TooManyAttempts => redirect()->route('verification.notice')
                ->with('otp_error_type', 'locked'),
        };
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('onboarding.show');
        }

        if ($this->otp->remainingCooldown($user) > 0) {
            return redirect()->route('verification.notice');
        }

        $code = $this->otp->issue($user);
        Mail::to($user)->send(new EmailVerificationCodeMail($user->name, $code));

        return redirect()->route('verification.notice')->with('status', 'resent');
    }
}
