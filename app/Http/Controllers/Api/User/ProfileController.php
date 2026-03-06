<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Http\Responses\ApiResponse;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * ProfileController
 *
 * Handles user profile management for all authenticated users.
 * Works for buyers, sellers, and admins.
 *
 * Endpoints:
 *   PATCH  /api/v1/user/profile          - update display name
 *   POST   /api/v1/user/avatar           - upload avatar
 *   DELETE /api/v1/user/avatar           - remove avatar
 *   POST   /api/v1/user/change-password  - change password
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * Update the authenticated user's display name.
     *
     * PATCH /api/v1/user/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->validated());

        return ApiResponse::success([
            'user' => $this->formatUser($user->fresh()),
        ], 'Profile updated successfully.');
    }

    /**
     * Upload or replace the user's avatar.
     * Old avatar is deleted from Cloudinary before uploading the new one.
     *
     * POST /api/v1/user/avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        // Delete the existing avatar from Cloudinary if one exists
        if ($user->avatar_url) {
            $this->cloudinary->delete("vaultly/avatars/user_{$user->id}");
        }

        $result = $this->cloudinary->uploadAvatar(
            $request->file('avatar'),
            $user->id
        );

        $user->update(['avatar_url' => $result['url']]);

        return ApiResponse::success([
            'avatar_url' => $result['url'],
        ], 'Avatar uploaded successfully.');
    }

    /**
     * Remove the user's avatar.
     *
     * DELETE /api/v1/user/avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->avatar_url) {
            return ApiResponse::error('No avatar is set on your account.', 404);
        }

        $this->cloudinary->delete("vaultly/avatars/user_{$user->id}");

        $user->update(['avatar_url' => null]);

        return ApiResponse::success(null, 'Avatar removed successfully.');
    }

    /**
     * Change the user's password.
     * Current password must match before the new password is set.
     * All existing Sanctum tokens are revoked after the change
     * to force re-authentication on all devices.
     *
     * POST /api/v1/user/change-password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify the current password
        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::validationError([
                'current_password' => ['The current password you entered is incorrect.'],
            ]);
        }

        // Prevent reuse of the same password
        if (Hash::check($request->new_password, $user->password)) {
            return ApiResponse::validationError([
                'new_password' => ['Your new password must be different from your current password.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Revoke all tokens except the current one to
        // force re-login on all other devices
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $user->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();

        return ApiResponse::success(null, 'Password changed successfully. All other sessions have been logged out.');
    }

    /**
     * Format a user for API output.
     *
     * @param  \App\Models\User $user
     * @return array<string, mixed>
     */
    private function formatUser(\App\Models\User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'role'           => $user->role,
            'status'         => $user->status,
            'avatar_url'     => $user->avatar_url,
            'email_verified' => !is_null($user->email_verified_at),
            'created_at'     => $user->created_at,
        ];
    }
}