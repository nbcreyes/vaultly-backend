<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * OrderItem model.
 *
 * One purchased product within an order.
 * Stores a price snapshot so historical data is never affected
 * by future price changes on the product.
 *
 * @property int    $id
 * @property int    $order_id
 * @property int    $product_id
 * @property int    $seller_id
 * @property float  $price
 * @property float  $platform_fee
 * @property float  $seller_earnings
 * @property string $status          active|refunded
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'seller_id',
        'price',
        'platform_fee',
        'seller_earnings',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'platform_fee'    => 'decimal:2',
            'seller_earnings' => 'decimal:2',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The order this item belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The product that was purchased.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The seller of this item.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Download records generated for this item.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    /**
     * The review the buyer left for this item.
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * The refund request for this item.
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }
}