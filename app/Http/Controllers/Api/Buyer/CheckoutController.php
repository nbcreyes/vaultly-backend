<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CaptureCheckoutOrderRequest;
use App\Http\Requests\Buyer\CreateCheckoutOrderRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * CheckoutController
 *
 * Handles the two-step PayPal checkout flow.
 *
 * Step 1 — Create:
 *   Buyer submits product IDs.
 *   We create a pending order and a PayPal order.
 *   We return both IDs to the frontend.
 *   The frontend renders the PayPal button using the PayPal JS SDK.
 *
 * Step 2 — Capture:
 *   Buyer approves payment in the PayPal popup.
 *   Frontend sends us the approved PayPal order ID and our internal order ID.
 *   We capture the payment with PayPal.
 *   We confirm the order, split commission, credit seller, generate download.
 *   We return the confirmed order with download tokens.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypal,
        private readonly OrderService $orderService,
    ) {}

    /**
     * Step 1 — Create a PayPal checkout order.
     *
     * POST /api/v1/checkout/create
     */
    public function create(CreateCheckoutOrderRequest $request): JsonResponse
    {
        try {
            $result = $this->orderService->createPendingOrder(
                buyerId:    $request->user()->id,
                productIds: $request->product_ids,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        try {
            $paypalOrder = $this->paypal->createOrder(
                items:          $result['items'],
                orderReference: $result['order']->order_number,
            );
        } catch (\RuntimeException $e) {
            // Clean up the pending order if PayPal fails
            $result['order']->items()->delete();
            $result['order']->delete();

            return ApiResponse::error($e->getMessage(), 502);
        }

        // Store the PayPal order ID on our pending order
        $result['order']->update(['paypal_order_id' => $paypalOrder['id']]);

        return ApiResponse::success([
            'order_id'        => $result['order']->id,
            'order_number'    => $result['order']->order_number,
            'paypal_order_id' => $paypalOrder['id'],
            'total'           => $result['total'],
        ], 'Order created. Complete payment to confirm your purchase.');
    }

    /**
     * Step 2 — Capture payment after buyer approves in PayPal popup.
     *
     * POST /api/v1/checkout/capture
     */
    public function capture(CaptureCheckoutOrderRequest $request): JsonResponse
    {
        $order = Order::where('id', $request->order_id)
            ->where('buyer_id', $request->user()->id)
            ->where('status', 'pending')
            ->with('items')
            ->first();

        if (!$order) {
            return ApiResponse::notFound('Order not found or already processed.');
        }

        // Verify the PayPal order ID matches what we stored
        if ($order->paypal_order_id !== $request->paypal_order_id) {
            return ApiResponse::error('PayPal order ID mismatch.', 422);
        }

        try {
            $captureResult = $this->paypal->captureOrder($request->paypal_order_id);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 502);
        }

        // Verify PayPal reports the capture as completed
        $captureStatus = $captureResult['status'] ?? null;

        if ($captureStatus !== 'COMPLETED') {
            Log::warning('PayPal capture returned non-COMPLETED status', [
                'order_id'      => $order->id,
                'paypal_status' => $captureStatus,
                'response'      => $captureResult,
            ]);

            return ApiResponse::error(
                'Payment was not completed. Please try again or contact support.',
                422
            );
        }

        // Extract the capture ID from the PayPal response
        $captureId = $captureResult['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        if (!$captureId) {
            Log::error('PayPal capture ID missing from response', [
                'order_id' => $order->id,
                'response' => $captureResult,
            ]);

            return ApiResponse::error('Payment confirmation failed. Please contact support.', 500);
        }

        // Confirm the order and process all post-payment steps
        $confirmedOrder = $this->orderService->confirmOrder(
            order:           $order,
            paypalOrderId:   $request->paypal_order_id,
            paypalCaptureId: $captureId,
        );

        // Build the response with order items and their download tokens
        $items = $confirmedOrder->items->map(function ($item) {
            $download = $item->downloads->first();

            return [
                'order_item_id'  => $item->id,
                'product_id'     => $item->product_id,
                'product_title'  => $item->product->title,
                'price'          => $item->price,
                'download_token' => $download?->token,
                'download_expires_at' => $download?->expires_at,
            ];
        });

        return ApiResponse::success([
            'order' => [
                'id'           => $confirmedOrder->id,
                'order_number' => $confirmedOrder->order_number,
                'total'        => $confirmedOrder->total,
                'status'       => $confirmedOrder->status,
                'paid_at'      => $confirmedOrder->paid_at,
                'items'        => $items,
            ],
        ], 'Payment successful. Your downloads are ready.');
    }
}