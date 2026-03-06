<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders table.
 *
 * One order per checkout session. A buyer may purchase multiple products
 * in one checkout (order items). In practice for a digital marketplace
 * most orders contain one item, but the architecture supports multiple.
 *
 * PayPal order and capture IDs are stored for webhook reconciliation
 * and refund processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();

            // Internal order reference shown to users
            $table->string('order_number')->unique();

            // Order financial summary
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);

            // Order lifecycle status
            // pending: PayPal order created, awaiting payment capture
            // completed: payment captured successfully
            // refunded: full refund processed
            // partially_refunded: one or more items refunded
            $table->enum('status', ['pending', 'completed', 'refunded', 'partially_refunded'])->default('pending');

            // PayPal references for reconciliation
            $table->string('paypal_order_id')->nullable();
            $table->string('paypal_capture_id')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('buyer_id');
            $table->index('status');
            $table->index('paypal_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};