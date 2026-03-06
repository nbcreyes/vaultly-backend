<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category model.
 *
 * One of the five top-level product categories.
 * Categories are seeded and managed by admin only.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property string $icon
 * @property int    $sort_order
 * @property bool   $is_active
 */
class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All products in this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}