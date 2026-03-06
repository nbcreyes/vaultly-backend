<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refund model.
 *
 * A buyer's request to refund a purchased product.
 * Must be submitted within 72 hours of purchase.
 * Admin reviews and approves or rejects.
 * On approval, PayPal refund is issued and downloads are revoked.
 *
 * @property int         $id
 * @property int         $order_item_id
 * @property int         $buyer_id
 * @property int         $seller_id
 * @property string      $reason            broken_file|not_as_described|duplicate_purchase|other
 * @property string|null $details
 * @property string      $status            pending|approved|rejected
 * @property string|null $admin_note
 * @property int|null    $reviewed_by
 * @property \Carbon\Carbon|null $reviewed_at
 * @property string|null $paypal_refund_id
 * @property float|null  $amount
 */
class Refund extends Model
{
    protected $fillable = [
        'order_item_id',
        'buyer_id',
        'seller_id',
        'reason',
        'details',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'paypal_refund_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'reviewed_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The order item this refund applies to.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * The buyer who requested the refund.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * The seller whose sale is being refunded.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The admin who reviewed this refund request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}