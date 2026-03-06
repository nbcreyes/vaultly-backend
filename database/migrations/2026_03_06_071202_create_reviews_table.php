<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews table.
 *
 * A buyer can leave one review per purchased product.
 * The seller can respond once to each review.
 * Rating is 1 to 5 stars stored as a tinyint.
 * After each insert or update, the product's average_rating
 * and review_count columns are recalculated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            // Rating 1 to 5
            $table->unsignedTinyInteger('rating');

            // Review body
            $table->text('body');

            // Seller reply
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();

            // Admin can hide abusive reviews
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            // One review per buyer per product
            $table->unique(['product_id', 'buyer_id']);
            $table->index('product_id');
            $table->index('buyer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};