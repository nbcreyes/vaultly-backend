<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories table.
 *
 * Top-level product categories. Seeded at deployment time.
 * Not user-created — only admin can manage categories.
 *
 * The five categories for Vaultly:
 *   - Code and Scripts
 *   - Design Assets
 *   - Documents and Templates
 *   - Media
 *   - Education
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Icon name — maps to a frontend icon component
            $table->string('icon')->nullable();

            // Controls display order on the homepage
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};