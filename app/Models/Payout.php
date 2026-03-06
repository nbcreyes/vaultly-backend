<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payout model.
 *
 * A seller's request to withdraw their available balance.
 * Admin processes manually via PayPal and marks as paid.
 *
 * @property int         $id
 * @property int         $seller_id
 * @property float       $amount
 * @property string      $paypal_email
 * @property string      $status             pending|paid|rejected
 * @property string|null $paypal_payout_id
 * @property string|null $admin_note
 * @property int|null    $processed_by
 * @property \Carbon\Carbon|null $processed_at
 */
class Payout extends Model
{
    protected $fillable = [
        'seller_id',
        'amount',
        'paypal_email',
        'status',
        'paypal_payout_id',
        'admin_note',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'processed_at' => 'datetime',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The seller who requested this payout.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The admin who processed this payout.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}