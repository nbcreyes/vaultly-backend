<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Refund;
use App\Models\SellerProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminDashboardController
 *
 * Provides platform-wide analytics and management data for admins.
 *
 * Endpoints:
 *   GET /api/v1/admin/dashboard/summary       - platform key metrics
 *   GET /api/v1/admin/dashboard/revenue       - platform revenue chart
 *   GET /api/v1/admin/dashboard/top-sellers   - highest earning sellers
 *   GET /api/v1/admin/users                   - list and search all users
 *   PATCH /api/v1/admin/users/{id}/status     - suspend or ban a user
 *   GET /api/v1/admin/products                - list all products for moderation
 *   PATCH /api/v1/admin/products/{id}/status  - force-unpublish a product
 */
class AdminDashboardController extends Controller
{
    /**
     * Get platform-wide key metrics.
     * Used to populate the summary cards at the top of the admin dashboard.
     *
     * GET /api/v1/admin/dashboard/summary
     */
    public function summary(): JsonResponse
    {
        // User counts by role
        $userCounts = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        // User counts by status
        $suspendedCount = User::where('status', 'suspended')->count();
        $bannedCount    = User::where('status', 'banned')->count();

        // New users this month
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Revenue stats
        $totalRevenue = Transaction::where('type', 'commission')
            ->sum('amount');

        $thisMonthRevenue = Transaction::where('type', 'commission')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $lastMonthRevenue = Transaction::where('type', 'commission')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount');

        $revenueChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($thisMonthRevenue > 0 ? 100 : 0);

        // Order stats
        $totalOrders       = Order::where('status', 'completed')->count();
        $thisMonthOrders   = Order::where('status', 'completed')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->count();

        // Product stats
        $productCounts = Product::whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Pending items requiring admin attention
        $pendingSellerApplications = \App\Models\SellerApplication::where('status', 'pending')->count();
        $pendingRefunds            = Refund::where('status', 'pending')->count();
        $pendingPayouts            = \App\Models\Payout::where('status', 'pending')->count();

        return ApiResponse::success([
            'users' => [
                'total'          => $userCounts->sum(),
                'buyers'         => (int) ($userCounts['buyer'] ?? 0),
                'sellers'        => (int) ($userCounts['seller'] ?? 0),
                'admins'         => (int) ($userCounts['admin'] ?? 0),
                'suspended'      => $suspendedCount,
                'banned'         => $bannedCount,
                'new_this_month' => $newUsersThisMonth,
            ],
            'revenue' => [
                'total_platform'   => round((float) $totalRevenue, 2),
                'this_month'       => round((float) $thisMonthRevenue, 2),
                'last_month'       => round((float) $lastMonthRevenue, 2),
                'change_percent'   => $revenueChange,
            ],
            'orders' => [
                'total'          => $totalOrders,
                'this_month'     => $thisMonthOrders,
            ],
            'products' => [
                'published'   => (int) ($productCounts['published'] ?? 0),
                'draft'       => (int) ($productCounts['draft'] ?? 0),
                'unpublished' => (int) ($productCounts['unpublished'] ?? 0),
                'total'       => $productCounts->sum(),
            ],
            'pending_actions' => [
                'seller_applications' => $pendingSellerApplications,
                'refunds'             => $pendingRefunds,
                'payouts'             => $pendingPayouts,
                'total'               => $pendingSellerApplications + $pendingRefunds + $pendingPayouts,
            ],
        ]);
    }

    /**
     * Get platform revenue chart data grouped by period.
     * Shows total platform commission earned over time.
     *
     * GET /api/v1/admin/dashboard/revenue
     *
     * Query parameters:
     *   period - 7d|30d|90d|12m (default: 30d)
     */
    public function revenue(Request $request): JsonResponse
    {
        $period = $request->get('period', '30d');

        [$startDate, $groupFormat, $labelFormat] = match ($period) {
            '7d'  => [now()->subDays(6)->startOfDay(),    '%Y-%m-%d', 'Y-m-d'],
            '90d' => [now()->subDays(89)->startOfDay(),   '%Y-%m-%d', 'Y-m-d'],
            '12m' => [now()->subMonths(11)->startOfMonth(),'%Y-%m',   'Y-m'],
            default => [now()->subDays(29)->startOfDay(),  '%Y-%m-%d', 'Y-m-d'],
        };

        $rows = Transaction::where('type', 'commission')
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE_FORMAT(created_at, '{$groupFormat}') as period_key,
                         SUM(amount) as revenue,
                         COUNT(*) as transactions")
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');

        // Build complete zero-filled series
        $series  = [];
        $current = clone $startDate;
        $end     = now();

        while ($current <= $end) {
            $key = $current->format($labelFormat);

            $series[] = [
                'period'       => $key,
                'revenue'      => $rows->has($key) ? round((float) $rows[$key]->revenue, 2) : 0,
                'transactions' => $rows->has($key) ? (int) $rows[$key]->transactions : 0,
            ];

            $period === '12m' ? $current->addMonth() : $current->addDay();
        }

        return ApiResponse::success([
            'period'           => $period,
            'total_revenue'    => round(array_sum(array_column($series, 'revenue')), 2),
            'total_transactions'=> array_sum(array_column($series, 'transactions')),
            'series'           => $series,
        ]);
    }

    /**
     * Get the top earning sellers on the platform.
     *
     * GET /api/v1/admin/dashboard/top-sellers
     *
     * Query parameters:
     *   limit - number of sellers to return (default 10, max 50)
     */
    public function topSellers(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 10), 50);

        $sellers = SellerProfile::with('user:id,name,email,avatar_url,created_at')
            ->orderBy('total_earned', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($profile) => [
                'seller_id'    => $profile->user_id,
                'name'         => $profile->user->name,
                'email'        => $profile->user->email,
                'avatar_url'   => $profile->user->avatar_url,
                'store_name'   => $profile->store_name,
                'store_slug'   => $profile->store_slug,
                'total_earned' => (float) $profile->total_earned,
                'total_sales'  => $profile->total_sales,
                'member_since' => $profile->user->created_at,
            ]);

        return ApiResponse::success(['sellers' => $sellers]);
    }

    /**
     * List all users with search and filter capabilities.
     *
     * GET /api/v1/admin/users
     *
     * Query parameters:
     *   q        - search by name or email
     *   role     - buyer|seller|admin
     *   status   - active|suspended|banned
     *   per_page - results per page (default 20)
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('sellerProfile:user_id,store_name,store_slug,total_sales,total_earned')
            ->latest();

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate((int) $request->get('per_page', 20));

        $users->getCollection()->transform(fn($user) => [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'role'            => $user->role,
            'status'          => $user->status,
            'avatar_url'      => $user->avatar_url,
            'email_verified'  => !is_null($user->email_verified_at),
            'created_at'      => $user->created_at,
            'seller_profile'  => $user->sellerProfile ? [
                'store_name'   => $user->sellerProfile->store_name,
                'store_slug'   => $user->sellerProfile->store_slug,
                'total_sales'  => $user->sellerProfile->total_sales,
                'total_earned' => (float) $user->sellerProfile->total_earned,
            ] : null,
        ]);

        return ApiResponse::paginated($users, 'Users retrieved.');
    }

    /**
     * Update a user's account status.
     * Admins can suspend, ban, or reactivate users.
     * Admins cannot change other admin accounts.
     *
     * PATCH /api/v1/admin/users/{id}/status
     */
    public function updateUserStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:active,suspended,banned'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user = User::find($id);

        if (!$user) {
            return ApiResponse::notFound('User not found.');
        }

        // Prevent admins from modifying other admin accounts
        if ($user->role === 'admin') {
            return ApiResponse::error('Admin accounts cannot be modified.', 403);
        }

        // Prevent self-modification
        if ($user->id === $request->user()->id) {
            return ApiResponse::error('You cannot modify your own account status.', 403);
        }

        $oldStatus = $user->status;
        $user->update(['status' => $request->status]);

        return ApiResponse::success([
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'status'     => $user->status,
                'old_status' => $oldStatus,
            ],
        ], "User status updated to {$request->status}.");
    }

    /**
     * List all products for admin moderation.
     * Includes soft-deleted products.
     *
     * GET /api/v1/admin/products
     *
     * Query parameters:
     *   q        - search by title
     *   status   - published|draft|unpublished
     *   category - category slug
     *   seller   - seller user ID
     *   per_page - results per page (default 20)
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::withTrashed()
            ->with([
                'category:id,name,slug',
                'seller:id,name,email',
                'seller.sellerProfile:user_id,store_name,store_slug',
                'images' => fn($q) => $q->where('sort_order', 0),
            ])
            ->withCount('reviews')
            ->latest();

        if ($request->filled('q')) {
            $query->where('title', 'like', "%{$request->q}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->whereHas(
                'category',
                fn($q) => $q->where('slug', $request->category)
            );
        }

        if ($request->filled('seller')) {
            $query->where('seller_id', $request->seller);
        }

        $products = $query->paginate((int) $request->get('per_page', 20));

        $products->getCollection()->transform(fn($product) => [
            'id'             => $product->id,
            'title'          => $product->title,
            'slug'           => $product->slug,
            'status'         => $product->status,
            'price'          => $product->price,
            'sales_count'    => $product->sales_count,
            'review_count'   => $product->reviews_count,
            'average_rating' => $product->average_rating,
            'thumbnail'      => $product->images->first()?->url,
            'category'       => $product->category?->name,
            'deleted_at'     => $product->deleted_at,
            'seller'         => [
                'id'         => $product->seller->id,
                'name'       => $product->seller->name,
                'email'      => $product->seller->email,
                'store_name' => $product->seller->sellerProfile?->store_name,
                'store_slug' => $product->seller->sellerProfile?->store_slug,
            ],
            'created_at' => $product->created_at,
        ]);

        return ApiResponse::paginated($products, 'Products retrieved.');
    }

    /**
     * Force-unpublish a product or restore a deleted one.
     * Used for content moderation.
     *
     * PATCH /api/v1/admin/products/{id}/status
     */
    public function updateProductStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:published,unpublished'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $product = Product::withTrashed()->find($id);

        if (!$product) {
            return ApiResponse::notFound('Product not found.');
        }

        $product->update(['status' => $request->status]);

        // Restore a soft-deleted product if admin publishes it
        if ($request->status === 'published' && $product->deleted_at) {
            $product->restore();
        }

        return ApiResponse::success([
            'product' => [
                'id'     => $product->id,
                'title'  => $product->title,
                'status' => $product->status,
            ],
        ], "Product status updated to {$request->status}.");
    }
}