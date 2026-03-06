<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform settings table.
 *
 * Key-value store for admin-configurable platform settings.
 * Seeded with defaults at deployment time.
 *
 * Keys used by the application:
 *   commission_rate          - percentage taken per sale (default: 10)
 *   maintenance_mode         - boolean, blocks all non-admin access
 *   max_product_images       - maximum preview images per product (default: 5)
 *   download_expiry_hours    - download link lifetime in hours (default: 48)
 *   refund_window_hours      - refund request window in hours (default: 72)
 *   download_window_days     - re-download window in days (default: 30)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string')->comment('string, boolean, integer, decimal');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};