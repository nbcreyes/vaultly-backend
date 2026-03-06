<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tag model.
 *
 * Freeform tags attached to products by sellers.
 * Tags are created on the fly when saving a product.
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 */
class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Products that have this tag.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}