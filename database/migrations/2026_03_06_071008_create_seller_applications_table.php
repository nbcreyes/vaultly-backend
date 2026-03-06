<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller applications table.
 *
 * A user submits one application to become a seller.
 * Admin reviews it and approves or rejects with a reason.
 * On approval, the user's role is updated to seller and
 * a seller_profiles row is created automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Application details submitted by the user
            $table->string('full_name');
            $table->string('store_name');
            $table->text('store_description');
            $table->string('category_focus');
            $table->string('paypal_email');

            // Review status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Admin fills this in when rejecting
            $table->text('rejection_reason')->nullable();

            // Which admin reviewed the application
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // A user can only have one application at a time
            $table->unique('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_applications');
    }
};