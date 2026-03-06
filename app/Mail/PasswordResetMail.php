<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PasswordResetMail
 *
 * Sent when a user requests a password reset.
 * Contains a link to the frontend reset page with the token
 * and email as query parameters.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param string $resetUrl   The full frontend URL with token and email.
     * @param string $userName   The recipient's display name.
     */
    public function __construct(
        public readonly string $resetUrl,
        public readonly string $userName,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your Vaultly password',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.password-reset',
        );
    }
}