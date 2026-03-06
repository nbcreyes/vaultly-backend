<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProcessPayoutRequest;
use App\Http\Responses\ApiResponse;
use App\Mail\PayoutProcessedMail;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * AdminPayoutController
 *
 * Admin endpoints for managing seller payout requests.
 *
 * Endpoints:
 *   GET   /api/v1/admin/payouts          - list all payout requests
 *   GET   /api/v1/admin/payouts/{id}     - view single payout
 *   PATCH /api/v1/admin/payouts/{id}/process - mark as paid or rejected
 */
class AdminPayoutController extends Controller
{
    /**
     * List all payout requests with optional status filter.
     *
     * GET /api/v1/admin/payouts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payout::with([
            'seller:id,name,email,avatar_url',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payouts = $query->paginate(20);

        return ApiResponse::paginated($payouts, 'Payouts retrieved.');
    }

    /**
     * Get a single payout request in detail.
     *
     * GET /api/v1/admin/payouts/{id}
     */
    public function show(string $id): JsonResponse
    {
        $payout = Payout::with([
            'seller:id,name,email,avatar_url',
            'processor:id,name',
        ])->find($id);

        if (!$payout) {
            return ApiResponse::notFound('Payout not found.');
        }

        return ApiResponse::success(['payout' => $payout]);
    }

    /**
     * Process a payout request — mark as paid or rejected.
     *
     * On paid:
     *   - Status updated to paid
     *   - PayPal payout ID stored
     *   - Seller notified by email
     *
     * On rejected:
     *   - Status updated to rejected
     *   - Balance returned to seller
     *   - Seller notified by email
     *
     * PATCH /api/v1/admin/payouts/{id}/process
     */
    public function process(ProcessPayoutRequest $request, string $id): JsonResponse
    {
        $payout = Payout::with('seller')->find($id);

        if (!$payout) {
            return ApiResponse::notFound('Payout not found.');
        }

        if (!$payout->isPending()) {
            return ApiResponse::error(
                'This payout has already been processed.',
                409
            );
        }

        $admin = $request->user();

        if ($request->decision === 'paid') {
            return $this->markAsPaid($payout, $admin, $request);
        }

        return $this->rejectPayout($payout, $admin, $request->admin_note);
    }

    /**
     * Mark a payout as paid.
     *
     * @param  Payout  $payout
     * @param  \App\Models\User $admin
     * @param  ProcessPayoutRequest $request
     * @return JsonResponse
     */
    private function markAsPaid(Payout $payout, $admin, ProcessPayoutRequest $request): JsonResponse
    {
        $payout->update([
            'status'           => 'paid',
            'paypal_payout_id' => $request->paypal_payout_id,
            'admin_note'       => $request->admin_note,
            'processed_by'     => $admin->id,
            'processed_at'     => now(),
        ]);

        Mail::to($payout->seller->email)->send(
            new PayoutProcessedMail(
                userName:   $payout->seller->name,
                amount:     $payout->amount,
                status:     'paid',
                adminNote:  $request->admin_note,
                dashboardUrl: config('app.frontend_url') . '/seller/dashboard',
            )
        );

        return ApiResponse::success([
            'payout' => $payout->fresh(['seller', 'processor']),
        ], 'Payout marked as paid. Seller has been notified.');
    }

    /**
     * Reject a payout and return the balance to the seller.
     *
     * @param  Payout       $payout
     * @param  \App\Models\User $admin
     * @param  string|null  $note
     * @return JsonResponse
     */
    private function rejectPayout(Payout $payout, $admin, ?string $note): JsonResponse
    {
        DB::transaction(function () use ($payout, $admin, $note) {
            $payout->update([
                'status'       => 'rejected',
                'admin_note'   => $note,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);

            // Return the balance to the seller
            $payout->seller->sellerProfile()->increment('available_balance', $payout->amount);

            // Write a reversal transaction
            \App\Models\Transaction::create([
                'user_id'     => $payout->seller_id,
                'type'        => 'payout',
                'amount'      => $payout->amount,
                'description' => "Payout request #{$payout->id} rejected — balance returned",
            ]);
        });

        Mail::to($payout->seller->email)->send(
            new PayoutProcessedMail(
                userName:    $payout->seller->name,
                amount:      $payout->amount,
                status:      'rejected',
                adminNote:   $note,
                dashboardUrl: config('app.frontend_url') . '/seller/dashboard',
            )
        );

        return ApiResponse::success([
            'payout' => $payout->fresh(['seller', 'processor']),
        ], 'Payout rejected. Balance has been returned to the seller.');
    }
}