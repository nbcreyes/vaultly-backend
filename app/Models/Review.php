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
 * @property int         $rating
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    // -------------------------------------------------------------------------
    // Aggregate helpers
    // -------------------------------------------------------------------------

    /**
     * Recalculate and update the aggregate rating on a product.
     *
     * Called after every review create, update, or hide operation
     * to keep the product's average_rating and review_count accurate.
     *
     * Only visible reviews are counted in the aggregate.
     *
     * @param  int $productId
     * @return void
     */
    public static function recalculateForProduct(int $productId): void
    {
        $aggregate = static::where('product_id', $productId)
            ->where('is_visible', true)
            ->selectRaw('COUNT(*) as count, AVG(rating) as average')
            ->first();

        Product::where('id', $productId)->update([
            'review_count'   => $aggregate->count ?? 0,
            'average_rating' => $aggregate->average ? round((float) $aggregate->average, 2) : 0.00,
        ]);
    }
}