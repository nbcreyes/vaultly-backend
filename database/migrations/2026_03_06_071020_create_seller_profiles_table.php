<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller profiles table.
 *
 * Created automatically when a seller application is approved.
 * Stores the public-facing store information and financial balance.
 * One seller profile per approved seller user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Store identity
            $table->string('store_name');
            $table->string('store_slug')->unique();
            $table->text('store_description')->nullable();

            // Cloudinary URLs for store branding
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();

            // Social links
            $table->string('website_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('dribbble_url')->nullable();
            $table->string('linkedin_url')->nullable();

            // PayPal email for receiving payouts
            $table->string('paypal_email');

            // Financial balance — decimal for precision, never use float for money
            // available_balance: funds that can be requested for payout
            // pending_balance: funds held during refund window
            // total_earned: lifetime earnings for analytics
            $table->decimal('available_balance', 12, 2)->default(0.00);
            $table->decimal('pending_balance', 12, 2)->default(0.00);
            $table->decimal('total_earned', 12, 2)->default(0.00);

            // Aggregate stats updated on each sale
            $table->unsignedInteger('total_sales')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};