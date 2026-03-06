<?php

namespace App\Services;

use App\Models\Notification;

/**
 * NotificationService
 *
 * Central service for creating in-app notifications.
 *
 * Every notification has:
 *   - user_id:  the recipient
 *   - type:     a snake_case string identifying the event
 *   - title:    short heading shown in the notification bell
 *   - body:     longer description shown on hover or in the list
 *   - data:     JSON payload with IDs needed to build the frontend link
 *   - read_at:  null until the user dismisses it
 *
 * Notification types used across the platform:
 *   order_confirmed         - buyer purchased a product
 *   new_sale                - seller made a sale
 *   refund_requested        - admin needs to review a refund
 *   refund_approved         - buyer's refund was approved
 *   refund_rejected         - buyer's refund was rejected
 *   refund_deducted         - seller's balance was reduced due to refund
 *   new_review              - seller received a new review
 *   review_reply            - buyer received a reply to their review
 *   new_message             - user received a new message
 *   payout_paid             - seller's payout was processed
 *   payout_rejected         - seller's payout was rejected
 *   seller_approved         - buyer's seller application was approved
 *   seller_rejected         - buyer's seller application was rejected
 *   download_expiring_soon  - buyer's download link expires in 24 hours
 */
class NotificationService
{
    /**
     * Notify a buyer that their order was confirmed.
     *
     * @param  int    $buyerId
     * @param  string $orderNumber
     * @param  int    $orderId
     * @return void
     */
    public function orderConfirmed(int $buyerId, string $orderNumber, int $orderId): void
    {
        $this->create($buyerId, 'order_confirmed', [
            'title' => 'Order confirmed',
            'body'  => "Your order {$orderNumber} has been confirmed. Your downloads are ready.",
            'data'  => ['order_id' => $orderId],
        ]);
    }

    /**
     * Notify a seller that they made a sale.
     *
     * @param  int    $sellerId
     * @param  string $productTitle
     * @param  int    $productId
     * @param  float  $earnings
     * @return void
     */
    public function newSale(int $sellerId, string $productTitle, int $productId, float $earnings): void
    {
        $this->create($sellerId, 'new_sale', [
            'title' => 'New sale',
            'body'  => "You sold \"{$productTitle}\" and earned \$" . number_format($earnings, 2) . '.',
            'data'  => ['product_id' => $productId],
        ]);
    }

    /**
     * Notify the buyer that their refund request was received.
     *
     * @param  int    $buyerId
     * @param  string $productTitle
     * @param  int    $refundId
     * @return void
     */
    public function refundRequested(int $buyerId, string $productTitle, int $refundId): void
    {
        $this->create($buyerId, 'refund_requested', [
            'title' => 'Refund request received',
            'body'  => "Your refund request for \"{$productTitle}\" is under review.",
            'data'  => ['refund_id' => $refundId],
        ]);
    }

    /**
     * Notify the buyer that their refund was approved.
     *
     * @param  int    $buyerId
     * @param  string $productTitle
     * @param  float  $amount
     * @param  int    $refundId
     * @return void
     */
    public function refundApproved(int $buyerId, string $productTitle, float $amount, int $refundId): void
    {
        $this->create($buyerId, 'refund_approved', [
            'title' => 'Refund approved',
            'body'  => "Your refund of \$" . number_format($amount, 2) . " for \"{$productTitle}\" has been approved.",
            'data'  => ['refund_id' => $refundId],
        ]);
    }

    /**
     * Notify the buyer that their refund was rejected.
     *
     * @param  int    $buyerId
     * @param  string $productTitle
     * @param  int    $refundId
     * @return void
     */
    public function refundRejected(int $buyerId, string $productTitle, int $refundId): void
    {
        $this->create($buyerId, 'refund_rejected', [
            'title' => 'Refund not approved',
            'body'  => "Your refund request for \"{$productTitle}\" could not be approved.",
            'data'  => ['refund_id' => $refundId],
        ]);
    }

    /**
     * Notify the seller that their balance was deducted due to a refund.
     *
     * @param  int    $sellerId
     * @param  string $productTitle
     * @param  float  $deductedAmount
     * @param  int    $refundId
     * @return void
     */
    public function refundDeducted(int $sellerId, string $productTitle, float $deductedAmount, int $refundId): void
    {
        $this->create($sellerId, 'refund_deducted', [
            'title' => 'Balance adjustment',
            'body'  => "\$" . number_format($deductedAmount, 2) . " was deducted from your balance due to a refund on \"{$productTitle}\".",
            'data'  => ['refund_id' => $refundId],
        ]);
    }

    /**
     * Notify the seller that they received a new review.
     *
     * @param  int    $sellerId
     * @param  string $productTitle
     * @param  int    $rating
     * @param  int    $reviewId
     * @param  int    $productId
     * @return void
     */
    public function newReview(int $sellerId, string $productTitle, int $rating, int $reviewId, int $productId): void
    {
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

        $this->create($sellerId, 'new_review', [
            'title' => 'New review received',
            'body'  => "Someone left a {$stars} review on \"{$productTitle}\".",
            'data'  => ['review_id' => $reviewId, 'product_id' => $productId],
        ]);
    }

    /**
     * Notify the buyer that the seller replied to their review.
     *
     * @param  int    $buyerId
     * @param  string $productTitle
     * @param  int    $reviewId
     * @param  int    $productId
     * @return void
     */
    public function reviewReply(int $buyerId, string $productTitle, int $reviewId, int $productId): void
    {
        $this->create($buyerId, 'review_reply', [
            'title' => 'Seller replied to your review',
            'body'  => "The seller responded to your review on \"{$productTitle}\".",
            'data'  => ['review_id' => $reviewId, 'product_id' => $productId],
        ]);
    }

    /**
     * Notify a user that they received a new message.
     *
     * @param  int    $recipientId
     * @param  string $senderName
     * @param  int    $orderId
     * @param  string $orderNumber
     * @return void
     */
    public function newMessage(int $recipientId, string $senderName, int $orderId, string $orderNumber): void
    {
        $this->create($recipientId, 'new_message', [
            'title' => 'New message',
            'body'  => "{$senderName} sent you a message about order {$orderNumber}.",
            'data'  => ['order_id' => $orderId],
        ]);
    }

    /**
     * Notify the seller that their payout was processed.
     *
     * @param  int   $sellerId
     * @param  float $amount
     * @param  int   $payoutId
     * @return void
     */
    public function payoutPaid(int $sellerId, float $amount, int $payoutId): void
    {
        $this->create($sellerId, 'payout_paid', [
            'title' => 'Payout processed',
            'body'  => "Your payout of \$" . number_format($amount, 2) . " has been sent to your PayPal account.",
            'data'  => ['payout_id' => $payoutId],
        ]);
    }

    /**
     * Notify the seller that their payout was rejected.
     *
     * @param  int   $sellerId
     * @param  float $amount
     * @param  int   $payoutId
     * @return void
     */
    public function payoutRejected(int $sellerId, float $amount, int $payoutId): void
    {
        $this->create($sellerId, 'payout_rejected', [
            'title' => 'Payout not processed',
            'body'  => "Your payout request of \$" . number_format($amount, 2) . " could not be processed. Your balance has been restored.",
            'data'  => ['payout_id' => $payoutId],
        ]);
    }

    /**
     * Notify the applicant that their seller application was approved.
     *
     * @param  int $userId
     * @return void
     */
    public function sellerApproved(int $userId): void
    {
        $this->create($userId, 'seller_approved', [
            'title' => 'Seller application approved',
            'body'  => 'Congratulations! Your seller application has been approved. You can now list products on Vaultly.',
            'data'  => [],
        ]);
    }

    /**
     * Notify the applicant that their seller application was rejected.
     *
     * @param  int    $userId
     * @param  string $reason
     * @return void
     */
    public function sellerRejected(int $userId, string $reason): void
    {
        $this->create($userId, 'seller_rejected', [
            'title' => 'Seller application not approved',
            'body'  => "Your seller application was not approved. Reason: {$reason}",
            'data'  => [],
        ]);
    }

    /**
     * Internal helper to create a notification record.
     *
     * @param  int                  $userId
     * @param  string               $type
     * @param  array<string, mixed> $payload
     * @return void
     */
    private function create(int $userId, string $type, array $payload): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $payload['title'],
                'body'    => $payload['body'],
                'data'    => $payload['data'],
            ]);
        } catch (\Throwable $e) {
            // Never let a notification failure break the main request
            \Illuminate\Support\Facades\Log::warning('Notification creation failed', [
                'user_id' => $userId,
                'type'    => $type,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}