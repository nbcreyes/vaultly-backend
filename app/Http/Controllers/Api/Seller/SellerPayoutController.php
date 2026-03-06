<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\RequestPayoutRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SellerPayoutController
 *
 * Handles payout requests from approved sellers.
 *
 * Endpoints:
 *   GET  /api/v1/seller/payouts         - list own payout history
 *   POST /api/v1/seller/payouts         - request a new payout
 */
class SellerPayoutController extends Controller
{
    /**
     * List the seller's payout history.
     *
     * GET /api/v1/seller/payouts
     */
    public function index(Request $request): JsonResponse
    {
        $payouts = Payout::where('seller_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($payouts, 'Payout history retrieved.');
    }

    /**
     * Request a new payout.
     *
     * The requested amount is immediately deducted from the seller's
     * available balance. If the admin rejects it, the balance is
     * returned. The seller cannot have more than one pending payout
     * at a time.
     *
     * POST /api/v1/seller/payouts
     */
    public function store(RequestPayoutRequest $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Seller profile not found.');
        }

        // Only one pending payout at a time
        $hasPending = Payout::where('seller_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return ApiResponse::error(
                'You already have a pending payout request. Please wait for it to be processed before requesting another.',
                409
            );
        }

        $amount = round((float) $request->amount, 2);

        // Verify the seller has sufficient available balance
        if ($amount > (float) $profile->available_balance) {
            return ApiResponse::error(
                "Insufficient balance. Your available balance is \${$profile->available_balance}.",
                422
            );
        }

        $payout = DB::transaction(function () use ($user, $profile, $amount) {
            // Deduct from available balance immediately
            $profile->decrement('available_balance', $amount);

            // Create the payout record
            $payout = Payout::create([
                'seller_id'    => $user->id,
                'amount'       => $amount,
                'paypal_email' => $profile->paypal_email,
                'status'       => 'pending',
            ]);

            // Write a transaction ledger entry
            Transaction::create([
                'user_id'     => $user->id,
                'type'        => 'payout',
                'amount'      => -$amount,
                'description' => "Payout request #{$payout->id}",
            ]);

            return $payout;
        });

        return ApiResponse::created([
            'payout'            => $payout,
            'available_balance' => (float) $profile->fresh()->available_balance,
        ], 'Payout request submitted. Admin will process it shortly.');
    }
}