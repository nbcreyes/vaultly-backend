<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User model.
 *
 * Central authentication model shared by buyers, sellers, and admins.
 * Role determines which relationships and capabilities are available.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $password
 * @property string      $role          buyer|seller|admin
 * @property string      $status        active|suspended|banned
 * @property string|null $avatar_url
 * @property \Carbon\Carbon|null $email_verified_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'avatar_url',
        'email_verified_at',
        'email_verification_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if the user is a buyer.
     */
    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    /**
     * Determine if the user is a seller.
     */
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Determine if the user account is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine if the user account is banned.
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The seller profile associated with this user.
     * Only exists for users with role = seller.
     */
    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    /**
     * The seller application submitted by this user.
     */
    public function sellerApplication(): HasOne
    {
        return $this->hasOne(SellerApplication::class);
    }

    /**
     * Products listed by this seller.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    /**
     * Orders placed by this buyer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /**
     * Reviews written by this buyer.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'buyer_id');
    }

    /**
     * Messages sent by this user.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    /**
     * Refund requests submitted by this buyer.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'buyer_id');
    }

    /**
     * Payout requests submitted by this seller.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'seller_id');
    }

    /**
     * Transactions associated with this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Notifications for this user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Download records for this buyer.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class, 'buyer_id');
    }
}