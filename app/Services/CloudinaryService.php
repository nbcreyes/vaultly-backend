<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;

/**
 * CloudinaryService
 *
 * Wraps the Cloudinary PHP SDK for all file upload and deletion operations
 * across the Vaultly platform.
 *
 * Upload methods return a consistent array:
 * [
 *   'public_id' => 'vaultly/stores/logos/abc123',
 *   'url'       => 'https://res.cloudinary.com/...',
 *   'format'    => 'jpg',
 *   'bytes'     => 204800,
 *   'width'     => 400,
 *   'height'    => 400,
 * ]
 *
 * All files are organized into folders by type:
 *   vaultly/stores/logos/     - seller store logos
 *   vaultly/stores/banners/   - seller store banners
 *   vaultly/avatars/          - user profile avatars
 *   vaultly/products/images/  - product preview images
 *   vaultly/products/files/   - product download files (private delivery)
 */
class CloudinaryService
{
    private Cloudinary $cloudinary;

    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key'    => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->cloudinary = new Cloudinary();
    }

    /**
     * Upload a store logo.
     * Resized to a maximum of 400x400 pixels, cropped to fill.
     *
     * @param  UploadedFile $file
     * @param  string       $storeSlug  Used to generate a stable public ID.
     * @return array<string, mixed>
     */
    public function uploadStoreLogo(UploadedFile $file, string $storeSlug): array
    {
        return $this->uploadImage($file, "vaultly/stores/logos/{$storeSlug}", [
            'transformation' => [
                'width'   => 400,
                'height'  => 400,
                'crop'    => 'fill',
                'gravity' => 'face',
            ],
        ]);
    }

    /**
     * Upload a store banner.
     * Resized to a maximum of 1200x300 pixels, cropped to fill.
     *
     * @param  UploadedFile $file
     * @param  string       $storeSlug
     * @return array<string, mixed>
     */
    public function uploadStoreBanner(UploadedFile $file, string $storeSlug): array
    {
        return $this->uploadImage($file, "vaultly/stores/banners/{$storeSlug}", [
            'transformation' => [
                'width'  => 1200,
                'height' => 300,
                'crop'   => 'fill',
            ],
        ]);
    }

    /**
     * Upload a user avatar.
     * Resized to 200x200 pixels.
     *
     * @param  UploadedFile $file
     * @param  int          $userId
     * @return array<string, mixed>
     */
    public function uploadAvatar(UploadedFile $file, int $userId): array
    {
        return $this->uploadImage($file, "vaultly/avatars/user_{$userId}", [
            'transformation' => [
                'width'   => 200,
                'height'  => 200,
                'crop'    => 'fill',
                'gravity' => 'face',
            ],
        ]);
    }

    /**
     * Upload a product preview image.
     * Resized to a maximum width of 1200 pixels.
     *
     * @param  UploadedFile $file
     * @param  int          $productId
     * @param  int          $sortOrder
     * @return array<string, mixed>
     */
    public function uploadProductImage(UploadedFile $file, int $productId, int $sortOrder): array
    {
        return $this->uploadImage(
            $file,
            "vaultly/products/images/product_{$productId}_image_{$sortOrder}",
            [
                'transformation' => [
                    'width'   => 1200,
                    'crop'    => 'limit',
                    'quality' => 'auto',
                ],
            ]
        );
    }

    /**
     * Upload a product file (ZIP, PDF, MP3, MP4, font files, etc).
     * Stored as a raw file — no transformation applied.
     * The resource_type is set to 'auto' so Cloudinary handles all file types.
     *
     * @param  UploadedFile $file
     * @param  int          $productId
     * @return array<string, mixed>
     */
    public function uploadProductFile(UploadedFile $file, int $productId): array
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'public_id'     => "vaultly/products/files/product_{$productId}",
                'resource_type' => 'auto',
                'overwrite'     => true,
                'use_filename'  => false,
            ]
        );

        return [
            'public_id' => $result['public_id'],
            'url'       => $result['secure_url'],
            'format'    => $result['format'] ?? $file->getClientOriginalExtension(),
            'bytes'     => $result['bytes'],
            'width'     => $result['width'] ?? null,
            'height'    => $result['height'] ?? null,
        ];
    }

    /**
     * Delete a file from Cloudinary by its public ID.
     * Used when replacing images or deleting products.
     *
     * @param  string $publicId
     * @param  string $resourceType  image|video|raw|auto
     * @return bool
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType,
            ]);

            return $result['result'] === 'ok';
        } catch (\Exception $e) {
            // Log but do not throw — a failed deletion should not block the user
            \Illuminate\Support\Facades\Log::warning('Cloudinary deletion failed', [
                'public_id' => $publicId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Internal helper to upload an image with given options.
     *
     * @param  UploadedFile         $file
     * @param  string               $publicId
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function uploadImage(UploadedFile $file, string $publicId, array $options = []): array
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            array_merge([
                'public_id'     => $publicId,
                'resource_type' => 'image',
                'overwrite'     => true,
                'use_filename'  => false,
            ], $options)
        );

        return [
            'public_id' => $result['public_id'],
            'url'       => $result['secure_url'],
            'format'    => $result['format'],
            'bytes'     => $result['bytes'],
            'width'     => $result['width'] ?? null,
            'height'    => $result['height'] ?? null,
        ];
    }
}