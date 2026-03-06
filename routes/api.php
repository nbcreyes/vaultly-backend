<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Seller\SellerApplicationController;
use App\Http\Controllers\Api\Admin\AdminSellerApplicationController;

/*
|--------------------------------------------------------------------------
| Vaultly API Routes
|--------------------------------------------------------------------------
|
| Middleware stack reference:
|   auth:sanctum    - requires valid Bearer token
|   verified.email  - requires email_verified_at to be set
|   active.account  - requires status to be active
|   role.admin      - requires role = admin
|   role.seller     - requires role = seller
|
*/

// Health check
Route::get('/health', [HealthController::class, 'index']);

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Public ping
    // -------------------------------------------------------------------------
    Route::get('/ping', function () {
        return \App\Http\Responses\ApiResponse::success(null, 'pong');
    });

    // -------------------------------------------------------------------------
    // Authentication routes (public)
    // -------------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register',            [AuthController::class, 'register']);
        Route::post('/verify-email',        [AuthController::class, 'verifyEmail']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        Route::post('/login',               [AuthController::class, 'login']);
        Route::post('/forgot-password',     [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',      [AuthController::class, 'resetPassword']);
    });

    // -------------------------------------------------------------------------
    // Authenticated routes — all users (buyer, seller, admin)
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'verified.email', 'active.account'])->group(function () {

        // Auth
        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // -------------------------------------------------------------------------
        // Seller application — buyer submits, any authenticated user can check status
        // -------------------------------------------------------------------------
        Route::prefix('seller')->group(function () {
            Route::post('/application',  [SellerApplicationController::class, 'store']);
            Route::get('/application',   [SellerApplicationController::class, 'show']);
        });

        // -------------------------------------------------------------------------
        // Admin routes
        // -------------------------------------------------------------------------
        Route::middleware('role.admin')->prefix('admin')->group(function () {

            // Seller applications
            Route::get('/seller-applications',               [AdminSellerApplicationController::class, 'index']);
            Route::get('/seller-applications/{id}',          [AdminSellerApplicationController::class, 'show']);
            Route::patch('/seller-applications/{id}/review', [AdminSellerApplicationController::class, 'review']);

        });

    });

});