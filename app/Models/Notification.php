<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification model.
 *
 * Persistent in-app notification for any user type.
 * Delivered in real-time via Pusher and stored here for history.
 * The data column holds a JSON payload with context-specific details.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $type
 * @property string      $title
 * @property string      $body
 * @property array|null  $data
 * @property string|null $action_url
 * @property \Carbon\Carbon|null $read_at
 */
class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'read_at'    => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // State helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if this notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The user who receives this notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}