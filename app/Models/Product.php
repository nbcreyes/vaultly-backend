<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product model.
 *
 * A digital product listed by an approved seller.
 * Contains metadata, Cloudinary file references, pricing,
 * and denormalized aggregate stats for performance.
 *
 * @property int         $id
 * @property int         $seller_id
 * @property int         $category_id
 * @property string      $title
 * @property string      $slug
 * @property string      $short_description
 * @property string      $description
 * @property float       $price
 * @property string      $license_type      personal|commercial
 * @property string      $file_cloudinary_id
 * @property string      $file_name
 * @property string      $file_type
 * @property int         $file_size
 * @property string      $version
 * @property string|null $changelog
 * @property string      $status            draft|published|unpublished|rejected
 * @property float       $average_rating
 * @property int         $review_count
 * @property int         $sales_count
 * @property int         $view_count
 * @property string|null $admin_note
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'category_id',
        'title',
        'slug',
        'short_description',
        'description',
        'price',
        'license_type',
        'file_cloudinary_id',
        'file_name',
        'file_type',
        'file_size',
        'version',
        'changelog',
        'status',
        'average_rating',
        'review_count',
        'sales_count',
        'view_count',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'average_rating' => 'decimal:2',
            'review_count'   => 'integer',
            'sales_count'    => 'integer',
            'view_count'     => 'integer',
            'file_size'      => 'integer',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
            'deleted_at'     => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The seller who owns this product.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Preview images for this product (up to 5).
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Tags attached to this product.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Order items where this product was purchased.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Reviews left on this product.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_visible', true);
    }

    /**
     * All reviews including hidden ones (for admin use).
     */
    public function allReviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Download records for this product.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }
}