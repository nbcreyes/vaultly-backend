<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Download;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $david = User::where('email', 'david@vaultly.com')->first();
        $emma  = User::where('email', 'emma@vaultly.com')->first();
        $frank = User::where('email', 'frank@vaultly.com')->first();

        $purchases = [
            ['buyer' => $david, 'product_slug' => 'nova-ui-kit-200-components'],
            ['buyer' => $david, 'product_slug' => 'laravel-starter-kit-saas-boilerplate'],
            ['buyer' => $david, 'product_slug' => 'complete-web-development-roadmap-2024'],
            ['buyer' => $emma,  'product_slug' => 'vue-3-dashboard-template'],
            ['buyer' => $emma,  'product_slug' => 'minimal-icon-pack-500-svg'],
            ['buyer' => $frank, 'product_slug' => 'react-component-library'],
            ['buyer' => $frank, 'product_slug' => 'freelance-dev-business-kit'],
        ];

        foreach ($purchases as $purchase) {
            $buyer   = $purchase['buyer'];
            $product = Product::where('slug', $purchase['product_slug'])->first();
            if (!$product) continue;

            // Skip if already purchased
            $exists = OrderItem::whereHas('order', fn($q) => $q->where('buyer_id', $buyer->id))
                ->where('product_id', $product->id)
                ->exists();
            if ($exists) continue;

            $platformFee    = round($product->price * 0.10, 2);
            $sellerEarnings = round($product->price - $platformFee, 2);
            $paidAt         = now()->subDays(rand(1, 30));

            $order = Order::create([
                'buyer_id'          => $buyer->id,
                'order_number'      => 'ORD-' . strtoupper(Str::random(8)),
                'subtotal'          => $product->price,
                'total'             => $product->price,
                'status'            => 'completed',
                'paypal_order_id'   => 'SEEDED-' . strtoupper(Str::random(10)),
                'paypal_capture_id' => 'CAP-' . strtoupper(Str::random(12)),
                'paid_at'           => $paidAt,
            ]);

            $item = OrderItem::create([
                'order_id'        => $order->id,
                'product_id'      => $product->id,
                'seller_id'       => $product->seller_id,
                'price'           => $product->price,
                'platform_fee'    => $platformFee,
                'seller_earnings' => $sellerEarnings,
                'status'          => 'active',
            ]);

            // Create download token
            Download::create([
                'order_item_id' => $item->id,
                'buyer_id'      => $buyer->id,
                'product_id'    => $product->id,
                'token'         => Str::random(64),
                'expires_at'    => now()->addDays(7),
                'is_revoked'    => false,
            ]);

            // Update seller balance
            $sellerProfile = $product->seller->sellerProfile;
            if ($sellerProfile) {
                $sellerProfile->increment('total_earned',      $sellerEarnings);
                $sellerProfile->increment('available_balance', $sellerEarnings);
                $sellerProfile->increment('total_sales',       1);
            }
        }
    }
}