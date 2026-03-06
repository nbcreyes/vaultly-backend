<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\RequestRefundRequest;
use App\Http\Responses\ApiResponse;
use App\Mail\RefundRequestedMail;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * BuyerRefundController
 *
 * Handles refund requests submitted by buyers.
 *
 * Endpoints:
 *   POST /api/v1/buyer/refunds        - submit a refund request
 *   GET  /api/v1/buyer/refunds        - list own refund requests
 */
class BuyerRefundController extends Controller
{
    /**
     * Submit a refund request for a purchased order item.
     *
     * Rules:
     *   - Must be within the refund window (default 72 hours)
     *   - Order item must not already have a pending or approved refund
     *   - Order item must belong to the authenticated buyer
     *   - Refunded items cannot be refunded again
     *
     * POST /api/v1/buyer/refunds
     */
    public function store(RequestRefundRequest $request): JsonResponse
    {
        $buyer = $request->user();

        $refundWindowHours = (int) PlatformSetting::get('refund_window_hours', 72);

        // Find and verify the order item
        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->whereHas(
                'order',
                fn($q) => $q->where('buyer_id', $buyer->id)
                             ->where('status', 'completed')
            )
            ->with(['order', 'product:id,title,price'])
            ->first();

        if (!$orderItem) {
            return ApiResponse::error(
                'Purchase not found or not eligible for a refund.',
                403
            );
        }

        if ($orderItem->status === 'refunded') {
            return ApiResponse::error(
                'This item has already been refunded.',
                409
            );
        }

        // Check refund window
        $paidAt     = $orderItem->order->paid_at;
        $hoursAgo   = $paidAt ? $paidAt->diffInHours(now()) : 999;

        if ($hoursAgo > $refundWindowHours) {
            return ApiResponse::error(
                "Refunds can only be requested within {$refundWindowHours} hours of purchase. "
                . "This purchase was made {$hoursAgo} hours ago.",
                403
            );
        }

        // Check for an existing pending or approved refund request
        $existingRefund = Refund::where('order_item_id', $orderItem->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRefund) {
            return ApiResponse::error(
                'A refund request for this item is already in progress.',
                409
            );
        }

        $refund = Refund::create([
            'order_item_id' => $orderItem->id,
            'buyer_id'      => $buyer->id,
            'seller_id'     => $orderItem->seller_id,
            'amount'        => $orderItem->price,
            'reason'        => $request->reason,
            'status'        => 'pending',
        ]);

        Mail::to($buyer->email)->send(new RefundRequestedMail(
            userName:     $buyer->name,
            productTitle: $orderItem->product->title,
            amount:       $orderItem->price,
            supportUrl:   config('app.frontend_url') . '/support',
        ));

        return ApiResponse::created([
            'refund' => $refund,
        ], 'Refund request submitted. We will review it within 1 to 2 business days.');
    }

    /**
     * List the authenticated buyer's refund requests.
     *
     * GET /api/v1/buyer/refunds
     */
    public function index(Request $request): JsonResponse
    {
        $refunds = Refund::where('buyer_id', $request->user()->id)
            ->with([
                'orderItem.product:id,title,slug',
                'orderItem.product.images' => fn($q) => $q->where('sort_order', 0),
            ])
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($refunds, 'Refund history retrieved.');
    }
}