<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateStoreProfileRequest;
use App\Http\Requests\Seller\UploadStoreBannerRequest;
use App\Http\Requests\Seller\UploadStoreLogoRequest;
use App\Http\Responses\ApiResponse;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SellerStoreController
 *
 * Handles all store profile management for approved sellers.
 *
 * Endpoints:
 *   GET    /api/v1/seller/store           - get own store profile
 *   PATCH  /api/v1/seller/store           - update store text fields
 *   POST   /api/v1/seller/store/logo      - upload store logo
 *   DELETE /api/v1/seller/store/logo      - remove store logo
 *   POST   /api/v1/seller/store/banner    - upload store banner
 *   DELETE /api/v1/seller/store/banner    - remove store banner
 */
class SellerStoreController extends Controller
{
    public function __construct(
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * Get the authenticated seller's store profile.
     *
     * GET /api/v1/seller/store
     */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Store profile not found.');
        }

        return ApiResponse::success([
            'store' => $this->formatProfile($profile),
        ]);
    }

    /**
     * Update store text fields and social links.
     * All fields are optional — only provided fields are updated.
     *
     * PATCH /api/v1/seller/store
     */
    public function update(UpdateStoreProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Store profile not found.');
        }

        $updatable = [
            'store_name',
            'store_description',
            'paypal_email',
            'website_url',
            'twitter_url',
            'github_url',
            'dribbble_url',
            'linkedin_url',
        ];

        $data = $request->only($updatable);

        // If the store name is being changed, regenerate the slug
        if (isset($data['store_name']) && $data['store_name'] !== $profile->store_name) {
            $data['store_slug'] = $this->generateUniqueSlug(
                $data['store_name'],
                $profile->id
            );
        }

        $profile->update($data);

        return ApiResponse::success([
            'store' => $this->formatProfile($profile->fresh()),
        ], 'Store profile updated successfully.');
    }

    /**
     * Upload or replace the store logo.
     * The old logo is deleted from Cloudinary before uploading the new one.
     *
     * POST /api/v1/seller/store/logo
     */
    public function uploadLogo(UploadStoreLogoRequest $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Store profile not found.');
        }

        // Delete the existing logo from Cloudinary if one exists
        if ($profile->logo_url) {
            $existingPublicId = "vaultly/stores/logos/{$profile->store_slug}";
            $this->cloudinary->delete($existingPublicId);
        }

        $result = $this->cloudinary->uploadStoreLogo(
            $request->file('logo'),
            $profile->store_slug
        );

        $profile->update(['logo_url' => $result['url']]);

        return ApiResponse::success([
            'logo_url' => $result['url'],
        ], 'Store logo uploaded successfully.');
    }

    /**
     * Remove the store logo.
     *
     * DELETE /api/v1/seller/store/logo
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Store profile not found.');
        }

        if (!$profile->logo_url) {
            return ApiResponse::error('No logo is set for this store.', 404);
        }

        $this->cloudinary->delete("vaultly/stores/logos/{$profile->store_slug}");

        $profile->update(['logo_url' => null]);

        return ApiResponse::success(null, 'Store logo removed successfully.');
    }

    /**
     * Upload or replace the store banner.
     * The old banner is deleted from Cloudinary before uploading the new one.
     *
     * POST /api/v1/seller/store/banner
     */
    public function uploadBanner(UploadStoreBannerRequest $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Store profile not found.');
        }

        // Delete the existing banner from Cloudinary if one exists
        if ($profile->banner_url) {
            $existingPublicId = "vaultly/stores/banners/{$profile->store_slug}";
            $this->cloudinary->delete($existingPublicId);
        }

        $result = $this->cloudinary->uploadStoreBanner(
            $request->file('banner'),
            $profile->store_slug
        );

        $profile->update(['banner_url' => $result['url']]);

        return ApiResponse::success([
            'banner_url' => $result['url'],
        ], 'Store banner uploaded successfully.');
    }

    /**
     * Remove the store banner.
     *
     * DELETE /api/v1/seller/store/banner
     */
    public function deleteBanner(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Store profile not found.');
        }

        if (!$profile->banner_url) {
            return ApiResponse::error('No banner is set for this store.', 404);
        }

        $this->cloudinary->delete("vaultly/stores/banners/{$profile->store_slug}");

        $profile->update(['banner_url' => null]);

        return ApiResponse::success(null, 'Store banner removed successfully.');
    }

    /**
     * Generate a unique store slug from a store name.
     * Excludes the current profile's slug from the uniqueness check
     * so a seller can update their name without triggering a slug conflict
     * with their own existing record.
     *
     * @param  string $storeName
     * @param  int    $excludeProfileId
     * @return string
     */
    private function generateUniqueSlug(string $storeName, int $excludeProfileId): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($storeName);
        $slug     = $baseSlug;
        $counter  = 1;

        while (
            \App\Models\SellerProfile::where('store_slug', $slug)
                ->where('id', '!=', $excludeProfileId)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Format a seller profile for API output.
     *
     * @param  \App\Models\SellerProfile $profile
     * @return array<string, mixed>
     */
    private function formatProfile(\App\Models\SellerProfile $profile): array
    {
        return [
            'id'                => $profile->id,
            'store_name'        => $profile->store_name,
            'store_slug'        => $profile->store_slug,
            'store_description' => $profile->store_description,
            'logo_url'          => $profile->logo_url,
            'banner_url'        => $profile->banner_url,
            'website_url'       => $profile->website_url,
            'twitter_url'       => $profile->twitter_url,
            'github_url'        => $profile->github_url,
            'dribbble_url'      => $profile->dribbble_url,
            'linkedin_url'      => $profile->linkedin_url,
            'paypal_email'      => $profile->paypal_email,
            'available_balance' => $profile->available_balance,
            'total_earned'      => $profile->total_earned,
            'total_sales'       => $profile->total_sales,
        ];
    }
}