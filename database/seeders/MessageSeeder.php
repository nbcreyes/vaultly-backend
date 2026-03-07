<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $david = User::where('email', 'david@vaultly.com')->first();
        $bob   = User::where('email', 'bob@vaultly.com')->first();

        // Find David's order that contains Bob's product
        $order = Order::where('buyer_id', $david->id)
            ->whereHas('items', fn($q) => $q->where('seller_id', $bob->id))
            ->first();

        if (!$order) return;

        $thread = [
            [
                'sender'    => $david,
                'recipient' => $bob,
                'body'      => 'Hi Bob, I just purchased the Laravel Starter Kit. Quick question — does it support multi-database tenancy?',
                'read_at'   => now()->subMinutes(60),
            ],
            [
                'sender'    => $bob,
                'recipient' => $david,
                'body'      => 'Hey David! Great question. Yes, it supports both single and multi-database tenancy. Check the /docs/tenancy.md file in the ZIP — it walks through both setups.',
                'read_at'   => now()->subMinutes(45),
            ],
            [
                'sender'    => $david,
                'recipient' => $bob,
                'body'      => 'Perfect, that is exactly what I needed. One more thing — is Cashier already configured for the Stripe integration?',
                'read_at'   => now()->subMinutes(30),
            ],
            [
                'sender'    => $bob,
                'recipient' => $david,
                'body'      => 'Yes, Laravel Cashier is fully configured. You just need to add your Stripe keys to the .env file and run php artisan cashier:install. Let me know if you hit any issues!',
                'read_at'   => now()->subMinutes(15),
            ],
            [
                'sender'    => $david,
                'recipient' => $bob,
                'body'      => 'Amazing support, thank you! Will leave a review.',
                'read_at'   => null,
            ],
        ];

        foreach ($thread as $msg) {
            Message::create([
                'order_id'     => $order->id,
                'sender_id'    => $msg['sender']->id,
                'recipient_id' => $msg['recipient']->id,
                'body'         => $msg['body'],
                'read_at'      => $msg['read_at'],
            ]);
        }
    }
}