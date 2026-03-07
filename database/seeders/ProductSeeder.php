<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::where('email', 'alice@vaultly.com')->first();
        $bob   = User::where('email', 'bob@vaultly.com')->first();
        $carol = User::where('email', 'carol@vaultly.com')->first();

        $code   = Category::where('slug', 'code')->first();
        $design = Category::where('slug', 'design')->first();
        $edu    = Category::where('slug', 'education')->first();
        $tpl    = Category::where('slug', 'templates')->first();

        $placeholders = [
            'https://picsum.photos/seed/product1/800/450',
            'https://picsum.photos/seed/product2/800/450',
            'https://picsum.photos/seed/product3/800/450',
            'https://picsum.photos/seed/product4/800/450',
            'https://picsum.photos/seed/product5/800/450',
            'https://picsum.photos/seed/product6/800/450',
            'https://picsum.photos/seed/product7/800/450',
            'https://picsum.photos/seed/product8/800/450',
            'https://picsum.photos/seed/product9/800/450',
            'https://picsum.photos/seed/product10/800/450',
            'https://picsum.photos/seed/product11/800/450',
            'https://picsum.photos/seed/product12/800/450',
        ];

        $products = [
            [
                'seller'       => $alice,
                'category'     => $design,
                'title'        => 'Nova UI Kit — 200+ Components',
                'slug'         => 'nova-ui-kit-200-components',
                'short_description' => 'A comprehensive Figma UI kit with 200+ ready-to-use components.',
                'description'  => "Nova UI Kit is the ultimate design system for modern web applications.\n\nIncludes:\n- 200+ Figma components\n- 12 pre-built page templates\n- Dark and light mode\n- Auto-layout and variants\n- Free updates for life\n\nCompatible with Figma, Sketch, and Adobe XD.",
                'price'        => 39.00,
                'license_type' => 'commercial',
                'version'      => '3.2.0',
                'file_type'    => 'zip',
                'file_size'    => 18400000,
                'status'       => 'published',
                'sales_count'  => 142,
                'img_index'    => 0,
                'tags'         => ['figma', 'ui-kit', 'design-system', 'components'],
            ],
            [
                'seller'       => $alice,
                'category'     => $design,
                'title'        => 'Minimal Icon Pack — 500 SVG Icons',
                'slug'         => 'minimal-icon-pack-500-svg',
                'short_description' => '500 clean SVG icons in stroke style, 6 sizes, MIT license.',
                'description'  => "A clean, consistent icon library designed for modern UI.\n\nIncludes:\n- 500 unique icons\n- 6 sizes (16px–96px)\n- SVG, PNG, and Webfont formats\n- MIT licensed for commercial use\n- Figma source file included",
                'price'        => 19.00,
                'license_type' => 'commercial',
                'version'      => '2.0.0',
                'file_type'    => 'zip',
                'file_size'    => 8200000,
                'status'       => 'published',
                'sales_count'  => 89,
                'img_index'    => 1,
                'tags'         => ['icons', 'svg', 'ui', 'design'],
            ],
            [
                'seller'       => $alice,
                'category'     => $tpl,
                'title'        => 'SaaS Landing Page — Figma Template',
                'slug'         => 'saas-landing-page-figma-template',
                'short_description' => 'Conversion-optimised SaaS landing page template in Figma.',
                'description'  => "A pixel-perfect SaaS landing page template built for conversion.\n\nSections included:\n- Hero with CTA\n- Features grid\n- Pricing table\n- Testimonials\n- FAQ accordion\n- Footer\n\nFully responsive, auto-layout enabled.",
                'price'        => 29.00,
                'license_type' => 'commercial',
                'version'      => '1.5.0',
                'file_type'    => 'fig',
                'file_size'    => 4100000,
                'status'       => 'published',
                'sales_count'  => 203,
                'img_index'    => 2,
                'tags'         => ['landing-page', 'saas', 'figma', 'template'],
            ],
            [
                'seller'       => $bob,
                'category'     => $code,
                'title'        => 'Laravel Starter Kit — SaaS Boilerplate',
                'slug'         => 'laravel-starter-kit-saas-boilerplate',
                'short_description' => 'Production-ready Laravel 11 SaaS boilerplate with billing, auth, and teams.',
                'description'  => "Skip weeks of setup with this battle-tested Laravel SaaS boilerplate.\n\nIncludes:\n- Laravel 11 + Livewire 3\n- Stripe billing integration\n- Teams and multi-tenancy\n- Role-based permissions\n- Admin dashboard\n- Comprehensive test suite\n- Docker setup\n- CI/CD pipeline",
                'price'        => 79.00,
                'license_type' => 'commercial',
                'version'      => '4.1.0',
                'file_type'    => 'zip',
                'file_size'    => 22000000,
                'status'       => 'published',
                'sales_count'  => 67,
                'img_index'    => 3,
                'tags'         => ['laravel', 'saas', 'boilerplate', 'php'],
            ],
            [
                'seller'       => $bob,
                'category'     => $code,
                'title'        => 'Vue 3 Dashboard Template',
                'slug'         => 'vue-3-dashboard-template',
                'short_description' => 'Clean Vue 3 admin dashboard with Tailwind CSS, charts, and dark mode.',
                'description'  => "A production-ready Vue 3 admin dashboard template.\n\nFeatures:\n- Vue 3 Composition API\n- Tailwind CSS 3\n- Chart.js integration\n- Dark/light mode\n- 15 pre-built pages\n- Authentication flow\n- Mobile responsive\n- TypeScript support",
                'price'        => 49.00,
                'license_type' => 'commercial',
                'version'      => '2.3.0',
                'file_type'    => 'zip',
                'file_size'    => 15600000,
                'status'       => 'published',
                'sales_count'  => 134,
                'img_index'    => 4,
                'tags'         => ['vue', 'dashboard', 'tailwind', 'admin'],
            ],
            [
                'seller'       => $bob,
                'category'     => $code,
                'title'        => 'Node.js REST API Starter',
                'slug'         => 'nodejs-rest-api-starter',
                'short_description' => 'Express.js REST API with JWT auth, rate limiting, and Swagger docs.',
                'description'  => "A solid foundation for any Node.js REST API project.\n\nIncludes:\n- Express.js 4\n- JWT authentication\n- Rate limiting\n- Input validation (Zod)\n- Swagger/OpenAPI docs\n- PostgreSQL with Prisma\n- Jest test suite\n- Docker + docker-compose",
                'price'        => 39.00,
                'license_type' => 'commercial',
                'version'      => '1.8.0',
                'file_type'    => 'zip',
                'file_size'    => 9800000,
                'status'       => 'published',
                'sales_count'  => 91,
                'img_index'    => 5,
                'tags'         => ['nodejs', 'express', 'rest-api', 'jwt'],
            ],
            [
                'seller'       => $bob,
                'category'     => $code,
                'title'        => 'React Component Library',
                'slug'         => 'react-component-library',
                'short_description' => '80+ accessible React components built with Radix UI and Tailwind.',
                'description'  => "A comprehensive React component library for building accessible UIs.\n\nIncludes:\n- 80+ components\n- Radix UI primitives\n- Tailwind CSS styling\n- Storybook documentation\n- TypeScript types\n- Dark mode support\n- WCAG 2.1 compliant",
                'price'        => 59.00,
                'license_type' => 'commercial',
                'version'      => '3.0.0',
                'file_type'    => 'zip',
                'file_size'    => 11200000,
                'status'       => 'published',
                'sales_count'  => 178,
                'img_index'    => 6,
                'tags'         => ['react', 'components', 'tailwind', 'typescript'],
            ],
            [
                'seller'       => $carol,
                'category'     => $edu,
                'title'        => 'Complete Web Development Roadmap 2024',
                'slug'         => 'complete-web-development-roadmap-2024',
                'short_description' => 'A 200-page guide covering everything from HTML to deployment.',
                'description'  => "The most comprehensive web development roadmap available.\n\nCovers:\n- HTML, CSS, JavaScript fundamentals\n- React, Vue, and Angular comparison\n- Backend with Node.js and Laravel\n- Databases: SQL and NoSQL\n- DevOps and CI/CD\n- Career guidance and portfolio tips\n\n200 pages, 50+ diagrams, updated for 2024.",
                'price'        => 24.00,
                'license_type' => 'personal',
                'version'      => '2024.1',
                'file_type'    => 'pdf',
                'file_size'    => 6400000,
                'status'       => 'published',
                'sales_count'  => 312,
                'img_index'    => 7,
                'tags'         => ['web-development', 'ebook', 'guide', 'career'],
            ],
            [
                'seller'       => $carol,
                'category'     => $edu,
                'title'        => 'Tailwind CSS Mastery — Video Course',
                'slug'         => 'tailwind-css-mastery-video-course',
                'short_description' => '4 hours of video content teaching Tailwind CSS from beginner to advanced.',
                'description'  => "Master Tailwind CSS with this comprehensive video course.\n\nWhat you will learn:\n- Utility-first CSS concepts\n- Responsive design\n- Custom design systems\n- Component patterns\n- Plugin development\n- Real-world project builds\n\n4 hours of HD video, downloadable assets, and lifetime access.",
                'price'        => 34.00,
                'license_type' => 'personal',
                'version'      => '1.0.0',
                'file_type'    => 'zip',
                'file_size'    => 2800000000,
                'status'       => 'published',
                'sales_count'  => 156,
                'img_index'    => 8,
                'tags'         => ['tailwind', 'css', 'course', 'video'],
            ],
            [
                'seller'       => $carol,
                'category'     => $edu,
                'title'        => 'Freelance Dev Business Kit',
                'slug'         => 'freelance-dev-business-kit',
                'short_description' => 'Templates, contracts, and guides for running a freelance development business.',
                'description'  => "Everything you need to run a successful freelance development business.\n\nIncludes:\n- Client contract template (legally reviewed)\n- Project proposal template\n- Invoice template\n- Onboarding checklist\n- Pricing guide\n- 60-page freelancing ebook\n- Cold email scripts\n- Rate calculator spreadsheet",
                'price'        => 29.00,
                'license_type' => 'personal',
                'version'      => '1.2.0',
                'file_type'    => 'zip',
                'file_size'    => 3200000,
                'status'       => 'published',
                'sales_count'  => 245,
                'img_index'    => 9,
                'tags'         => ['freelance', 'business', 'templates', 'contract'],
            ],
            [
                'seller'       => $bob,
                'category'     => $code,
                'title'        => 'Next.js E-Commerce Starter (Draft)',
                'slug'         => 'nextjs-ecommerce-starter-draft',
                'short_description' => 'Full-stack Next.js e-commerce template — coming soon.',
                'description'  => 'A Next.js 14 e-commerce starter with Stripe, Prisma, and Tailwind CSS.',
                'price'        => 69.00,
                'license_type' => 'commercial',
                'version'      => '0.9.0',
                'file_type'    => 'zip',
                'file_size'    => 14000000,
                'status'       => 'draft',
                'sales_count'  => 0,
                'img_index'    => 10,
                'tags'         => ['nextjs', 'ecommerce', 'stripe'],
            ],
            [
                'seller'       => $alice,
                'category'     => $tpl,
                'title'        => 'Resume & CV Template Bundle',
                'slug'         => 'resume-cv-template-bundle',
                'short_description' => '10 ATS-friendly resume templates in Word and PDF formats.',
                'description'  => "Stand out from the crowd with professionally designed resume templates.\n\nIncludes:\n- 10 unique resume designs\n- Word DOCX and PDF formats\n- Cover letter templates\n- ATS-optimised layouts\n- Easy to customise\n- Instructions included",
                'price'        => 15.00,
                'license_type' => 'personal',
                'version'      => '1.0.0',
                'file_type'    => 'zip',
                'file_size'    => 5600000,
                'status'       => 'published',
                'sales_count'  => 421,
                'img_index'    => 11,
                'tags'         => ['resume', 'cv', 'template', 'word'],
            ],
        ];

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'seller_id'           => $data['seller']->id,
                    'category_id'         => $data['category']->id,
                    'title'               => $data['title'],
                    'slug'                => $data['slug'],
                    'short_description'   => $data['short_description'],
                    'description'         => $data['description'],
                    'price'               => $data['price'],
                    'license_type'        => $data['license_type'],
                    'version'             => $data['version'],
                    'file_type'           => $data['file_type'],
                    'file_size'           => $data['file_size'],
                    'file_cloudinary_id'  => 'seeded/placeholder',
                    'file_name'           => Str::slug($data['title']) . '.' . $data['file_type'],
                    'status'              => $data['status'],
                    'sales_count'         => $data['sales_count'],
                ]
            );

            // Tags
            if (!empty($data['tags'])) {
                $tagIds = collect($data['tags'])->map(
                    fn($tag) =>
                    \App\Models\Tag::firstOrCreate(
                        ['slug' => Str::slug($tag)],
                        ['name' => $tag]
                    )->id
                )->toArray();

                $product->tags()->sync($tagIds);
            }

            // Preview image
            if ($product->images()->count() === 0) {
                ProductImage::create([
                    'product_id'    => $product->id,
                    'cloudinary_id' => 'seeded/placeholder-' . $data['img_index'],
                    'url'           => $placeholders[$data['img_index']],
                    'sort_order'    => 0,
                ]);
            }
        }
    }
}
