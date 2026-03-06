<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message model.
 *
 * A message in the conversation thread for one order.
 * Each order has one thread. Messages are between buyer and seller.
 *
 * @property int         $id
 * @property int         $order_id
 * @property int         $sender_id
 * @property int         $recipient_id
 * @property string      $body
 * @property \Carbon\Carbon|null $read_at
 */
class Message extends Model
{
    protected $fillable = [
        'order_id',
        'sender_id',
        'recipient_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if this message has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The order this message belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The user who sent this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The user who received this message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}