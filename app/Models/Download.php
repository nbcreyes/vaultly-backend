<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Download model.
 *
 * A secure expiring token for one purchased product file.
 * The token is used in a signed URL. The backend resolves
 * the Cloudinary file and proxies the download.
 * Direct Cloudinary URLs are never exposed to the buyer.
 *
 * @property int         $id
 * @property int         $order_item_id
 * @property int         $buyer_id
 * @property int         $product_id
 * @property string      $token
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $downloaded_at
 * @property bool        $is_revoked
 */
class Download extends Model
{
    protected $fillable = [
        'order_item_id',
        'buyer_id',
        'product_id',
        'token',
        'expires_at',
        'downloaded_at',
        'is_revoked',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'     => 'datetime',
            'downloaded_at'  => 'datetime',
            'is_revoked'     => 'boolean',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if this download token is currently usable.
     * A token is valid when it has not expired and has not been revoked.
     */
    public function isValid(): bool
    {
        return !$this->is_revoked && $this->expires_at->isFuture();
    }

    /**
     * Determine if this token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The order item this download belongs to.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * The buyer who owns this download.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * The product being downloaded.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}