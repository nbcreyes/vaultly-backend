<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * PlatformSettingSeeder
 *
 * Seeds default platform configuration values.
 * Run once on fresh deployment. Safe to re-run (uses upsert).
 * Admin can update these values through the admin dashboard.
 */
class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'commission_rate',
                'value'       => '10',
                'type'        => 'integer',
                'description' => 'Percentage the platform takes from each sale.',
            ],
            [
                'key'         => 'maintenance_mode',
                'value'       => 'false',
                'type'        => 'boolean',
                'description' => 'When true, all non-admin access is blocked.',
            ],
            [
                'key'         => 'max_product_images',
                'value'       => '5',
                'type'        => 'integer',
                'description' => 'Maximum number of preview images per product.',
            ],
            [
                'key'         => 'download_expiry_hours',
                'value'       => '48',
                'type'        => 'integer',
                'description' => 'How long a download link is valid in hours.',
            ],
            [
                'key'         => 'refund_window_hours',
                'value'       => '72',
                'type'        => 'integer',
                'description' => 'How long a buyer has to request a refund in hours.',
            ],
            [
                'key'         => 'download_window_days',
                'value'       => '30',
                'type'        => 'integer',
                'description' => 'How many days a buyer can re-download a purchased product.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('platform_settings')->upsert(
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                ['key'],
                ['value', 'type', 'description', 'updated_at']
            );
        }
    }
}