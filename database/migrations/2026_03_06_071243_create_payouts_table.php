<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payouts table.
 *
 * A seller requests a payout of their available balance.
 * Admin processes it manually via PayPal and marks it as paid.
 * The seller's available_balance is decremented on request submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('amount', 10, 2);

            // PayPal email at time of request — seller may change it later
            $table->string('paypal_email');

            $table->enum('status', ['pending', 'paid', 'rejected'])->default('pending');

            // Admin fills these in when processing
            $table->string('paypal_payout_id')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index('seller_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};