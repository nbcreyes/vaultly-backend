<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Downloads table.
 *
 * Each row represents one download token for one purchased product.
 * When a buyer requests a download, a signed token is generated and stored here.
 * The token expires after 48 hours. The buyer can regenerate a new token
 * from their purchase history at any time within the 30-day window.
 *
 * The actual Cloudinary file ID is never exposed to the buyer.
 * The backend uses the token to look up the file and proxy it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Secure random token used in the download URL
            $table->string('token', 64)->unique();

            // When this token expires — set to 48 hours from generation
            $table->timestamp('expires_at');

            // When the buyer actually downloaded the file (null if not yet used)
            $table->timestamp('downloaded_at')->nullable();

            // Whether this token has been revoked (e.g. after a refund)
            $table->boolean('is_revoked')->default(false);

            $table->timestamps();

            $table->index('token');
            $table->index('buyer_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};