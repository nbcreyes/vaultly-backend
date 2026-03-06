<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds table.
 *
 * A buyer submits a refund request within 72 hours of purchase.
 * Admin reviews and approves or rejects the request.
 * On approval the PayPal refund is issued and download access is revoked.
 * Both buyer and seller are notified of the outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            // Reason selected by buyer
            $table->enum('reason', [
                'broken_file',
                'not_as_described',
                'duplicate_purchase',
                'other',
            ]);

            // Optional additional detail from buyer
            $table->text('details')->nullable();

            // Review status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Admin decision fields
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // PayPal refund transaction ID — populated on approval
            $table->string('paypal_refund_id')->nullable();

            // Amount refunded
            $table->decimal('amount', 10, 2)->nullable();

            $table->timestamps();

            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};