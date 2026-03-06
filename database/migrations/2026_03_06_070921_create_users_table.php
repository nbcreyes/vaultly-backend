<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users table.
 *
 * Central user table shared by all account types (buyer, seller, admin).
 * The role column determines what a user can do on the platform.
 * Sellers additionally have a seller_profiles row after application approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // Role — buyer is the default registration role
            // admin is set manually in the database, never via API
            $table->enum('role', ['buyer', 'seller', 'admin'])->default('buyer');

            // Account status
            // active: normal access
            // suspended: temporary restriction, can still log in but cannot transact
            // banned: permanent restriction, login blocked
            $table->enum('status', ['active', 'suspended', 'banned'])->default('active');

            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();

            // Avatar — stored as Cloudinary URL
            $table->string('avatar_url')->nullable();

            // Password reset
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};