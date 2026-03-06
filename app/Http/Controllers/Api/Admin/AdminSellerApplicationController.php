<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewApplicationRequest;
use App\Http\Responses\ApiResponse;
use App\Mail\SellerApplicationApprovedMail;
use App\Mail\SellerApplicationRejectedMail;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * AdminSellerApplicationController
 *
 * Admin endpoints for reviewing seller applications.
 * Approval creates a seller profile and upgrades the user role.
 * Both decisions send email notifications to the applicant.
 */
class AdminSellerApplicationController extends Controller
{
    /**
     * List all seller applications with optional status filter.
     *
     * GET /api/v1/admin/seller-applications
     *
     * Query parameters:
     *   status - pending|approved|rejected (optional)
     */
    public function index(Request $request): JsonResponse
    {
        $query = SellerApplication::with(['user:id,name,email,avatar_url,created_at'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->paginate(20);

        return ApiResponse::paginated($applications, 'Seller applications retrieved.');
    }

    /**
     * Get a single seller application in detail.
     *
     * GET /api/v1/admin/seller-applications/{id}
     */
    public function show(string $id): JsonResponse
    {
        $application = SellerApplication::with([
            'user:id,name,email,avatar_url,created_at',
            'reviewer:id,name',
        ])->find($id);

        if (!$application) {
            return ApiResponse::notFound('Seller application not found.');
        }

        return ApiResponse::success(['application' => $application]);
    }

    /**
     * Approve or reject a seller application.
     *
     * PATCH /api/v1/admin/seller-applications/{id}/review
     *
     * On approval:
     *   - Application status set to approved
     *   - User role updated to seller
     *   - SellerProfile row created
     *   - Approval email sent to applicant
     *
     * On rejection:
     *   - Application status set to rejected
     *   - Rejection reason stored
     *   - Rejection email sent to applicant
     */
    public function review(ReviewApplicationRequest $request, string $id): JsonResponse
    {
        $application = SellerApplication::with('user')->find($id);

        if (!$application) {
            return ApiResponse::notFound('Seller application not found.');
        }

        if (!$application->isPending()) {
            return ApiResponse::error(
                'This application has already been reviewed.',
                409,
            );
        }

        $admin = $request->user();

        if ($request->decision === 'approved') {
            return $this->approveApplication($application, $admin);
        }

        return $this->rejectApplication($application, $admin, $request->rejection_reason);
    }

    /**
     * Approve the application.
     * Wrapped in a DB transaction to ensure atomicity.
     *
     * @param  SellerApplication $application
     * @param  \App\Models\User  $admin
     * @return JsonResponse
     */
    private function approveApplication(SellerApplication $application, $admin): JsonResponse
    {
        DB::transaction(function () use ($application, $admin) {
            // Update application record
            $application->update([
                'status'      => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Upgrade user role to seller
            $application->user->update(['role' => 'seller']);

            // Generate a unique store slug from the store name
            $baseSlug = Str::slug($application->store_name);
            $slug     = $baseSlug;
            $counter  = 1;

            while (SellerProfile::where('store_slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Create the seller profile
            SellerProfile::create([
                'user_id'           => $application->user_id,
                'store_name'        => $application->store_name,
                'store_slug'        => $slug,
                'store_description' => $application->store_description,
                'paypal_email'      => $application->paypal_email,
                'available_balance' => 0.00,
                'pending_balance'   => 0.00,
                'total_earned'      => 0.00,
                'total_sales'       => 0,
            ]);
        });

        // Send approval email outside the transaction
        $dashboardUrl = config('app.frontend_url') . '/seller/dashboard';

        Mail::to($application->user->email)->send(
            new SellerApplicationApprovedMail(
                $application->user->name,
                $application->store_name,
                $dashboardUrl,
            )
        );

        return ApiResponse::success(
            ['application' => $application->fresh(['user', 'reviewer'])],
            'Application approved. The seller account and store profile have been created.'
        );
    }

    /**
     * Reject the application.
     *
     * @param  SellerApplication $application
     * @param  \App\Models\User  $admin
     * @param  string            $reason
     * @return JsonResponse
     */
    private function rejectApplication(SellerApplication $application, $admin, string $reason): JsonResponse
    {
        $application->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
        ]);

        $reapplyUrl = config('app.frontend_url') . '/seller/apply';

        Mail::to($application->user->email)->send(
            new SellerApplicationRejectedMail(
                $application->user->name,
                $application->store_name,
                $reason,
                $reapplyUrl,
            )
        );

        return ApiResponse::success(
            ['application' => $application->fresh(['user', 'reviewer'])],
            'Application rejected. The applicant has been notified by email.'
        );
    }
}