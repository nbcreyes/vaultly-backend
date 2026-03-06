<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages table.
 *
 * Direct messaging between buyers and sellers scoped to a specific order.
 * Each order has one conversation thread. Messages are simple and
 * do not support attachments at this version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            // The order this conversation is about
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Who sent and who receives the message
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();

            $table->text('body');

            // Null until the recipient reads the message
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('sender_id');
            $table->index('recipient_id');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};