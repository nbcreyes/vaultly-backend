<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SubmitApplicationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SellerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SellerApplicationController
 *
 * Handles seller application submission and status checks
 * from the applicant's perspective.
 *
 * Admin-side approval and rejection lives in AdminSellerApplicationController.
 */
class SellerApplicationController extends Controller
{
    /**
     * Submit a new seller application.
     *
     * Only buyers who have not yet submitted an application may apply.
     * Approved sellers cannot re-apply.
     *
     * POST /api/v1/seller/application
     */
    public function store(SubmitApplicationRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only buyers can apply
        if (!$user->isBuyer()) {
            return ApiResponse::forbidden('Only buyer accounts can submit a seller application.');
        }

        // Check for an existing application
        $existing = SellerApplication::where('user_id', $user->id)->first();

        if ($existing) {
            if ($existing->isPending()) {
                return ApiResponse::error(
                    'You already have a pending application under review.',
                    409,
                );
            }

            if ($existing->isApproved()) {
                return ApiResponse::error(
                    'Your application has already been approved.',
                    409,
                );
            }

            // If previously rejected, delete it and allow reapplication
            if ($existing->isRejected()) {
                $existing->delete();
            }
        }

        $application = SellerApplication::create([
            'user_id'           => $user->id,
            'full_name'         => $request->full_name,
            'store_name'        => $request->store_name,
            'store_description' => $request->store_description,
            'category_focus'    => $request->category_focus,
            'paypal_email'      => $request->paypal_email,
            'status'            => 'pending',
        ]);

        return ApiResponse::created([
            'application' => $this->formatApplication($application),
        ], 'Your application has been submitted and is under review. We will notify you by email once a decision has been made.');
    }

    /**
     * Get the current user's application status.
     *
     * GET /api/v1/seller/application
     */
    public function show(Request $request): JsonResponse
    {
        $application = SellerApplication::where('user_id', $request->user()->id)->first();

        if (!$application) {
            return ApiResponse::notFound('No seller application found.');
        }

        return ApiResponse::success([
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Format an application for API output.
     *
     * @param  SellerApplication $application
     * @return array<string, mixed>
     */
    private function formatApplication(SellerApplication $application): array
    {
        return [
            'id'               => $application->id,
            'full_name'        => $application->full_name,
            'store_name'       => $application->store_name,
            'store_description'=> $application->store_description,
            'category_focus'   => $application->category_focus,
            'paypal_email'     => $application->paypal_email,
            'status'           => $application->status,
            'rejection_reason' => $application->rejection_reason,
            'submitted_at'     => $application->created_at,
            'reviewed_at'      => $application->reviewed_at,
        ];
    }
}