<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PlatformSetting model.
 *
 * Admin-configurable key-value settings for the platform.
 * Use the static get() helper anywhere in the app to retrieve a value
 * with automatic type casting based on the type column.
 *
 * @property int    $id
 * @property string $key
 * @property string $value
 * @property string $type    string|boolean|integer|decimal
 * @property string $description
 */
class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve a platform setting value by key with automatic type casting.
     *
     * Usage:
     *   PlatformSetting::get('commission_rate')    // returns 10 (integer)
     *   PlatformSetting::get('maintenance_mode')   // returns false (boolean)
     *   PlatformSetting::get('missing_key', 'default') // returns 'default'
     *
     * @param  string $key
     * @param  mixed  $default  Returned if the key does not exist.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'decimal' => (float) $setting->value,
            default   => $setting->value,
        };
    }

    /**
     * Update or create a platform setting by key.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => (string) $value]);
    }
}