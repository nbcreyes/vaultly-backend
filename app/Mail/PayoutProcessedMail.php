<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PayoutProcessedMail
 *
 * Sent to a seller when their payout request is marked as paid
 * or rejected by an admin.
 */
class PayoutProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $userName,
        public readonly float   $amount,
        public readonly string  $status,
        public readonly ?string $adminNote,
        public readonly string  $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->status === 'paid'
            ? 'Your Vaultly payout has been processed'
            : 'Update on your Vaultly payout request';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.seller.payout-processed',
        );
    }
}