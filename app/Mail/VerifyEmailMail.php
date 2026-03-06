<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * VerifyEmailMail
 *
 * Sent to a new user after registration.
 * Contains a link to the frontend verification page with the token
 * as a query parameter. The frontend calls the backend verify endpoint
 * with that token to confirm the email address.
 */
class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param string $verificationUrl  The full frontend URL including the token.
     * @param string $userName         The recipient's display name.
     */
    public function __construct(
        public readonly string $verificationUrl,
        public readonly string $userName,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your Vaultly email address',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.verify-email',
        );
    }
}