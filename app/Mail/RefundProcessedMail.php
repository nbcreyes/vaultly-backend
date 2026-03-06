<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer when their refund is approved or rejected.
 * Also sent to the seller when a refund on their product is approved.
 */
class RefundProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string  $userName,
        public readonly string  $productTitle,
        public readonly float   $amount,
        public readonly string  $status,
        public readonly ?string $adminNote,
        public readonly string  $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->status === 'approved'
            ? 'Your refund has been approved — Vaultly'
            : 'Update on your refund request — Vaultly';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.buyer.refund-processed');
    }
}