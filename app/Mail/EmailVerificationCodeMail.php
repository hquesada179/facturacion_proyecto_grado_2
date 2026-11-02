<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Deliberately sent synchronously (Mail::send(), never ::queue()) by
 * RegisteredUserController/EmailVerificationController — queuing would
 * leave the plain 6-digit code sitting in the jobs table payload until
 * the worker picks it up, which is exactly the kind of plaintext-at-rest
 * exposure the OTP design is meant to avoid.
 */
class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de verificación de Fiscora',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verification-code',
        );
    }
}
