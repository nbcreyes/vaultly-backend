<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Review;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $david = User::where('email', 'david@vaultly.com')->first();
        $emma  = User::where('email', 'emma@vaultly.com')->first();
        $frank = User::where('email', 'frank@vaultly.com')->first();

        $reviews = [
            [
                'buyer'        => $david,
                'product_slug' => 'nova-ui-kit-200-components',
                'rating'       => 5,
                'body'         => 'Absolutely love this UI kit. The components are clean, well-organised, and the auto-layout is a huge time saver. Worth every penny.',
                'seller_reply' => 'Thank you so much David! Really glad it is helping your workflow.',
            ],
            [
                'buyer'        => $david,
                'product_slug' => 'laravel-starter-kit-saas-boilerplate',
                'rating'       => 5,
                'body'         => 'Saved me at least two weeks of setup time. The code quality is excellent and the documentation is clear. Stripe integration worked out of the box.',
                'seller_reply' => null,
            ],
            [
                'buyer'        => $david,
                'product_slug' => 'complete-web-development-roadmap-2024',
                'rating'       => 4,
                'body'         => 'Very comprehensive guide. Covers everything a junior dev needs to know. Would love a section on cloud deployment.',
                'seller_reply' => 'Great suggestion! Cloud deployment section is coming in the next update.',
            ],
            [
                'buyer'        => $emma,
                'product_slug' => 'vue-3-dashboard-template',
                'rating'       => 5,
                'body'         => 'Best Vue dashboard template I have found. The chart integrations are smooth and the dark mode is gorgeous.',
                'seller_reply' => 'Glad you like it Emma! More chart types coming in v2.4.',
            ],
            [
                'buyer'        => $emma,
                'product_slug' => 'minimal-icon-pack-500-svg',
                'rating'       => 4,
                'body'         => 'Great icon pack, very consistent style. Would love more category-specific icons like finance and healthcare.',
                'seller_reply' => null,
            ],
            [
                'buyer'        => $frank,
                'product_slug' => 'react-component-library',
                'rating'       => 5,
                'body'         => 'Incredibly well-built component library. Accessibility is taken seriously and every component has excellent TypeScript types.',
                'seller_reply' => 'Thank you Frank! Accessibility is a core priority for this library.',
            ],
            [
                'buyer'        => $frank,
                'product_slug' => 'freelance-dev-business-kit',
                'rating'       => 5,
                'body'         => 'The contract template alone was worth the price. I have already used it for three client projects. The pricing guide is also gold.',
                'seller_reply' => null,
            ],
        ];

        foreach ($reviews as $data) {
            $product = Product::where('slug', $data['product_slug'])->first();
            if (!$product) continue;

            // Find the order item for this buyer + product
            $orderItem = OrderItem::where('product_id', $product->id)
                ->whereHas('order', fn($q) => $q->where('buyer_id', $data['buyer']->id))
                ->first();
            if (!$orderItem) continue;

            $exists = Review::where('buyer_id', $data['buyer']->id)
                ->where('product_id', $product->id)
                ->exists();
            if ($exists) continue;

            Review::create([
                'buyer_id'           => $data['buyer']->id,
                'product_id'         => $product->id,
                'order_item_id'      => $orderItem->id,
                'rating'             => $data['rating'],
                'body'               => $data['body'],
                'seller_reply'       => $data['seller_reply'],
                'seller_replied_at'  => $data['seller_reply'] ? now()->subDays(rand(1, 5)) : null,
                'is_visible'         => true,
            ]);
        }

        // Update average ratings
        Product::whereHas('reviews')->each(function ($product) {
            $avg   = $product->reviews()->avg('rating');
            $count = $product->reviews()->count();
            $product->update([
                'average_rating' => round($avg, 1),
                'review_count'   => $count,
            ]);
        });
    }
}