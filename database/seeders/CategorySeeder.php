<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Code & Scripts',    'slug' => 'code',          'icon' => '💻', 'description' => 'PHP, JavaScript, Python scripts and full applications'],
            ['name' => 'Design Assets',     'slug' => 'design',        'icon' => '🎨', 'description' => 'UI kits, icons, illustrations, and graphic templates'],
            ['name' => 'Education',         'slug' => 'education',     'icon' => '📚', 'description' => 'Courses, ebooks, guides, and learning resources'],
            ['name' => 'Fonts',             'slug' => 'fonts',         'icon' => '✍️',  'description' => 'Premium typefaces and font families'],
            ['name' => 'Templates',         'slug' => 'templates',     'icon' => '📄', 'description' => 'Resume, business, and document templates'],
            ['name' => 'Audio & Music',     'slug' => 'audio',         'icon' => '🎵', 'description' => 'Sound effects, music tracks, and audio assets'],
            ['name' => 'Video',             'slug' => 'video',         'icon' => '🎬', 'description' => 'Motion graphics, transitions, and video templates'],
            ['name' => 'Photography',       'slug' => 'photography',   'icon' => '📷', 'description' => 'Stock photos, presets, and photography resources'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}