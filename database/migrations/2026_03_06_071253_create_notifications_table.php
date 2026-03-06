<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications table.
 *
 * Stores persistent in-app notifications for all user types.
 * Real-time delivery is handled by Pusher. This table provides
 * the notification bell history and persists notifications
 * across sessions.
 *
 * The data column stores a JSON payload with context-specific
 * information (e.g. order ID, product title, refund reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Who receives this notification
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', [
                'product_purchased',
                'review_received',
                'refund_requested',
                'refund_approved',
                'refund_rejected',
                'seller_application_approved',
                'seller_application_rejected',
                'payout_processed',
                'payout_rejected',
                'new_message',
            ]);

            // Human-readable notification title and body
            $table->string('title');
            $table->string('body');

            // Contextual data — JSON payload
            // e.g. { "order_id": 42, "product_title": "UI Kit Pro" }
            $table->json('data')->nullable();

            // Frontend route to navigate to when clicked
            $table->string('action_url')->nullable();

            // Null means unread
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('read_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};