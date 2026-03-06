<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CategorySeeder
 *
 * Seeds the five core product categories for Vaultly.
 * Run once on fresh deployment. Safe to re-run (uses upsert).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Code and Scripts',
                'slug'        => 'code-and-scripts',
                'description' => 'Themes, plugins, components, templates, and developer tools.',
                'icon'        => 'code',
                'sort_order'  => 1,
            ],
            [
                'name'        => 'Design Assets',
                'slug'        => 'design-assets',
                'description' => 'UI kits, icons, illustrations, mockups, and fonts.',
                'icon'        => 'palette',
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Documents and Templates',
                'slug'        => 'documents-and-templates',
                'description' => 'Resume templates, business documents, and presentations.',
                'icon'        => 'file-text',
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Media',
                'slug'        => 'media',
                'description' => 'Music, sound effects, stock photos, and videos.',
                'icon'        => 'film',
                'sort_order'  => 4,
            ],
            [
                'name'        => 'Education',
                'slug'        => 'education',
                'description' => 'Ebooks, guides, and courses in PDF or ZIP format.',
                'icon'        => 'book-open',
                'sort_order'  => 5,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->upsert(
                array_merge($category, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                ['slug'],
                ['name', 'description', 'icon', 'sort_order', 'updated_at']
            );
        }
    }
}