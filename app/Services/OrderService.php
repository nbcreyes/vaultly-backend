<?php

namespace App\Services;

use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OrderService
 *
 * Handles order creation and all post-payment processing.
 *
 * Responsibilities:
 *   - Create a pending order before PayPal checkout
 *   - Confirm the order after successful payment capture
 *   - Split commission between platform and seller
 *   - Credit seller available balance
 *   - Write transaction ledger entries
 *   - Generate the initial download token
 */
class OrderService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}
    /**
     * Create a pending order before sending the buyer to PayPal.
     *
     * The order is created in pending status so we have an internal
     * reference before the buyer interacts with PayPal. If the buyer
     * abandons the checkout the order stays pending and is never fulfilled.
     *
     * @param  int   $buyerId
     * @param  int[] $productIds
     * @return array{order: Order, items: array, total: float}
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function createPendingOrder(int $buyerId, array $productIds): array
    {
        // Load and validate all products
        $products = Product::whereIn('id', $productIds)
            ->where('status', 'published')
            ->get();

        if ($products->count() !== count($productIds)) {
            throw new \InvalidArgumentException(
                'One or more selected products are unavailable or no longer published.'
            );
        }

        // Check the buyer has not already purchased any of these products
        foreach ($products as $product) {
            $alreadyPurchased = OrderItem::whereHas(
                'order',
                fn($q) => $q->where('buyer_id', $buyerId)->where('status', 'completed')
            )
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->exists();

            if ($alreadyPurchased) {
                throw new \InvalidArgumentException(
                    "You have already purchased \"{$product->title}\"."
                );
            }
        }

        // Get the commission rate from platform settings
        $commissionRate = (float) PlatformSetting::get('commission_rate', 10);

        $subtotal = $products->sum('price');
        $total    = $subtotal;

        // Generate a unique human-readable order number
        $orderNumber = $this->generateOrderNumber();

        $order = DB::transaction(function () use ($buyerId, $products, $commissionRate, $subtotal, $total, $orderNumber) {
            $order = Order::create([
                'buyer_id'     => $buyerId,
                'order_number' => $orderNumber,
                'subtotal'     => $subtotal,
                'total'        => $total,
                'status'       => 'pending',
            ]);

            foreach ($products as $product) {
                $platformFee    = round($product->price * ($commissionRate / 100), 2);
                $sellerEarnings = round($product->price - $platformFee, 2);

                OrderItem::create([
                    'order_id'        => $order->id,
                    'product_id'      => $product->id,
                    'seller_id'       => $product->seller_id,
                    'price'           => $product->price,
                    'platform_fee'    => $platformFee,
                    'seller_earnings' => $sellerEarnings,
                    'status'          => 'active',
                ]);
            }

            return $order;
        });

        // Build the item list for PayPal
        $paypalItems = $products->map(fn($p) => [
            'name'        => $p->title,
            'unit_amount' => $p->price,
            'quantity'    => 1,
        ])->toArray();

        return [
            'order'       => $order->load('items'),
            'items'       => $paypalItems,
            'total'       => $total,
        ];
    }

    /**
     * Confirm a pending order after successful PayPal capture.
     *
     * This is called after we receive confirmation from PayPal
     * that the payment was successfully captured.
     *
     * Steps:
     *   1. Mark order as completed
     *   2. Store PayPal capture ID
     *   3. Credit seller balances
     *   4. Write transaction ledger entries
     *   5. Increment product sales counts
     *   6. Generate download tokens for each item
     *
     * @param  Order  $order
     * @param  string $paypalOrderId
     * @param  string $paypalCaptureId
     * @return Order
     */
    public function confirmOrder(Order $order, string $paypalOrderId, string $paypalCaptureId): Order
    {
        DB::transaction(function () use ($order, $paypalOrderId, $paypalCaptureId) {
            // Mark the order as completed
            $order->update([
                'status'            => 'completed',
                'paypal_order_id'   => $paypalOrderId,
                'paypal_capture_id' => $paypalCaptureId,
                'paid_at'           => now(),
            ]);

            $order->load('items.product');

            foreach ($order->items as $item) {
                // Credit the seller's available balance
                SellerProfile::where('user_id', $item->seller_id)
                    ->increment('available_balance', $item->seller_earnings);

                SellerProfile::where('user_id', $item->seller_id)
                    ->increment('total_earned', $item->seller_earnings);

                SellerProfile::where('user_id', $item->seller_id)
                    ->increment('total_sales', 1);

                // Increment the product's sales count
                $item->product->increment('sales_count');

                // Write transaction ledger entries
                // Entry 1: buyer sale record
                Transaction::create([
                    'user_id'               => $order->buyer_id,
                    'order_item_id'         => $item->id,
                    'type'                  => 'sale',
                    'amount'                => -$item->price,
                    'description'           => "Purchase: {$item->product->title}",
                    'paypal_transaction_id' => $paypalCaptureId,
                ]);

                // Entry 2: platform commission record
                Transaction::create([
                    'user_id'               => $order->buyer_id,
                    'order_item_id'         => $item->id,
                    'type'                  => 'commission',
                    'amount'                => $item->platform_fee,
                    'description'           => "Platform fee: {$item->product->title}",
                    'paypal_transaction_id' => $paypalCaptureId,
                ]);

                // Entry 3: seller credit record
                Transaction::create([
                    'user_id'               => $item->seller_id,
                    'order_item_id'         => $item->id,
                    'type'                  => 'seller_credit',
                    'amount'                => $item->seller_earnings,
                    'description'           => "Sale: {$item->product->title}",
                    'paypal_transaction_id' => $paypalCaptureId,
                ]);

                // Generate initial download token for this item
                $this->generateDownloadToken($item, $order->buyer_id);
                // Notify buyer
                $this->notifications->orderConfirmed(
                    $order->buyer_id,
                    $order->order_number,
                    $order->id
                );

                // Notify each seller
                foreach ($order->items as $item) {
                    $this->notifications->newSale(
                        $item->seller_id,
                        $item->product->title,
                        $item->product_id,
                        $item->seller_earnings
                    );
                }
            }
        });

        return $order->fresh(['items.product', 'items.downloads']);
    }

    /**
     * Generate a secure expiring download token for an order item.
     *
     * Called after order confirmation and when the buyer requests
     * a new link from their purchase history.
     *
     * Previous tokens for the same order item are expired (not deleted)
     * so the download history remains intact.
     *
     * @param  OrderItem $item
     * @param  int       $buyerId
     * @return Download
     */
    public function generateDownloadToken(OrderItem $item, int $buyerId): Download
    {
        $expiryHours = (int) PlatformSetting::get('download_expiry_hours', 48);

        // Expire all previous active tokens for this item
        Download::where('order_item_id', $item->id)
            ->where('buyer_id', $buyerId)
            ->where('is_revoked', false)
            ->whereNull('downloaded_at')
            ->update(['expires_at' => now()]);

        return Download::create([
            'order_item_id' => $item->id,
            'buyer_id'      => $buyerId,
            'product_id'    => $item->product_id,
            'token'         => Str::random(64),
            'expires_at'    => now()->addHours($expiryHours),
            'is_revoked'    => false,
        ]);
    }

    /**
     * Generate a unique human-readable order number.
     * Format: VLT-YYYYMMDD-XXXXXXXX
     * Example: VLT-20260306-A3F9B2C1
     *
     * @return string
     */
    private function generateOrderNumber(): string
    {
        do {
            $number = 'VLT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
