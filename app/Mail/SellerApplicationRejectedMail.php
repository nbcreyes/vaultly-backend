<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SellerApplicationRejectedMail
 *
 * Sent to a user when their seller application is rejected by admin.
 * Includes the rejection reason provided by the admin.
 */
class SellerApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $storeName,
        public readonly string $rejectionReason,
        public readonly string $reapplyUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on your Vaultly seller application',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.seller.application-rejected',
        );
    }
}