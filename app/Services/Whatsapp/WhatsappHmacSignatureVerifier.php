<?php

namespace App\Services\Whatsapp;

/**
 * Pure signature-verification logic, deliberately kept out of the HTTP
 * middleware so it's trivially unit-testable without booting a request.
 * Canonical signed string is "{timestamp}.{raw body}", HMAC-SHA256 keyed
 * by the shared secret, compared with hash_equals() (constant-time —
 * never a plain === on a secret-derived value).
 */
class WhatsappHmacSignatureVerifier
{
    public function __construct(private readonly string $secret, private readonly int $maxSkewSeconds) {}

    public function sign(string $timestamp, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->secret);
    }

    /**
     * @return string|null  null on success; otherwise a short,
     *   non-sensitive machine-readable rejection reason (never includes
     *   the signature, the secret, or the body) suitable for audit
     *   logging.
     */
    public function verify(?string $timestamp, ?string $signature, string $rawBody, ?int $now = null): ?string
    {
        if ($this->secret === '') {
            return 'secret_not_configured';
        }

        if ($timestamp === null || $signature === null || $timestamp === '' || $signature === '') {
            return 'missing_signature';
        }

        if (! ctype_digit($timestamp)) {
            return 'invalid_timestamp';
        }

        $now ??= time();

        if (abs($now - (int) $timestamp) > $this->maxSkewSeconds) {
            return 'timestamp_out_of_range';
        }

        $expected = $this->sign($timestamp, $rawBody);

        if (! hash_equals($expected, $signature)) {
            return 'signature_mismatch';
        }

        return null;
    }
}
