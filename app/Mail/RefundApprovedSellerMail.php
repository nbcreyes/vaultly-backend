<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the seller when a refund on one of their products is approved.
 */
class RefundApprovedSellerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $sellerName,
        public readonly string $productTitle,
        public readonly float  $amount,
        public readonly float  $deductedAmount,
        public readonly string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A refund has been issued on your product — Vaultly');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.seller.refund-approved');
    }
}