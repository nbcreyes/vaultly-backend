<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Review model.
 *
 * A buyer's rating and written review of a purchased product.
 * One review per buyer per product, enforced at the database level.
 * The seller may reply once. Admin can hide abusive reviews.
 *
 * @property int         $id
 * @property int         $product_id
 * @property int         $buyer_id
 * @property int         $order_item_id
 * @property int         $rating           1 to 5
 * @property string      $body
 * @property string|null $seller_reply
 * @property \Carbon\Carbon|null $seller_replied_at
 * @property bool        $is_visible
 */
class Review extends Model
{
    protected $fillable = [
        'product_id',
        'buyer_id',
        'order_item_id',
        'rating',
        'body',
        'seller_reply',
        'seller_replied_at',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'rating'            => 'integer',
            'is_visible'        => 'boolean',
            'seller_replied_at' => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The product being reviewed.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The buyer who wrote this review.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * The order item this review is associated with.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}