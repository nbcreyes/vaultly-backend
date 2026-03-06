<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\SubmitReviewRequest;
use App\Http\Requests\Seller\ReplyToReviewRequest;
use App\Http\Responses\ApiResponse;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ReviewController
 *
 * Handles review submission by buyers and seller replies.
 *
 * Endpoints:
 *   POST   /api/v1/reviews                  - submit a review
 *   PATCH  /api/v1/reviews/{id}/reply       - seller replies to a review
 *   DELETE /api/v1/reviews/{id}             - buyer deletes own review
 *   GET    /api/v1/products/{slug}/reviews  - paginated reviews for a product
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly \App\Services\NotificationService $notifications,
    ) {}
    /**
     * Submit a review for a purchased product.
     *
     * Rules:
     *   - Buyer must have purchased the product via a completed order
     *   - The order item must not be refunded
     *   - Buyer can only leave one review per product
     *
     * POST /api/v1/reviews
     */
    public function store(SubmitReviewRequest $request): JsonResponse
    {
        $buyer = $request->user();

        // Verify the order item belongs to this buyer
        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->whereHas(
                'order',
                fn($q) => $q->where('buyer_id', $buyer->id)
                    ->where('status', 'completed')
            )
            ->with('product')
            ->first();

        if (!$orderItem) {
            return ApiResponse::error(
                'You can only review products you have purchased.',
                403
            );
        }

        if ($orderItem->status === 'refunded') {
            return ApiResponse::error(
                'You cannot review a refunded purchase.',
                403
            );
        }

        // Check for an existing review on this product by this buyer
        $existingReview = Review::where('product_id', $orderItem->product_id)
            ->where('buyer_id', $buyer->id)
            ->first();

        if ($existingReview) {
            return ApiResponse::error(
                'You have already reviewed this product.',
                409
            );
        }

        $review = Review::create([
            'product_id'    => $orderItem->product_id,
            'buyer_id'      => $buyer->id,
            'order_item_id' => $orderItem->id,
            'rating'        => $request->rating,
            'body'          => $request->body,
            'is_visible'    => true,
        ]);

        // Recalculate the product's aggregate rating
        Review::recalculateForProduct($orderItem->product_id);

        $this->notifications->newReview(
            $orderItem->product->seller_id,
            $orderItem->product->title,
            $request->rating,
            $review->id,
            $orderItem->product_id
        );

        $review->load('buyer:id,name,avatar_url');

        return ApiResponse::created([
            'review' => $this->formatReview($review),
        ], 'Review submitted successfully.');
    }

    /**
     * Seller replies to a review on one of their products.
     * A seller can only reply once per review.
     *
     * PATCH /api/v1/reviews/{id}/reply
     */
    public function reply(ReplyToReviewRequest $request, string $id): JsonResponse
    {
        $review = Review::with('product')->find($id);

        if (!$review) {
            return ApiResponse::notFound('Review not found.');
        }

        // Verify the authenticated user is the seller of this product
        if ($review->product->seller_id !== $request->user()->id) {
            return ApiResponse::forbidden(
                'You can only reply to reviews on your own products.'
            );
        }

        if ($review->seller_reply) {
            return ApiResponse::error(
                'You have already replied to this review.',
                409
            );
        }

        $review->update([
            'seller_reply'      => $request->reply,
            'seller_replied_at' => now(),
        ]);

        $this->notifications->reviewReply(
            $review->buyer_id,
            $review->product->title,
            $review->id,
            $review->product_id
        );

        return ApiResponse::success([
            'review' => $this->formatReview($review->fresh('buyer')),
        ], 'Reply posted successfully.');
    }

    /**
     * Buyer deletes their own review.
     * The product aggregate rating is recalculated after deletion.
     *
     * DELETE /api/v1/reviews/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $review = Review::where('id', $id)
            ->where('buyer_id', $request->user()->id)
            ->first();

        if (!$review) {
            return ApiResponse::notFound('Review not found.');
        }

        $productId = $review->product_id;

        $review->delete();

        // Recalculate the product's aggregate rating
        Review::recalculateForProduct($productId);

        return ApiResponse::success(null, 'Review deleted successfully.');
    }

    /**
     * Get paginated reviews for a product by its slug.
     * Available to all users including guests.
     *
     * GET /api/v1/browse/products/{slug}/reviews
     *
     * Query parameters:
     *   sort     - newest|oldest|highest|lowest (default: newest)
     *   per_page - results per page (default 10, max 50)
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = \App\Models\Product::where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        $query = Review::where('product_id', $product->id)
            ->where('is_visible', true)
            ->with('buyer:id,name,avatar_url');

        $sort = $request->get('sort', 'newest');

        match ($sort) {
            'oldest'  => $query->oldest(),
            'highest' => $query->orderBy('rating', 'desc'),
            'lowest'  => $query->orderBy('rating', 'asc'),
            default   => $query->latest(),
        };

        $perPage = min((int) $request->get('per_page', 10), 50);
        $reviews = $query->paginate($perPage);

        $reviews->getCollection()->transform(
            fn($review) => $this->formatReview($review)
        );

        // Include rating distribution for the filter UI
        $distribution = Review::where('product_id', $product->id)
            ->where('is_visible', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill in any missing ratings with 0
        $ratingBreakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingBreakdown[$i] = $distribution[$i] ?? 0;
        }

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved.',
            'data'    => $reviews->items(),
            'meta'    => [
                'current_page'     => $reviews->currentPage(),
                'last_page'        => $reviews->lastPage(),
                'per_page'         => $reviews->perPage(),
                'total'            => $reviews->total(),
                'average_rating'   => $product->average_rating,
                'rating_breakdown' => $ratingBreakdown,
            ],
            'links' => [
                'first' => $reviews->url(1),
                'last'  => $reviews->url($reviews->lastPage()),
                'prev'  => $reviews->previousPageUrl(),
                'next'  => $reviews->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Format a review for API output.
     *
     * @param  Review $review
     * @return array<string, mixed>
     */
    private function formatReview(Review $review): array
    {
        return [
            'id'               => $review->id,
            'rating'           => $review->rating,
            'body'             => $review->body,
            'seller_reply'     => $review->seller_reply,
            'seller_replied_at' => $review->seller_replied_at,
            'is_visible'       => $review->is_visible,
            'created_at'       => $review->created_at,
            'buyer'            => $review->buyer ? [
                'name'       => $review->buyer->name,
                'avatar_url' => $review->buyer->avatar_url,
            ] : null,
        ];
    }
}
