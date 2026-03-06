<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SellerApplication model.
 *
 * Represents a user's request to become a seller on Vaultly.
 * One per user. Admin reviews and approves or rejects.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $full_name
 * @property string      $store_name
 * @property string      $store_description
 * @property string      $category_focus
 * @property string      $paypal_email
 * @property string      $status           pending|approved|rejected
 * @property string|null $rejection_reason
 * @property int|null    $reviewed_by
 * @property \Carbon\Carbon|null $reviewed_at
 */
class SellerApplication extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'store_name',
        'store_description',
        'category_focus',
        'paypal_email',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user who submitted this application.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who reviewed this application.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}