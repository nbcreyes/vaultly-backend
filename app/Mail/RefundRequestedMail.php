<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer confirming their refund request was received.
 */
class RefundRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $productTitle,
        public readonly float  $amount,
        public readonly string $supportUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your refund request has been received — Vaultly');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.buyer.refund-requested');
    }
}