<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SellerProfile model.
 *
 * The public store profile for an approved seller.
 * Created automatically when a seller application is approved.
 * Contains store branding, social links, and financial balance.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $store_name
 * @property string      $store_slug
 * @property string|null $store_description
 * @property string|null $logo_url
 * @property string|null $banner_url
 * @property string      $paypal_email
 * @property float       $available_balance
 * @property float       $pending_balance
 * @property float       $total_earned
 * @property int         $total_sales
 */
class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'store_slug',
        'store_description',
        'logo_url',
        'banner_url',
        'website_url',
        'twitter_url',
        'github_url',
        'dribbble_url',
        'linkedin_url',
        'paypal_email',
        'available_balance',
        'pending_balance',
        'total_earned',
        'total_sales',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'decimal:2',
            'pending_balance'   => 'decimal:2',
            'total_earned'      => 'decimal:2',
            'total_sales'       => 'integer',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user account this profile belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Products listed under this store.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id', 'user_id');
    }
}