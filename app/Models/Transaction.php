<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction model.
 *
 * Append-only financial ledger entry.
 * Every sale, commission, seller credit, refund, and payout
 * creates a row here. Rows are never updated or deleted.
 *
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $order_item_id
 * @property string      $type              sale|commission|seller_credit|refund|payout
 * @property float       $amount            Positive for credit, negative for debit
 * @property string      $description
 * @property string|null $paypal_transaction_id
 */
class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'order_item_id',
        'type',
        'amount',
        'description',
        'paypal_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user this transaction belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The order item this transaction is associated with.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}