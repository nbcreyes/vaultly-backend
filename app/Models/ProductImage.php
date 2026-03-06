<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductImage model.
 *
 * One of up to five preview images for a product.
 * Stored on Cloudinary. The URL is public and safe to expose.
 *
 * @property int    $id
 * @property int    $product_id
 * @property string $cloudinary_id
 * @property string $url
 * @property int    $sort_order
 */
class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'cloudinary_id',
        'url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The product this image belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}