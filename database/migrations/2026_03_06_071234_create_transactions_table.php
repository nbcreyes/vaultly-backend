<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactions table.
 *
 * Immutable ledger of all financial events on the platform.
 * Every sale, commission deduction, refund, and payout creates a transaction row.
 * This table is append-only — rows are never updated or deleted.
 *
 * Transaction types:
 *   sale           - buyer purchased a product
 *   commission     - platform took its 10% cut
 *   seller_credit  - 90% credited to seller balance
 *   refund         - buyer received a refund
 *   payout         - seller balance paid out
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // The user this transaction belongs to
            // For a sale: buyer_id. For seller_credit: seller_id.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', [
                'sale',
                'commission',
                'seller_credit',
                'refund',
                'payout',
            ]);

            // Positive for credits, negative for debits
            $table->decimal('amount', 10, 2);

            // Running description for display in transaction history
            $table->string('description');

            // Reference to external payment processor transaction
            $table->string('paypal_transaction_id')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};