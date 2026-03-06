<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SellerDashboardController
 *
 * Provides analytics and summary data for the seller dashboard.
 *
 * Endpoints:
 *   GET /api/v1/seller/dashboard/summary       - key metrics overview
 *   GET /api/v1/seller/dashboard/sales         - sales history with pagination
 *   GET /api/v1/seller/dashboard/revenue       - revenue chart data by period
 *   GET /api/v1/seller/dashboard/top-products  - best performing products
 *   GET /api/v1/seller/dashboard/transactions  - transaction ledger
 */
class SellerDashboardController extends Controller
{
    /**
     * Get the seller's key metrics summary.
     * Used to populate the summary cards at the top of the dashboard.
     *
     * Returns:
     *   - Available balance for payout
     *   - Total lifetime earnings
     *   - Total sales count
     *   - Total products listed
     *   - Average rating across all products
     *   - Pending refund requests count
     *   - This month vs last month revenue comparison
     *
     * GET /api/v1/seller/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;
        $profile  = $request->user()->sellerProfile;

        if (!$profile) {
            return ApiResponse::notFound('Seller profile not found.');
        }

        // This month revenue
        $thisMonthRevenue = OrderItem::where('seller_id', $sellerId)
            ->where('status', 'active')
            ->whereHas('order', fn($q) => $q->where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
            )
            ->sum('seller_earnings');

        // Last month revenue
        $lastMonthRevenue = OrderItem::where('seller_id', $sellerId)
            ->where('status', 'active')
            ->whereHas('order', fn($q) => $q->where('status', 'completed')
                ->whereMonth('paid_at', now()->subMonth()->month)
                ->whereYear('paid_at', now()->subMonth()->year)
            )
            ->sum('seller_earnings');

        // Revenue change percentage
        $revenueChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($thisMonthRevenue > 0 ? 100 : 0);

        // This month sales count
        $thisMonthSales = OrderItem::where('seller_id', $sellerId)
            ->where('status', 'active')
            ->whereHas('order', fn($q) => $q->where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
            )
            ->count();

        // Last month sales count
        $lastMonthSales = OrderItem::where('seller_id', $sellerId)
            ->where('status', 'active')
            ->whereHas('order', fn($q) => $q->where('status', 'completed')
                ->whereMonth('paid_at', now()->subMonth()->month)
                ->whereYear('paid_at', now()->subMonth()->year)
            )
            ->count();

        // Sales change percentage
        $salesChange = $lastMonthSales > 0
            ? round((($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 1)
            : ($thisMonthSales > 0 ? 100 : 0);

        // Product counts by status
        $productCounts = Product::where('seller_id', $sellerId)
            ->whereNull('deleted_at')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Average rating across all products
        $averageRating = Review::whereHas(
            'product',
            fn($q) => $q->where('seller_id', $sellerId)
        )
        ->where('is_visible', true)
        ->avg('rating');

        // Pending refund requests
        $pendingRefunds = \App\Models\Refund::where('seller_id', $sellerId)
            ->where('status', 'pending')
            ->count();

        // Unread messages count
        $unreadMessages = \App\Models\Message::where('recipient_id', $sellerId)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success([
            'balance' => [
                'available'   => (float) $profile->available_balance,
                'pending'     => (float) $profile->pending_balance,
                'total_earned'=> (float) $profile->total_earned,
            ],
            'sales' => [
                'total'            => $profile->total_sales,
                'this_month'       => $thisMonthSales,
                'last_month'       => $lastMonthSales,
                'change_percent'   => $salesChange,
            ],
            'revenue' => [
                'this_month'     => round((float) $thisMonthRevenue, 2),
                'last_month'     => round((float) $lastMonthRevenue, 2),
                'change_percent' => $revenueChange,
            ],
            'products' => [
                'published'   => (int) ($productCounts['published'] ?? 0),
                'draft'       => (int) ($productCounts['draft'] ?? 0),
                'unpublished' => (int) ($productCounts['unpublished'] ?? 0),
                'total'       => $productCounts->sum(),
            ],
            'average_rating'  => $averageRating ? round((float) $averageRating, 2) : null,
            'pending_refunds' => $pendingRefunds,
            'unread_messages' => $unreadMessages,
        ]);
    }

    /**
     * Get paginated sales history for the seller.
     * Each row is one order item (one product sold in one order).
     *
     * GET /api/v1/seller/dashboard/sales
     *
     * Query parameters:
     *   status    - active|refunded
     *   from      - date filter start (Y-m-d)
     *   to        - date filter end (Y-m-d)
     *   per_page  - results per page (default 20)
     */
    public function sales(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;

        $query = OrderItem::where('seller_id', $sellerId)
            ->whereHas('order', fn($q) => $q->where('status', 'completed'))
            ->with([
                'order:id,order_number,buyer_id,paid_at,total',
                'order.buyer:id,name,email,avatar_url',
                'product:id,title,slug,price',
                'product.images' => fn($q) => $q->where('sort_order', 0),
                'refund:id,order_item_id,status,reason',
            ])
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereHas(
                'order',
                fn($q) => $q->whereDate('paid_at', '>=', $request->from)
            );
        }

        if ($request->filled('to')) {
            $query->whereHas(
                'order',
                fn($q) => $q->whereDate('paid_at', '<=', $request->to)
            );
        }

        $sales = $query->paginate((int) $request->get('per_page', 20));

        $sales->getCollection()->transform(fn($item) => [
            'order_item_id'   => $item->id,
            'order_number'    => $item->order->order_number,
            'paid_at'         => $item->order->paid_at,
            'buyer'           => [
                'name'       => $item->order->buyer->name,
                'avatar_url' => $item->order->buyer->avatar_url,
            ],
            'product'         => [
                'id'        => $item->product->id,
                'title'     => $item->product->title,
                'slug'      => $item->product->slug,
                'thumbnail' => $item->product->images->first()?->url,
            ],
            'price'           => $item->price,
            'platform_fee'    => $item->platform_fee,
            'seller_earnings' => $item->seller_earnings,
            'status'          => $item->status,
            'refund'          => $item->refund ? [
                'status' => $item->refund->status,
                'reason' => $item->refund->reason,
            ] : null,
        ]);

        return ApiResponse::paginated($sales, 'Sales history retrieved.');
    }

    /**
     * Get revenue chart data grouped by day, week, or month.
     * Used to render the revenue chart on the dashboard.
     *
     * GET /api/v1/seller/dashboard/revenue
     *
     * Query parameters:
     *   period  - 7d|30d|90d|12m (default: 30d)
     */
    public function revenue(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;
        $period   = $request->get('period', '30d');

        [$startDate, $groupFormat, $labelFormat] = match ($period) {
            '7d'  => [now()->subDays(6)->startOfDay(),   '%Y-%m-%d', 'Y-m-d'],
            '90d' => [now()->subDays(89)->startOfDay(),  '%Y-%m-%d', 'Y-m-d'],
            '12m' => [now()->subMonths(11)->startOfMonth(),'%Y-%m',  'Y-m'],
            default => [now()->subDays(29)->startOfDay(), '%Y-%m-%d', 'Y-m-d'],
        };

        $rows = OrderItem::where('seller_id', $sellerId)
            ->where('status', 'active')
            ->whereHas('order', fn($q) => $q->where('status', 'completed')
                ->where('paid_at', '>=', $startDate)
            )
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw("DATE_FORMAT(orders.paid_at, '{$groupFormat}') as period_key,
                         SUM(order_items.seller_earnings) as revenue,
                         COUNT(*) as sales")
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');

        // Build a complete date series with zero-fill for missing days
        $series = [];
        $current = clone $startDate;
        $end     = now();

        while ($current <= $end) {
            $key = $current->format($labelFormat);

            $series[] = [
                'period'  => $key,
                'revenue' => $rows->has($key) ? round((float) $rows[$key]->revenue, 2) : 0,
                'sales'   => $rows->has($key) ? (int) $rows[$key]->sales : 0,
            ];

            // Advance by day or month depending on period
            if ($period === '12m') {
                $current->addMonth();
            } else {
                $current->addDay();
            }
        }

        // Calculate totals for the period
        $totalRevenue = array_sum(array_column($series, 'revenue'));
        $totalSales   = array_sum(array_column($series, 'sales'));

        return ApiResponse::success([
            'period'         => $period,
            'total_revenue'  => round($totalRevenue, 2),
            'total_sales'    => $totalSales,
            'series'         => $series,
        ]);
    }

    /**
     * Get the seller's top performing products.
     * Sorted by sales count descending.
     *
     * GET /api/v1/seller/dashboard/top-products
     *
     * Query parameters:
     *   limit  - number of products to return (default 5, max 20)
     */
    public function topProducts(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;
        $limit    = min((int) $request->get('limit', 5), 20);

        $products = Product::where('seller_id', $sellerId)
            ->whereNull('deleted_at')
            ->with([
                'images' => fn($q) => $q->where('sort_order', 0),
                'category:id,name,slug',
            ])
            ->withCount('reviews')
            ->orderBy('sales_count', 'desc')
            ->orderBy('average_rating', 'desc')
            ->limit($limit)
            ->get();

        $formatted = $products->map(fn($product) => [
            'id'             => $product->id,
            'title'          => $product->title,
            'slug'           => $product->slug,
            'status'         => $product->status,
            'price'          => $product->price,
            'sales_count'    => $product->sales_count,
            'view_count'     => $product->view_count,
            'average_rating' => $product->average_rating,
            'review_count'   => $product->reviews_count,
            'revenue'        => round($product->sales_count * $product->price * 0.9, 2),
            'thumbnail'      => $product->images->first()?->url,
            'category'       => $product->category?->name,
        ]);

        return ApiResponse::success(['products' => $formatted]);
    }

    /**
     * Get the seller's transaction ledger.
     * Shows all credits and debits on their account.
     *
     * GET /api/v1/seller/dashboard/transactions
     *
     * Query parameters:
     *   type      - seller_credit|payout|refund
     *   per_page  - results per page (default 20)
     */
    public function transactions(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;

        $query = Transaction::where('user_id', $sellerId)
            ->whereIn('type', ['seller_credit', 'payout', 'refund'])
            ->with([
                'orderItem.product:id,title,slug',
            ])
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $transactions = $query->paginate((int) $request->get('per_page', 20));

        $transactions->getCollection()->transform(fn($tx) => [
            'id'          => $tx->id,
            'type'        => $tx->type,
            'amount'      => $tx->amount,
            'description' => $tx->description,
            'product'     => $tx->orderItem?->product ? [
                'title' => $tx->orderItem->product->title,
                'slug'  => $tx->orderItem->product->slug,
            ] : null,
            'created_at'  => $tx->created_at,
        ]);

        return ApiResponse::paginated($transactions, 'Transactions retrieved.');
    }
}