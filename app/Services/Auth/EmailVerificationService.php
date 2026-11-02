<?php

namespace App\Services\Auth;

use App\Enums\OtpVerificationResult;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Issues and verifies the 6-digit email-verification code shown on the
 * "Verifica tu correo" screen. Deliberately separate from Laravel's
 * built-in signed-URL verification notification — the approved mockup
 * calls for an OTP, not an email link — but still finishes through the
 * framework's standard email_verified_at mechanism (User::markEmailAsVerified(),
 * see MustVerifyEmail on the User model) rather than inventing a parallel
 * "is_verified" concept.
 *
 * Never logs, returns, or renders the plain code anywhere outside the
 * Mailable that sends it once at issue() time; only a bcrypt hash of it
 * is ever persisted (see EmailVerificationCode::$hidden too).
 */
class EmailVerificationService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_IN_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 45;

    /**
     * Generates a new code, invalidates any previous one for this user,
     * and returns the plain code so the caller can email it immediately.
     * random_int() draws from the OS CSPRNG (not mt_rand), so every
     * value in 000000–999999 — including ones with leading zeros — is
     * equally likely.
     */
    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $code): void {
            EmailVerificationCode::where('user_id', $user->id)->delete();

            EmailVerificationCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ]);
        });

        return $code;
    }

    /**
     * Hash::check() is bcrypt's own constant-time comparison, so this
     * never leaks timing information about how much of the code matched.
     */
    public function verify(User $user, string $code): OtpVerificationResult
    {
        $record = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first();

        if (! $record || $record->used_at !== null || $record->expires_at->isPast()) {
            return OtpVerificationResult::Expired;
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return OtpVerificationResult::TooManyAttempts;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return $record->attempts >= self::MAX_ATTEMPTS
                ? OtpVerificationResult::TooManyAttempts
                : OtpVerificationResult::Invalid;
        }

        $record->forceFill(['used_at' => now()])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return OtpVerificationResult::Success;
    }

    /**
     * Seconds until a resend is allowed again, derived from the most
     * recently issued code's created_at — no extra column needed, and it
     * naturally survives a page reload since it's computed server-side.
     */
    public function remainingCooldown(User $user): int
    {
        $latest = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first();

        if (! $latest) {
            return 0;
        }

        $readyAt = $latest->created_at->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return $readyAt->isFuture() ? now()->diffInSeconds($readyAt) : 0;
    }
}
