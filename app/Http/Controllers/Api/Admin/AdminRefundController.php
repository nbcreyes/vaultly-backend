<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProcessRefundRequest;
use App\Http\Responses\ApiResponse;
use App\Mail\RefundApprovedSellerMail;
use App\Mail\RefundProcessedMail;
use App\Models\Download;
use App\Models\Refund;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * AdminRefundController
 *
 * Admin endpoints for reviewing and processing buyer refund requests.
 *
 * Endpoints:
 *   GET   /api/v1/admin/refunds              - list all refund requests
 *   GET   /api/v1/admin/refunds/{id}         - view single refund
 *   PATCH /api/v1/admin/refunds/{id}/process - approve or reject
 */
class AdminRefundController extends Controller
{
    public function __construct(
        private readonly \App\Services\NotificationService $notifications,
    ) {}
    /**
     * List all refund requests with optional status filter.
     *
     * GET /api/v1/admin/refunds
     */
    public function index(Request $request): JsonResponse
    {
        $query = Refund::with([
            'buyer:id,name,email,avatar_url',
            'seller:id,name,email',
            'orderItem.product:id,title,slug',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $refunds = $query->paginate(20);

        return ApiResponse::paginated($refunds, 'Refunds retrieved.');
    }

    /**
     * Get a single refund request in detail.
     *
     * GET /api/v1/admin/refunds/{id}
     */
    public function show(string $id): JsonResponse
    {
        $refund = Refund::with([
            'buyer:id,name,email,avatar_url',
            'seller:id,name,email',
            'orderItem.product:id,title,slug,price',
            'orderItem.order:id,order_number,paid_at,paypal_capture_id',
            'processor:id,name',
        ])->find($id);

        if (!$refund) {
            return ApiResponse::notFound('Refund request not found.');
        }

        return ApiResponse::success(['refund' => $refund]);
    }

    /**
     * Process a refund request — approve or reject.
     *
     * On approve:
     *   1. Issue PayPal refund via capture ID
     *   2. Mark order item as refunded
     *   3. Deduct seller earnings from seller balance
     *   4. Write transaction ledger entry
     *   5. Revoke all active download tokens
     *   6. Email buyer and seller
     *
     * On reject:
     *   1. Mark refund as rejected
     *   2. Email buyer with reason
     *
     * PATCH /api/v1/admin/refunds/{id}/process
     */
    public function process(ProcessRefundRequest $request, string $id): JsonResponse
    {
        $refund = Refund::with([
            'buyer',
            'seller',
            'orderItem.order',
            'orderItem.product:id,title,seller_id',
        ])->find($id);

        if (!$refund) {
            return ApiResponse::notFound('Refund request not found.');
        }

        if ($refund->status !== 'pending') {
            return ApiResponse::error(
                'This refund has already been processed.',
                409
            );
        }

        if ($request->decision === 'approved') {
            return $this->approveRefund($refund, $request->user(), $request->admin_note);
        }

        return $this->rejectRefund($refund, $request->user(), $request->admin_note);
    }

    /**
     * Approve a refund.
     * Issues PayPal refund, revokes downloads, adjusts seller balance.
     */
    private function approveRefund(Refund $refund, $admin, ?string $note): JsonResponse
    {
        $captureId = $refund->orderItem->order->paypal_capture_id;

        // Issue the PayPal refund first — if this fails we abort
        // and do not touch the database
        try {
            $paypalService = app(\App\Services\PayPalService::class);
            $paypalResult  = $paypalService->refundCapture(
                captureId: $captureId,
                amount: $refund->amount,
                note: 'Refund approved by Vaultly support'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error(
                'PayPal refund failed: ' . $e->getMessage(),
                502
            );
        }

        DB::transaction(function () use ($refund, $admin, $note, $paypalResult) {
            // Mark refund as approved
            $refund->update([
                'status'              => 'approved',
                'admin_note'          => $note,
                'processed_by'        => $admin->id,
                'processed_at'        => now(),
                'paypal_refund_id'    => $paypalResult['id'] ?? null,
            ]);

            // Mark the order item as refunded
            $refund->orderItem->update(['status' => 'refunded']);

            // Deduct seller earnings from their available balance
            $refund->seller->sellerProfile()
                ->decrement('available_balance', $refund->orderItem->seller_earnings);

            $refund->seller->sellerProfile()
                ->decrement('total_earned', $refund->orderItem->seller_earnings);

            $refund->seller->sellerProfile()
                ->decrement('total_sales', 1);

            // Write a refund transaction to the ledger
            Transaction::create([
                'user_id'               => $refund->buyer_id,
                'order_item_id'         => $refund->order_item_id,
                'type'                  => 'refund',
                'amount'                => $refund->amount,
                'description'           => "Refund approved: {$refund->orderItem->product->title}",
                'paypal_transaction_id' => $paypalResult['id'] ?? null,
            ]);

            // Write a deduction to the seller's ledger
            Transaction::create([
                'user_id'       => $refund->seller_id,
                'order_item_id' => $refund->order_item_id,
                'type'          => 'refund',
                'amount'        => -$refund->orderItem->seller_earnings,
                'description'   => "Refund deduction: {$refund->orderItem->product->title}",
            ]);

            // Revoke all download tokens for this order item
            Download::where('order_item_id', $refund->order_item_id)
                ->where('buyer_id', $refund->buyer_id)
                ->update(['is_revoked' => true]);
        });

        // Email the buyer
        Mail::to($refund->buyer->email)->send(new RefundProcessedMail(
            userName: $refund->buyer->name,
            productTitle: $refund->orderItem->product->title,
            amount: $refund->amount,
            status: 'approved',
            adminNote: $note,
            dashboardUrl: config('app.frontend_url') . '/purchases',
        ));

        // Email the seller
        Mail::to($refund->seller->email)->send(new RefundApprovedSellerMail(
            sellerName: $refund->seller->name,
            productTitle: $refund->orderItem->product->title,
            amount: $refund->amount,
            deductedAmount: $refund->orderItem->seller_earnings,
            dashboardUrl: config('app.frontend_url') . '/seller/dashboard',
        ));

        $this->notifications->refundApproved(
            $refund->buyer_id,
            $refund->orderItem->product->title,
            $refund->amount,
            $refund->id
        );

        $this->notifications->refundDeducted(
            $refund->seller_id,
            $refund->orderItem->product->title,
            $refund->orderItem->seller_earnings,
            $refund->id
        );

        return ApiResponse::success([
            'refund' => $refund->fresh(['buyer', 'seller', 'orderItem.product']),
        ], 'Refund approved. PayPal refund issued and downloads revoked.');
    }

    /**
     * Reject a refund request.
     */
    private function rejectRefund(Refund $refund, $admin, ?string $note): JsonResponse
    {
        $refund->update([
            'status'       => 'rejected',
            'admin_note'   => $note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        Mail::to($refund->buyer->email)->send(new RefundProcessedMail(
            userName: $refund->buyer->name,
            productTitle: $refund->orderItem->product->title,
            amount: $refund->amount,
            status: 'rejected',
            adminNote: $note,
            dashboardUrl: config('app.frontend_url') . '/purchases',
        ));
        
        $this->notifications->refundRejected(
            $refund->buyer_id,
            $refund->orderItem->product->title,
            $refund->id
        );

        return ApiResponse::success([
            'refund' => $refund->fresh(['buyer', 'seller', 'orderItem.product']),
        ], 'Refund rejected. Buyer has been notified.');
    }
}
