<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * PayPalWebhookController
 *
 * Handles incoming PayPal webhook events.
 *
 * PayPal sends webhooks for every significant payment event.
 * We use these for reconciliation — our primary flow uses the
 * capture endpoint directly, so webhooks serve as a safety net
 * to catch any edge cases where the frontend did not complete
 * the capture confirmation call.
 *
 * All webhook handlers return HTTP 200 immediately to acknowledge
 * receipt. PayPal will retry unacknowledged webhooks up to 3 times.
 *
 * Handled events:
 *   PAYMENT.CAPTURE.COMPLETED  - payment successfully captured
 *   PAYMENT.CAPTURE.DENIED     - payment was denied by PayPal
 *   PAYMENT.CAPTURE.REFUNDED   - refund was processed
 *   CHECKOUT.ORDER.APPROVED    - buyer approved but not yet captured (informational)
 */
class PayPalWebhookController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypal,
    ) {}

    /**
     * Handle incoming PayPal webhook.
     *
     * POST /api/v1/payments/webhook
     */
    public function handle(Request $request): Response
    {
        // Verify the webhook signature before processing anything
        $isValid = $this->paypal->verifyWebhookSignature(
            authAlgo:         $request->header('PAYPAL-AUTH-ALGO', ''),
            certUrl:          $request->header('PAYPAL-CERT-URL', ''),
            transmissionId:   $request->header('PAYPAL-TRANSMISSION-ID', ''),
            transmissionSig:  $request->header('PAYPAL-TRANSMISSION-SIG', ''),
            transmissionTime: $request->header('PAYPAL-TRANSMISSION-TIME', ''),
            rawBody:          $request->getContent(),
        );

        if (!$isValid) {
            Log::warning('PayPal webhook received with invalid signature', [
                'ip'      => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            // Return 200 anyway — returning non-200 causes PayPal to retry
            // which would flood our logs with invalid requests
            return response('Signature verification failed.', 200);
        }

        $payload   = $request->json()->all();
        $eventType = $payload['event_type'] ?? null;
        $resource  = $payload['resource'] ?? [];

        Log::info('PayPal webhook received', ['event_type' => $eventType]);

        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource),
            'PAYMENT.CAPTURE.DENIED'    => $this->handleCaptureDenied($resource),
            'PAYMENT.CAPTURE.REFUNDED'  => $this->handleCaptureRefunded($resource),
            default                     => Log::info('PayPal webhook event not handled', ['event_type' => $eventType]),
        };

        return response('OK', 200);
    }

    /**
     * Handle PAYMENT.CAPTURE.COMPLETED
     *
     * This fires when a payment is successfully captured.
     * In our primary flow the CheckoutController already handles this.
     * This handler catches any orders that were approved but where the
     * frontend failed to call our capture endpoint.
     *
     * @param  array<string, mixed> $resource
     * @return void
     */
    private function handleCaptureCompleted(array $resource): void
    {
        $captureId   = $resource['id'] ?? null;
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        if (!$captureId || !$paypalOrderId) {
            Log::warning('PayPal CAPTURE.COMPLETED missing IDs', ['resource' => $resource]);
            return;
        }

        // Find the order by PayPal order ID
        $order = Order::where('paypal_order_id', $paypalOrderId)
            ->where('status', 'pending')
            ->first();

        if (!$order) {
            // Order already confirmed via direct capture — nothing to do
            Log::info('PayPal CAPTURE.COMPLETED: order already confirmed or not found', [
                'paypal_order_id' => $paypalOrderId,
            ]);
            return;
        }

        // Order is still pending — confirm it via webhook
        try {
            app(\App\Services\OrderService::class)->confirmOrder(
                order:           $order,
                paypalOrderId:   $paypalOrderId,
                paypalCaptureId: $captureId,
            );

            Log::info('PayPal webhook confirmed pending order', [
                'order_id'        => $order->id,
                'paypal_order_id' => $paypalOrderId,
            ]);
        } catch (\Throwable $e) {
            Log::error('PayPal webhook order confirmation failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle PAYMENT.CAPTURE.DENIED
     *
     * Payment was denied by PayPal. Log for investigation.
     * The order stays in pending status and is never fulfilled.
     *
     * @param  array<string, mixed> $resource
     * @return void
     */
    private function handleCaptureDenied(array $resource): void
    {
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        Log::warning('PayPal payment capture denied', [
            'paypal_order_id' => $paypalOrderId,
            'resource'        => $resource,
        ]);

        if ($paypalOrderId) {
            Order::where('paypal_order_id', $paypalOrderId)
                ->where('status', 'pending')
                ->update(['status' => 'pending']); // stays pending, buyer can retry
        }
    }

    /**
     * Handle PAYMENT.CAPTURE.REFUNDED
     *
     * PayPal has processed a refund. This is informational — our
     * refund flow in RefundController already handles the database
     * updates. This handler just logs for reconciliation.
     *
     * @param  array<string, mixed> $resource
     * @return void
     */
    private function handleCaptureRefunded(array $resource): void
    {
        Log::info('PayPal refund webhook received', [
            'refund_id' => $resource['id'] ?? null,
            'amount'    => $resource['amount'] ?? null,
        ]);
    }
}