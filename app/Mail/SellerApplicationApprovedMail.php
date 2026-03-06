<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SellerApplicationApprovedMail
 *
 * Sent to a user when their seller application is approved by admin.
 * Includes a link to the seller dashboard to get started.
 */
class SellerApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $storeName,
        public readonly string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Vaultly seller application has been approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.seller.application-approved',
        );
    }
}