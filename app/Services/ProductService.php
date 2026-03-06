<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * ProductService
 *
 * Handles the business logic for product creation, updates,
 * tag management, image management, and file replacement.
 *
 * All Cloudinary interactions are delegated to CloudinaryService.
 */
class ProductService
{
    public function __construct(
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * Create a new product with images and a product file.
     *
     * @param  array<string, mixed> $data      Validated request data
     * @param  int                  $sellerId
     * @param  UploadedFile         $file      The product download file
     * @param  UploadedFile[]       $images    Preview images array
     * @return Product
     */
    public function createProduct(array $data, int $sellerId, UploadedFile $file, array $images): Product
    {
        // Generate a unique slug from the product title
        $slug = $this->generateUniqueSlug($data['title']);

        // Create the product record first so we have an ID for Cloudinary paths
        $product = Product::create([
            'seller_id'         => $sellerId,
            'category_id'       => $data['category_id'],
            'title'             => $data['title'],
            'slug'              => $slug,
            'short_description' => $data['short_description'],
            'description'       => $data['description'],
            'price'             => $data['price'],
            'license_type'      => $data['license_type'],
            'version'           => $data['version'],
            'changelog'         => $data['changelog'] ?? null,
            'status'            => 'draft',
            // Placeholder values — replaced immediately after upload
            'file_cloudinary_id' => 'pending',
            'file_name'          => $file->getClientOriginalName(),
            'file_type'          => $file->getClientOriginalExtension(),
            'file_size'          => $file->getSize(),
        ]);

        // Upload the product file to Cloudinary
        $fileResult = $this->cloudinary->uploadProductFile($file, $product->id);

        $product->update([
            'file_cloudinary_id' => $fileResult['public_id'],
            'file_name'          => $file->getClientOriginalName(),
            'file_type'          => $fileResult['format'],
            'file_size'          => $fileResult['bytes'],
        ]);

        // Upload preview images
        $this->uploadProductImages($product, $images, startingOrder: 0);

        // Sync tags
        if (!empty($data['tags'])) {
            $this->syncTags($product, $data['tags']);
        }

        return $product->load(['images', 'tags', 'category']);
    }

    /**
     * Update an existing product.
     * Only provided fields are updated.
     * If a new file is provided, the old one is replaced on Cloudinary.
     *
     * @param  Product              $product
     * @param  array<string, mixed> $data
     * @param  UploadedFile|null    $file
     * @return Product
     */
    public function updateProduct(Product $product, array $data, ?UploadedFile $file = null): Product
    {
        $updatable = [
            'category_id',
            'title',
            'short_description',
            'description',
            'price',
            'license_type',
            'version',
            'changelog',
        ];

        $updateData = array_intersect_key($data, array_flip($updatable));

        // If title changed, regenerate slug
        if (isset($updateData['title']) && $updateData['title'] !== $product->title) {
            $updateData['slug'] = $this->generateUniqueSlug($updateData['title'], $product->id);
        }

        // Replace product file if a new one was uploaded
        if ($file) {
            // Delete the old file from Cloudinary
            $this->cloudinary->delete($product->file_cloudinary_id, 'raw');

            // Upload the new file
            $fileResult = $this->cloudinary->uploadProductFile($file, $product->id);

            $updateData['file_cloudinary_id'] = $fileResult['public_id'];
            $updateData['file_name']          = $file->getClientOriginalName();
            $updateData['file_type']          = $fileResult['format'];
            $updateData['file_size']          = $fileResult['bytes'];
        }

        $product->update($updateData);

        // Sync tags if provided
        if (isset($data['tags'])) {
            $this->syncTags($product, $data['tags']);
        }

        return $product->load(['images', 'tags', 'category']);
    }

    /**
     * Add new preview images to a product.
     * The starting sort order is calculated from existing image count.
     *
     * @param  Product        $product
     * @param  UploadedFile[] $images
     * @return Product
     */
    public function addProductImages(Product $product, array $images): Product
    {
        $existingCount = $product->images()->count();

        $this->uploadProductImages($product, $images, startingOrder: $existingCount);

        return $product->load(['images', 'tags', 'category']);
    }

    /**
     * Delete a single product image by ID.
     * Verifies ownership before deleting.
     *
     * @param  Product $product
     * @param  int     $imageId
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function deleteProductImage(Product $product, int $imageId): void
    {
        $image = ProductImage::where('id', $imageId)
            ->where('product_id', $product->id)
            ->first();

        if (!$image) {
            throw new \InvalidArgumentException('Image not found on this product.');
        }

        // Must keep at least one image
        if ($product->images()->count() <= 1) {
            throw new \InvalidArgumentException('You must keep at least one preview image.');
        }

        $this->cloudinary->delete($image->cloudinary_id);
        $image->delete();

        // Reorder remaining images to fill the gap
        $this->reorderImages($product);
    }

    /**
     * Sync tags for a product.
     * Creates new tags if they do not exist yet.
     * Tags are stored lowercase with a URL-safe slug.
     *
     * @param  Product  $product
     * @param  string[] $tagNames
     * @return void
     */
    public function syncTags(Product $product, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $name = strtolower(trim($tagName));
            $slug = Str::slug($name);

            if (empty($slug)) {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );

            $tagIds[] = $tag->id;
        }

        $product->tags()->sync($tagIds);
    }

    /**
     * Upload an array of images to Cloudinary and create ProductImage records.
     *
     * @param  Product        $product
     * @param  UploadedFile[] $images
     * @param  int            $startingOrder
     * @return void
     */
    private function uploadProductImages(Product $product, array $images, int $startingOrder): void
    {
        foreach ($images as $index => $image) {
            $sortOrder = $startingOrder + $index;

            $result = $this->cloudinary->uploadProductImage($image, $product->id, $sortOrder);

            ProductImage::create([
                'product_id'    => $product->id,
                'cloudinary_id' => $result['public_id'],
                'url'           => $result['url'],
                'sort_order'    => $sortOrder,
            ]);
        }
    }

    /**
     * Reorder product images sequentially after a deletion.
     * Ensures sort_order values are always 0, 1, 2, ... with no gaps.
     *
     * @param  Product $product
     * @return void
     */
    private function reorderImages(Product $product): void
    {
        $images = $product->images()->orderBy('sort_order')->get();

        foreach ($images as $index => $image) {
            $image->update(['sort_order' => $index]);
        }
    }

    /**
     * Generate a unique product slug from a title.
     * Excludes the current product ID from the uniqueness check
     * to allow editing without slug collision on the same product.
     *
     * @param  string   $title
     * @param  int|null $excludeProductId
     * @return string
     */
    private function generateUniqueSlug(string $title, ?int $excludeProductId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

        while (
            Product::where('slug', $slug)
                ->when($excludeProductId, fn($q) => $q->where('id', '!=', $excludeProductId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}