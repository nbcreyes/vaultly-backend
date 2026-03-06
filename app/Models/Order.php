<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order model.
 *
 * Represents one checkout session by a buyer.
 * Contains one or more order items.
 * Financial totals are stored at time of purchase.
 *
 * @property int         $id
 * @property int         $buyer_id
 * @property string      $order_number
 * @property float       $subtotal
 * @property float       $total
 * @property string      $status           pending|completed|refunded|partially_refunded
 * @property string|null $paypal_order_id
 * @property string|null $paypal_capture_id
 * @property \Carbon\Carbon|null $paid_at
 */
class Order extends Model
{
    protected $fillable = [
        'buyer_id',
        'order_number',
        'subtotal',
        'total',
        'status',
        'paypal_order_id',
        'paypal_capture_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'   => 'decimal:2',
            'total'      => 'decimal:2',
            'paid_at'    => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The buyer who placed this order.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * All items in this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Messages in this order's conversation thread.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }
}