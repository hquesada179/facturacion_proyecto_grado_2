<?php

namespace App\Services\Whatsapp;

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappLinkCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Generates and consumes the short "FISCORA-7K4P9X" linking code, and
 * manages the resulting WhatsappConnection. The code itself is the ONLY
 * credential ever accepted to link a number — there is no path that
 * links by email, company name, or phone number alone.
 *
 * Lookup problem this solves: unlike EmailVerificationService (which
 * already knows the authenticated user_id and only ever checks ONE
 * row), consume() is reached from WhatsApp with nothing but the code
 * itself — no user context exists yet. `lookup_hash` (HMAC-SHA256 keyed
 * by APP_KEY) gives an O(1) indexed candidate lookup without being
 * reversible from a database dump alone (the attacker would also need
 * APP_KEY); `code_hash` (bcrypt, same convention as users.password and
 * email_verification_codes.code_hash) is what actually decides a match.
 */
class WhatsappLinkService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_IN_MINUTES = 10;

    // Unambiguous subset — no 0/O or 1/I/L — so a code read aloud or
    // typed on a phone keyboard is never misheard/mistyped into another
    // valid-looking code.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly WhatsappBridgeAuditLogger $audit) {}

    /**
     * @return array{code: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function generate(User $user): array
    {
        $plain = $this->randomCode();
        $expiresAt = now()->addMinutes(self::EXPIRES_IN_MINUTES);

        DB::transaction(function () use ($user, $plain, $expiresAt): void {
            WhatsappLinkCode::where('user_id', $user->id)->whereNull('used_at')->delete();

            // lookup_hash collisions are astronomically unlikely (1 in
            // ~1 billion) but the unique constraint exists precisely to
            // make a collision loud and safe instead of silent — one
            // retry with a fresh code is enough to resolve it.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    WhatsappLinkCode::create([
                        'user_id' => $user->id,
                        'company_id' => $user->company_id,
                        'code_hash' => Hash::make($plain),
                        'lookup_hash' => $this->lookupHash($plain),
                        'expires_at' => $expiresAt,
                    ]);

                    break;
                } catch (\Illuminate\Database\QueryException $exception) {
                    if ($attempt === 2) {
                        throw $exception;
                    }
                }
            }
        });

        $this->audit->codeGenerated($user);

        return ['code' => "FISCORA-{$plain}", 'expires_at' => $expiresAt];
    }

    /**
     * @return array{success: bool, user?: User, company?: Company, connection?: WhatsappConnection}
     */
    public function consume(string $waId, ?string $phoneNumberId, string $plainCode): array
    {
        $plainCode = Str::upper(trim($plainCode));
        $lookupHash = $this->lookupHash($plainCode);

        return DB::transaction(function () use ($waId, $phoneNumberId, $plainCode, $lookupHash): array {
            $record = WhatsappLinkCode::where('lookup_hash', $lookupHash)->lockForUpdate()->first();

            if ($record === null
                || $record->used_at !== null
                || $record->expires_at->isPast()
                || ! Hash::check($plainCode, $record->code_hash)
            ) {
                $this->audit->linkInvalidAttempt($waId, 'invalid_or_expired');

                return ['success' => false];
            }

            // Atomic, conditional on still being unused — the same
            // "single UPDATE ... WHERE" guard ActionProposalStore uses,
            // so two near-simultaneous consume() calls for the same code
            // (a retried/duplicated webhook) can never both succeed.
            $claimed = WhatsappLinkCode::where('id', $record->id)->whereNull('used_at')->update(['used_at' => now()]);

            if ($claimed === 0) {
                $this->audit->linkInvalidAttempt($waId, 'already_used');

                return ['success' => false];
            }

            $user = User::withoutGlobalScopes()->find($record->user_id);
            $company = Company::find($record->company_id);

            if ($user === null || $company === null || $user->company_id !== $company->id) {
                $this->audit->linkInvalidAttempt($waId, 'user_or_company_missing');

                return ['success' => false];
            }

            // Enforce "one active WhatsApp number per company at a time"
            // and "one active connection per user at a time" as real
            // mutations, on top of the active_wa_id unique DB constraint
            // that makes the first one impossible to violate even if
            // this check were ever skipped.
            WhatsappConnection::withoutGlobalScopes()->active()->where('active_wa_id', $waId)
                ->update(['active_wa_id' => null, 'revoked_at' => now()]);
            WhatsappConnection::withoutGlobalScopes()->active()->where('user_id', $user->id)
                ->update(['active_wa_id' => null, 'revoked_at' => now()]);

            $connection = WhatsappConnection::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'wa_id' => $waId,
                'active_wa_id' => $waId,
                'phone_number_id' => $phoneNumberId,
                'verified_at' => now(),
            ]);

            $this->audit->linkSucceeded($user, $connection);

            return ['success' => true, 'user' => $user, 'company' => $company, 'connection' => $connection];
        });
    }

    /**
     * Only ever the owning user may revoke their own connection — never
     * exposed as a bulk/admin action in this phase.
     */
    public function revoke(User $user, WhatsappConnection $connection): void
    {
        abort_unless($connection->user_id === $user->id && $connection->company_id === $user->company_id, 403);

        $connection->forceFill([
            'active_wa_id' => null,
            'revoked_at' => now(),
        ])->save();

        $this->audit->connectionRevoked($user, $connection);
    }

    public function activeConnectionFor(string $waId): ?WhatsappConnection
    {
        return WhatsappConnection::withoutGlobalScopes()->active()->where('wa_id', $waId)->first();
    }

    public function activeConnectionForUser(User $user): ?WhatsappConnection
    {
        return WhatsappConnection::withoutGlobalScopes()->active()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function randomCode(): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }

    private function lookupHash(string $plainCode): string
    {
        return hash_hmac('sha256', $plainCode, (string) config('app.key'));
    }
}
