<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product images table.
 *
 * Stores up to 5 preview images per product.
 * Images are uploaded to Cloudinary and the public_id is stored here.
 * The sort_order column controls display order in the image gallery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Cloudinary identifiers
            $table->string('cloudinary_id');
            $table->string('url');

            // Display order in the product gallery (0 = primary image)
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};