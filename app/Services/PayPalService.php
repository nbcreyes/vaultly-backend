<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * PayPalService
 *
 * Wraps all PayPal REST API interactions for Vaultly.
 *
 * Responsibilities:
 *   - OAuth token management with automatic refresh and caching
 *   - Create checkout orders
 *   - Capture payments after buyer approval
 *   - Issue full and partial refunds
 *   - Verify incoming webhook signatures
 *
 * PayPal API version: v2
 * All amounts are in USD with two decimal places.
 *
 * Sandbox base URL: https://api-m.sandbox.paypal.com
 * Live base URL:    https://api-m.paypal.com
 */
class PayPalService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;

    public function __construct()
    {
        $mode = config('services.paypal.mode', 'sandbox');

        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $this->clientId = $mode === 'live'
            ? config('services.paypal.live.client_id')
            : config('services.paypal.sandbox.client_id');

        $this->clientSecret = $mode === 'live'
            ? config('services.paypal.live.client_secret')
            : config('services.paypal.sandbox.client_secret');

        $this->webhookId = config('services.paypal.webhook_id');
    }

    /**
     * Create a PayPal checkout order.
     *
     * Called when the buyer clicks the purchase button.
     * Returns a PayPal order ID that the frontend uses to
     * render the PayPal checkout buttons via the JS SDK.
     *
     * @param  array<array{name: string, unit_amount: float, quantity: int}> $items
     * @param  string $orderReference  Our internal order number for reference
     * @return array{id: string, status: string, links: array}
     *
     * @throws \RuntimeException
     */
    public function createOrder(array $items, string $orderReference): array
    {
        $token = $this->getAccessToken();

        // Build the item breakdown for the order
        $itemList    = [];
        $totalAmount = 0;

        foreach ($items as $item) {
            $unitAmount   = number_format((float) $item['unit_amount'], 2, '.', '');
            $totalAmount += (float) $unitAmount * (int) $item['quantity'];

            $itemList[] = [
                'name'        => $item['name'],
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value'         => $unitAmount,
                ],
                'quantity' => (string) $item['quantity'],
            ];
        }

        $totalAmount = number_format($totalAmount, 2, '.', '');

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $orderReference,
                    'amount'       => [
                        'currency_code' => 'USD',
                        'value'         => $totalAmount,
                        'breakdown'     => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value'         => $totalAmount,
                            ],
                        ],
                    ],
                    'items' => $itemList,
                ],
            ],
            'application_context' => [
                'brand_name'          => 'Vaultly',
                'landing_page'        => 'NO_PREFERENCE',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/v2/checkout/orders", $payload);

        if (!$response->successful()) {
            Log::error('PayPal create order failed', [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);

            throw new \RuntimeException('Failed to create PayPal order. Please try again.');
        }

        return $response->json();
    }

    /**
     * Capture a PayPal order after the buyer approves it.
     *
     * Called after the buyer completes the PayPal checkout popup
     * and the frontend receives the approved order ID.
     *
     * @param  string $paypalOrderId  The PayPal order ID from createOrder
     * @return array{id: string, status: string, purchase_units: array}
     *
     * @throws \RuntimeException
     */
    public function captureOrder(string $paypalOrderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture", []);

        if (!$response->successful()) {
            Log::error('PayPal capture order failed', [
                'paypal_order_id' => $paypalOrderId,
                'status'          => $response->status(),
                'response'        => $response->json(),
            ]);

            throw new \RuntimeException('Payment capture failed. Please try again.');
        }

        return $response->json();
    }

    /**
     * Issue a full refund for a captured payment.
     *
     * @param  string $captureId  The PayPal capture ID from the completed order
     * @param  float  $amount     The amount to refund in USD
     * @param  string $note       Reason for the refund shown to the buyer
     * @return array{id: string, status: string}
     *
     * @throws \RuntimeException
     */
    public function refundCapture(string $captureId, float $amount, string $note = 'Refund approved by Vaultly'): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'amount' => [
                'currency_code' => 'USD',
                'value'         => number_format($amount, 2, '.', ''),
            ],
            'note_to_payer' => $note,
        ];

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$this->baseUrl}/v2/payments/captures/{$captureId}/refund", $payload);

        if (!$response->successful()) {
            Log::error('PayPal refund failed', [
                'capture_id' => $captureId,
                'amount'     => $amount,
                'status'     => $response->status(),
                'response'   => $response->json(),
            ]);

            throw new \RuntimeException('Refund processing failed. Please try again.');
        }

        return $response->json();
    }

    /**
     * Verify a PayPal webhook signature.
     *
     * PayPal signs every webhook it sends. We verify the signature
     * to ensure the request genuinely came from PayPal and was not
     * tampered with or forged by a third party.
     *
     * @param  string $authAlgo         From header: PAYPAL-AUTH-ALGO
     * @param  string $certUrl          From header: PAYPAL-CERT-URL
     * @param  string $transmissionId   From header: PAYPAL-TRANSMISSION-ID
     * @param  string $transmissionSig  From header: PAYPAL-TRANSMISSION-SIG
     * @param  string $transmissionTime From header: PAYPAL-TRANSMISSION-TIME
     * @param  string $rawBody          The raw request body string
     * @return bool
     */
    public function verifyWebhookSignature(
        string $authAlgo,
        string $certUrl,
        string $transmissionId,
        string $transmissionSig,
        string $transmissionTime,
        string $rawBody,
    ): bool {
        try {
            $token = $this->getAccessToken();

            $payload = [
                'auth_algo'         => $authAlgo,
                'cert_url'          => $certUrl,
                'transmission_id'   => $transmissionId,
                'transmission_sig'  => $transmissionSig,
                'transmission_time' => $transmissionTime,
                'webhook_id'        => $this->webhookId,
                'webhook_event'     => json_decode($rawBody, true),
            ];

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", $payload);

            if (!$response->successful()) {
                Log::warning('PayPal webhook verification request failed', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);

                return false;
            }

            $verificationStatus = $response->json('verification_status');

            return $verificationStatus === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get a cached OAuth access token.
     *
     * PayPal OAuth tokens are valid for ~9 hours.
     * We cache them for 8 hours to avoid requesting a new one
     * on every API call. The cache key is environment-specific
     * so sandbox and live tokens never collide.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_access_token_' . config('services.paypal.mode', 'sandbox');

        return Cache::remember($cacheKey, now()->addHours(8), function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                Log::error('PayPal OAuth token request failed', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);

                throw new \RuntimeException('Could not authenticate with PayPal. Please check your credentials.');
            }

            return $response->json('access_token');
        });
    }
}