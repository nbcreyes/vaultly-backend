<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\AddProductImagesRequest;
use App\Http\Requests\Seller\CreateProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Services\CloudinaryService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SellerProductController
 *
 * Handles all product management actions for approved sellers.
 * A seller can only manage their own products.
 *
 * Endpoints:
 *   GET    /api/v1/seller/products                        - list own products
 *   POST   /api/v1/seller/products                        - create product
 *   GET    /api/v1/seller/products/{id}                   - get single product
 *   PATCH  /api/v1/seller/products/{id}                   - update product
 *   POST   /api/v1/seller/products/{id}/publish           - publish draft
 *   POST   /api/v1/seller/products/{id}/unpublish         - unpublish product
 *   DELETE /api/v1/seller/products/{id}                   - delete product
 *   POST   /api/v1/seller/products/{id}/images            - add preview images
 *   DELETE /api/v1/seller/products/{id}/images/{imageId}  - delete one image
 */
class SellerProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CloudinaryService $cloudinary,
    ) {}

    /**
     * List the authenticated seller's products with optional status filter.
     *
     * GET /api/v1/seller/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('seller_id', $request->user()->id)
            ->with(['category:id,name,slug', 'images', 'tags'])
            ->withCount('reviews')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->paginate(20);

        return ApiResponse::paginated($products, 'Products retrieved.');
    }

    /**
     * Create a new product.
     * Product starts in draft status. Seller must publish it explicitly.
     *
     * POST /api/v1/seller/products
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct(
            data:     $request->validated(),
            sellerId: $request->user()->id,
            file:     $request->file('product_file'),
            images:   $request->file('images'),
        );

        return ApiResponse::created([
            'product' => $this->formatProduct($product),
        ], 'Product created successfully. It is currently in draft status. Publish it when you are ready.');
    }

    /**
     * Get a single product owned by the authenticated seller.
     *
     * GET /api/v1/seller/products/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->with(['category:id,name,slug', 'images', 'tags'])
            ->withCount('reviews')
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        return ApiResponse::success([
            'product' => $this->formatProduct($product),
        ]);
    }

    /**
     * Update a product.
     * Only fields provided in the request are updated.
     * Rejected products cannot be edited.
     *
     * PATCH /api/v1/seller/products/{id}
     */
    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        if ($product->status === 'rejected') {
            return ApiResponse::error('Rejected products cannot be edited.', 403);
        }

        $product = $this->productService->updateProduct(
            product: $product,
            data:    $request->validated(),
            file:    $request->file('product_file'),
        );

        return ApiResponse::success([
            'product' => $this->formatProduct($product),
        ], 'Product updated successfully.');
    }

    /**
     * Publish a product (move from draft or unpublished to published).
     *
     * POST /api/v1/seller/products/{id}/publish
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        if ($product->status === 'published') {
            return ApiResponse::error('This product is already published.', 409);
        }

        if ($product->status === 'rejected') {
            return ApiResponse::error('Rejected products cannot be published.', 403);
        }

        $product->update(['status' => 'published']);

        return ApiResponse::success([
            'product' => $this->formatProduct($product->fresh(['images', 'tags', 'category'])),
        ], 'Product published successfully.');
    }

    /**
     * Unpublish a product (take it off the marketplace).
     * The product is not deleted — it moves to unpublished status.
     *
     * POST /api/v1/seller/products/{id}/unpublish
     */
    public function unpublish(Request $request, string $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        if ($product->status === 'unpublished') {
            return ApiResponse::error('This product is already unpublished.', 409);
        }

        if ($product->status === 'rejected') {
            return ApiResponse::error('This product has been rejected and cannot be unpublished.', 403);
        }

        $product->update(['status' => 'unpublished']);

        return ApiResponse::success(null, 'Product unpublished successfully.');
    }

    /**
     * Permanently delete a product.
     * Deletes all associated images and the product file from Cloudinary.
     * Cannot delete a product that has sales history.
     *
     * DELETE /api/v1/seller/products/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->withCount('orderItems')
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        // Prevent deletion of products with sales history
        // to preserve transaction records
        if ($product->order_items_count > 0) {
            return ApiResponse::error(
                'Products with sales history cannot be deleted. Unpublish it instead.',
                403
            );
        }

        // Delete all preview images from Cloudinary
        foreach ($product->images as $image) {
            $this->cloudinary->delete($image->cloudinary_id);
        }

        // Delete the product file from Cloudinary
        if ($product->file_cloudinary_id && $product->file_cloudinary_id !== 'pending') {
            $this->cloudinary->delete($product->file_cloudinary_id, 'raw');
        }

        // Soft delete the product — preserves the record in the database
        $product->delete();

        return ApiResponse::success(null, 'Product deleted successfully.');
    }

    /**
     * Add more preview images to an existing product.
     * Total image count cannot exceed 5.
     *
     * POST /api/v1/seller/products/{id}/images
     */
    public function addImages(AddProductImagesRequest $request, string $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->with('images')
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        $existingCount = $product->images->count();
        $newCount      = count($request->file('images'));
        $maxImages     = 5;

        if ($existingCount + $newCount > $maxImages) {
            return ApiResponse::error(
                "You can only have {$maxImages} preview images per product. "
                . "This product currently has {$existingCount}.",
                422
            );
        }

        $product = $this->productService->addProductImages(
            $product,
            $request->file('images')
        );

        return ApiResponse::success([
            'images' => $product->images,
        ], 'Images added successfully.');
    }

    /**
     * Delete a single preview image from a product.
     * At least one image must remain at all times.
     *
     * DELETE /api/v1/seller/products/{id}/images/{imageId}
     */
    public function deleteImage(Request $request, string $id, string $imageId): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('seller_id', $request->user()->id)
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        try {
            $this->productService->deleteProductImage($product, (int) $imageId);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Image deleted successfully.');
    }

    /**
     * Format a product for API output.
     *
     * @param  Product $product
     * @return array<string, mixed>
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id'                 => $product->id,
            'title'              => $product->title,
            'slug'               => $product->slug,
            'short_description'  => $product->short_description,
            'description'        => $product->description,
            'category'           => $product->category,
            'tags'               => $product->tags,
            'price'              => $product->price,
            'license_type'       => $product->license_type,
            'version'            => $product->version,
            'changelog'          => $product->changelog,
            'status'             => $product->status,
            'images'             => $product->images,
            'file_name'          => $product->file_name,
            'file_type'          => $product->file_type,
            'file_size'          => $product->file_size,
            'average_rating'     => $product->average_rating,
            'review_count'       => $product->review_count,
            'sales_count'        => $product->sales_count,
            'view_count'         => $product->view_count,
            'created_at'         => $product->created_at,
            'updated_at'         => $product->updated_at,
        ];
    }
}