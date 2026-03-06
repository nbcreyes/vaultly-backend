<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products table.
 *
 * Core product listing. Each product belongs to one seller and one category.
 * The product file is stored on Cloudinary and proxied securely through the backend.
 * Preview images are stored in the product_images table (up to 5 per product).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // Core listing fields
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description');
            $table->longText('description');
            $table->decimal('price', 10, 2);

            // License type
            $table->enum('license_type', ['personal', 'commercial'])->default('personal');

            // Product file — stored on Cloudinary
            // We store the Cloudinary public_id, not the raw URL.
            // The URL is generated at download time and never exposed directly.
            $table->string('file_cloudinary_id');
            $table->string('file_name');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size')->comment('File size in bytes');

            // Versioning
            $table->string('version')->default('1.0.0');
            $table->text('changelog')->nullable();

            // Listing status
            // draft: seller saved but not submitted
            // pending: submitted, waiting for first review (future feature, not used at launch)
            // published: live and visible
            // unpublished: seller or admin took it down
            // rejected: admin removed and blocked relisting
            $table->enum('status', ['draft', 'published', 'unpublished', 'rejected'])->default('draft');

            // Aggregate stats — denormalized for query performance
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);

            // Admin moderation note
            $table->text('admin_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for browse and search queries
            $table->index('seller_id');
            $table->index('category_id');
            $table->index('status');
            $table->index('price');
            $table->index('average_rating');
            $table->index('sales_count');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};