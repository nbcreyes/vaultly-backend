<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order items table.
 *
 * Each row is one product in one order.
 * Price and commission are recorded at the time of purchase so historical
 * order data is accurate even if the product price changes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            // Snapshot of pricing at time of purchase
            $table->decimal('price', 10, 2);
            $table->decimal('platform_fee', 10, 2)->comment('10% platform commission');
            $table->decimal('seller_earnings', 10, 2)->comment('90% paid to seller');

            // Item status — tracks refund state per item
            $table->enum('status', ['active', 'refunded'])->default('active');

            $table->timestamps();

            $table->index('order_id');
            $table->index('seller_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};