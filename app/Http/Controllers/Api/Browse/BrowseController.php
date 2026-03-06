<?php

namespace App\Http\Controllers\Api\Browse;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BrowseController
 *
 * Handles all public product browsing endpoints.
 * No authentication required on any of these routes.
 *
 * Endpoints:
 *   GET /api/v1/browse/categories                   - list all active categories
 *   GET /api/v1/browse/products                     - list and search products
 *   GET /api/v1/browse/products/{slug}              - single product detail
 *   GET /api/v1/browse/categories/{slug}/products   - products in a category
 *   GET /api/v1/browse/stores/{slug}                - seller store page
 *   GET /api/v1/browse/featured                     - featured products for homepage
 */
class BrowseController extends Controller
{
    /**
     * List all active categories.
     * Used to populate navigation and category pages.
     *
     * GET /api/v1/browse/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->withCount([
                'products' => fn($q) => $q->where('status', 'published'),
            ])
            ->get();

        return ApiResponse::success(['categories' => $categories]);
    }

    /**
     * List and search published products with filtering and sorting.
     *
     * GET /api/v1/browse/products
     *
     * Query parameters:
     *   q           - full text search across title, short_description, tags
     *   category    - category slug to filter by
     *   min_price   - minimum price filter
     *   max_price   - maximum price filter
     *   license     - personal|commercial
     *   tags        - comma-separated tag slugs e.g. tags=vue,tailwind
     *   sort        - newest|oldest|price_asc|price_desc|top_rated|best_selling
     *   per_page    - results per page (default 20, max 50)
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::where('status', 'published')
            ->with([
                'category:id,name,slug',
                'images' => fn($q) => $q->where('sort_order', 0),
                'tags:id,name,slug',
            ])
            ->withCount('reviews');

        // Full text search across title, short_description
        // and tags via a subquery join
        if ($request->filled('q')) {
            $search = $request->q;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%")
                  ->orWhereHas('tags', fn($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->whereHas(
                'category',
                fn($q) => $q->where('slug', $request->category)
            );
        }

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // License type filter
        if ($request->filled('license') && in_array($request->license, ['personal', 'commercial'])) {
            $query->where('license_type', $request->license);
        }

        // Tag filter — comma-separated slugs
        if ($request->filled('tags')) {
            $tagSlugs = array_filter(explode(',', $request->tags));

            if (!empty($tagSlugs)) {
                foreach ($tagSlugs as $tagSlug) {
                    $query->whereHas(
                        'tags',
                        fn($q) => $q->where('slug', trim($tagSlug))
                    );
                }
            }
        }

        // Sorting
        $sort = $request->get('sort', 'newest');

        match ($sort) {
            'oldest'       => $query->oldest(),
            'price_asc'    => $query->orderBy('price', 'asc'),
            'price_desc'   => $query->orderBy('price', 'desc'),
            'top_rated'    => $query->orderBy('average_rating', 'desc')->orderBy('review_count', 'desc'),
            'best_selling' => $query->orderBy('sales_count', 'desc'),
            default        => $query->latest(), // newest
        };

        $perPage = min((int) $request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        return ApiResponse::paginated($products, 'Products retrieved.');
    }

    /**
     * Get a single published product by its slug.
     * Increments the view count on each call.
     * Returns full product details including seller info and reviews.
     *
     * GET /api/v1/browse/products/{slug}
     */
    public function productDetail(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'category:id,name,slug',
                'images',
                'tags:id,name,slug',
                'seller:id,name,avatar_url',
                'seller.sellerProfile:id,user_id,store_name,store_slug,logo_url,banner_url,store_description,total_sales,total_earned',
                'reviews' => fn($q) => $q->where('is_visible', true)
                    ->with('buyer:id,name,avatar_url')
                    ->latest()
                    ->limit(10),
            ])
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        // Increment view count — fire and forget, do not fail the request if this fails
        try {
            $product->increment('view_count');
        } catch (\Throwable) {
            // Silently ignore view count failures
        }

        // If the buyer is authenticated, check if they have purchased this product
        $hasPurchased = false;
        $hasReviewed  = false;

        if ($request->bearerToken()) {
            $user = auth('sanctum')->user();

            if ($user) {
                $hasPurchased = \App\Models\OrderItem::whereHas(
                    'order',
                    fn($q) => $q->where('buyer_id', $user->id)->where('status', 'completed')
                )
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->exists();

                $hasReviewed = \App\Models\Review::where('product_id', $product->id)
                    ->where('buyer_id', $user->id)
                    ->exists();
            }
        }

        return ApiResponse::success([
            'product'      => $this->formatProductDetail($product),
            'has_purchased' => $hasPurchased,
            'has_reviewed'  => $hasReviewed,
        ]);
    }

    /**
     * List published products in a specific category.
     * Supports the same filters and sorting as the main product list.
     *
     * GET /api/v1/browse/categories/{slug}/products
     */
    public function categoryProducts(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$category) {
            return ApiResponse::notFound('Category not found.');
        }

        $query = Product::where('status', 'published')
            ->where('category_id', $category->id)
            ->with([
                'category:id,name,slug',
                'images' => fn($q) => $q->where('sort_order', 0),
                'tags:id,name,slug',
            ])
            ->withCount('reviews');

        // Price range filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // License type filter
        if ($request->filled('license') && in_array($request->license, ['personal', 'commercial'])) {
            $query->where('license_type', $request->license);
        }

        // Search within category
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'newest');

        match ($sort) {
            'oldest'       => $query->oldest(),
            'price_asc'    => $query->orderBy('price', 'asc'),
            'price_desc'   => $query->orderBy('price', 'desc'),
            'top_rated'    => $query->orderBy('average_rating', 'desc'),
            'best_selling' => $query->orderBy('sales_count', 'desc'),
            default        => $query->latest(),
        };

        $perPage  = min((int) $request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        return ApiResponse::paginated($products, "Products in {$category->name} retrieved.");
    }

    /**
     * Get a seller's public store page.
     * Includes store profile and paginated published products.
     *
     * GET /api/v1/browse/stores/{slug}
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $profile = SellerProfile::where('store_slug', $slug)
            ->with('user:id,name,avatar_url,created_at')
            ->first();

        if (!$profile) {
            return ApiResponse::notFound('Store not found.');
        }

        $productsQuery = Product::where('seller_id', $profile->user_id)
            ->where('status', 'published')
            ->with([
                'category:id,name,slug',
                'images' => fn($q) => $q->where('sort_order', 0),
                'tags:id,name,slug',
            ])
            ->withCount('reviews');

        // Category filter within the store
        if ($request->filled('category')) {
            $productsQuery->whereHas(
                'category',
                fn($q) => $q->where('slug', $request->category)
            );
        }

        // Sorting
        $sort = $request->get('sort', 'newest');

        match ($sort) {
            'price_asc'    => $productsQuery->orderBy('price', 'asc'),
            'price_desc'   => $productsQuery->orderBy('price', 'desc'),
            'top_rated'    => $productsQuery->orderBy('average_rating', 'desc'),
            'best_selling' => $productsQuery->orderBy('sales_count', 'desc'),
            default        => $productsQuery->latest(),
        };

        $perPage  = min((int) $request->get('per_page', 20), 50);
        $products = $productsQuery->paginate($perPage);

        return ApiResponse::success([
            'store'    => $this->formatStoreProfile($profile),
            'products' => [
                'data'  => $products->items(),
                'meta'  => [
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                    'per_page'     => $products->perPage(),
                    'total'        => $products->total(),
                ],
            ],
        ]);
    }

    /**
     * Get featured products for the homepage.
     * Returns the top products by sales count across all categories,
     * plus one featured product per category.
     *
     * GET /api/v1/browse/featured
     */
    public function featured(): JsonResponse
    {
        // Top 8 best-selling products overall
        $topProducts = Product::where('status', 'published')
            ->with([
                'category:id,name,slug',
                'images' => fn($q) => $q->where('sort_order', 0),
                'seller.sellerProfile:id,user_id,store_name,store_slug,logo_url',
            ])
            ->orderBy('sales_count', 'desc')
            ->limit(8)
            ->get();

        // One top product per category for the category highlights section
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $categoryHighlights = [];

        foreach ($categories as $category) {
            $product = Product::where('status', 'published')
                ->where('category_id', $category->id)
                ->with([
                    'images' => fn($q) => $q->where('sort_order', 0),
                    'seller.sellerProfile:id,user_id,store_name,store_slug',
                ])
                ->orderBy('sales_count', 'desc')
                ->first();

            $categoryHighlights[] = [
                'category' => [
                    'id'           => $category->id,
                    'name'         => $category->name,
                    'slug'         => $category->slug,
                    'icon'         => $category->icon,
                    'description'  => $category->description,
                ],
                'top_product' => $product ? $this->formatProductCard($product) : null,
            ];
        }

        // Newest arrivals — last 8 published products
        $newArrivals = Product::where('status', 'published')
            ->with([
                'category:id,name,slug',
                'images' => fn($q) => $q->where('sort_order', 0),
                'seller.sellerProfile:id,user_id,store_name,store_slug,logo_url',
            ])
            ->latest()
            ->limit(8)
            ->get();

        return ApiResponse::success([
            'top_products'        => $topProducts->map(fn($p) => $this->formatProductCard($p)),
            'category_highlights' => $categoryHighlights,
            'new_arrivals'        => $newArrivals->map(fn($p) => $this->formatProductCard($p)),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private formatters
    // -------------------------------------------------------------------------

    /**
     * Format a product for card display (list views).
     * Returns a minimal set of fields sufficient for a product card.
     *
     * @param  Product $product
     * @return array<string, mixed>
     */
    private function formatProductCard(Product $product): array
    {
        return [
            'id'               => $product->id,
            'title'            => $product->title,
            'slug'             => $product->slug,
            'short_description'=> $product->short_description,
            'price'            => $product->price,
            'license_type'     => $product->license_type,
            'average_rating'   => $product->average_rating,
            'review_count'     => $product->review_count,
            'sales_count'      => $product->sales_count,
            'category'         => $product->category,
            'thumbnail'        => $product->images->first()?->url,
            'seller'           => $product->seller?->sellerProfile ? [
                'store_name' => $product->seller->sellerProfile->store_name,
                'store_slug' => $product->seller->sellerProfile->store_slug,
                'logo_url'   => $product->seller->sellerProfile->logo_url,
            ] : null,
        ];
    }

    /**
     * Format a product for the detail page.
     * Returns all fields including full description, images, tags, and reviews.
     *
     * @param  Product $product
     * @return array<string, mixed>
     */
    private function formatProductDetail(Product $product): array
    {
        return [
            'id'                => $product->id,
            'title'             => $product->title,
            'slug'              => $product->slug,
            'short_description' => $product->short_description,
            'description'       => $product->description,
            'price'             => $product->price,
            'license_type'      => $product->license_type,
            'version'           => $product->version,
            'changelog'         => $product->changelog,
            'average_rating'    => $product->average_rating,
            'review_count'      => $product->review_count,
            'sales_count'       => $product->sales_count,
            'view_count'        => $product->view_count,
            'file_name'         => $product->file_name,
            'file_type'         => $product->file_type,
            'file_size'         => $product->file_size,
            'category'          => $product->category,
            'tags'              => $product->tags,
            'images'            => $product->images,
            'seller'            => $product->seller ? [
                'id'         => $product->seller->id,
                'name'       => $product->seller->name,
                'avatar_url' => $product->seller->avatar_url,
                'store'      => $product->seller->sellerProfile ? [
                    'store_name'        => $product->seller->sellerProfile->store_name,
                    'store_slug'        => $product->seller->sellerProfile->store_slug,
                    'logo_url'          => $product->seller->sellerProfile->logo_url,
                    'banner_url'        => $product->seller->sellerProfile->banner_url,
                    'store_description' => $product->seller->sellerProfile->store_description,
                    'total_sales'       => $product->seller->sellerProfile->total_sales,
                ] : null,
            ] : null,
            'reviews'           => $product->reviews->map(fn($r) => [
                'id'               => $r->id,
                'rating'           => $r->rating,
                'body'             => $r->body,
                'seller_reply'     => $r->seller_reply,
                'seller_replied_at'=> $r->seller_replied_at,
                'created_at'       => $r->created_at,
                'buyer'            => [
                    'name'       => $r->buyer->name,
                    'avatar_url' => $r->buyer->avatar_url,
                ],
            ]),
            'created_at'        => $product->created_at,
            'updated_at'        => $product->updated_at,
        ];
    }

    /**
     * Format a seller profile for the store page.
     *
     * @param  SellerProfile $profile
     * @return array<string, mixed>
     */
    private function formatStoreProfile(SellerProfile $profile): array
    {
        return [
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
            'total_sales'       => $profile->total_sales,
            'member_since'      => $profile->user->created_at,
            'seller'            => [
                'name'       => $profile->user->name,
                'avatar_url' => $profile->user->avatar_url,
            ],
        ];
    }
}