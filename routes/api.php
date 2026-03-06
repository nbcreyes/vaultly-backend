<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Browse\BrowseController;
use App\Http\Controllers\Api\Buyer\BuyerRefundController;
use App\Http\Controllers\Api\Buyer\CheckoutController;
use App\Http\Controllers\Api\Buyer\DownloadController;
use App\Http\Controllers\Api\Buyer\ReviewController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Seller\SellerApplicationController;
use App\Http\Controllers\Api\Seller\SellerDashboardController;
use App\Http\Controllers\Api\Seller\SellerPayoutController;
use App\Http\Controllers\Api\Seller\SellerStoreController;
use App\Http\Controllers\Api\Seller\SellerProductController;
use App\Http\Controllers\Api\Admin\AdminRefundController;
use App\Http\Controllers\Api\Admin\AdminSellerApplicationController;
use App\Http\Controllers\Api\Admin\AdminPayoutController;
use App\Http\Controllers\Api\Webhook\PayPalWebhookController;

/*
|--------------------------------------------------------------------------
| Vaultly API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', [HealthController::class, 'index']);

Route::prefix('v1')->group(function () {

    Route::get('/ping', function () {
        return \App\Http\Responses\ApiResponse::success(null, 'pong');
    });

    Route::post('/payments/webhook', [PayPalWebhookController::class, 'handle']);
    Route::get('/downloads/{token}', [DownloadController::class, 'download']);

    Route::prefix('browse')->group(function () {
        Route::get('/featured',                   [BrowseController::class, 'featured']);
        Route::get('/categories',                 [BrowseController::class, 'categories']);
        Route::get('/categories/{slug}/products', [BrowseController::class, 'categoryProducts']);
        Route::get('/products',                   [BrowseController::class, 'products']);
        Route::get('/products/{slug}',            [BrowseController::class, 'productDetail']);
        Route::get('/products/{slug}/reviews',    [ReviewController::class, 'index']);
        Route::get('/stores/{slug}',              [BrowseController::class, 'store']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register',            [AuthController::class, 'register']);
        Route::post('/verify-email',        [AuthController::class, 'verifyEmail']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        Route::post('/login',               [AuthController::class, 'login']);
        Route::post('/forgot-password',     [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',      [AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:sanctum', 'verified.email', 'active.account'])->group(function () {

        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Notifications
        Route::get('/notifications',              [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all',    [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read',   [NotificationController::class, 'markRead']);

        // Checkout
        Route::prefix('checkout')->group(function () {
            Route::post('/create',  [CheckoutController::class, 'create']);
            Route::post('/capture', [CheckoutController::class, 'capture']);
        });

        // Buyer
        Route::prefix('buyer')->group(function () {
            Route::get('/purchases',                           [DownloadController::class, 'purchases']);
            Route::post('/downloads/{orderItemId}/regenerate', [DownloadController::class, 'regenerate']);
            Route::get('/refunds',                             [BuyerRefundController::class, 'index']);
            Route::post('/refunds',                            [BuyerRefundController::class, 'store']);
        });

        // Reviews
        Route::post('/reviews',             [ReviewController::class, 'store']);
        Route::delete('/reviews/{id}',      [ReviewController::class, 'destroy']);
        Route::patch('/reviews/{id}/reply', [ReviewController::class, 'reply']);

        // Messaging
        Route::prefix('messages')->group(function () {
            Route::get('/',                      [MessageController::class, 'index']);
            Route::get('/order/{orderId}',       [MessageController::class, 'thread']);
            Route::post('/order/{orderId}',      [MessageController::class, 'send']);
            Route::post('/order/{orderId}/read', [MessageController::class, 'markRead']);
        });

        // Seller application
        Route::prefix('seller')->group(function () {
            Route::post('/application', [SellerApplicationController::class, 'store']);
            Route::get('/application',  [SellerApplicationController::class, 'show']);
        });

        // Seller — approved only
        Route::middleware('role.seller')->prefix('seller')->group(function () {

            Route::get('/store',           [SellerStoreController::class, 'show']);
            Route::patch('/store',         [SellerStoreController::class, 'update']);
            Route::post('/store/logo',     [SellerStoreController::class, 'uploadLogo']);
            Route::delete('/store/logo',   [SellerStoreController::class, 'deleteLogo']);
            Route::post('/store/banner',   [SellerStoreController::class, 'uploadBanner']);
            Route::delete('/store/banner', [SellerStoreController::class, 'deleteBanner']);

            Route::get('/products',                          [SellerProductController::class, 'index']);
            Route::post('/products',                         [SellerProductController::class, 'store']);
            Route::get('/products/{id}',                     [SellerProductController::class, 'show']);
            Route::patch('/products/{id}',                   [SellerProductController::class, 'update']);
            Route::post('/products/{id}/publish',            [SellerProductController::class, 'publish']);
            Route::post('/products/{id}/unpublish',          [SellerProductController::class, 'unpublish']);
            Route::delete('/products/{id}',                  [SellerProductController::class, 'destroy']);
            Route::post('/products/{id}/images',             [SellerProductController::class, 'addImages']);
            Route::delete('/products/{id}/images/{imageId}', [SellerProductController::class, 'deleteImage']);

            Route::prefix('dashboard')->group(function () {
                Route::get('/summary',      [SellerDashboardController::class, 'summary']);
                Route::get('/sales',        [SellerDashboardController::class, 'sales']);
                Route::get('/revenue',      [SellerDashboardController::class, 'revenue']);
                Route::get('/top-products', [SellerDashboardController::class, 'topProducts']);
                Route::get('/transactions', [SellerDashboardController::class, 'transactions']);
            });

            Route::get('/payouts',  [SellerPayoutController::class, 'index']);
            Route::post('/payouts', [SellerPayoutController::class, 'store']);

        });

        // Admin
        Route::middleware('role.admin')->prefix('admin')->group(function () {

            Route::get('/seller-applications',               [AdminSellerApplicationController::class, 'index']);
            Route::get('/seller-applications/{id}',          [AdminSellerApplicationController::class, 'show']);
            Route::patch('/seller-applications/{id}/review', [AdminSellerApplicationController::class, 'review']);

            Route::get('/payouts',                [AdminPayoutController::class, 'index']);
            Route::get('/payouts/{id}',           [AdminPayoutController::class, 'show']);
            Route::patch('/payouts/{id}/process', [AdminPayoutController::class, 'process']);

            Route::get('/refunds',                [AdminRefundController::class, 'index']);
            Route::get('/refunds/{id}',           [AdminRefundController::class, 'show']);
            Route::patch('/refunds/{id}/process', [AdminRefundController::class, 'process']);

        });

    });

});