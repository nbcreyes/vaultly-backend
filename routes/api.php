<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Auth\AuthController;

/*
|--------------------------------------------------------------------------
| Vaultly API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api automatically by Laravel.
| All application routes are nested under the /v1 prefix.
|
| Middleware stack used on protected routes:
|   auth:sanctum    - requires valid Bearer token
|   verified.email  - requires email_verified_at to be set
|   active.account  - requires status to be active
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
    // Authentication routes (public — no token required)
    // -------------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register',             [AuthController::class, 'register']);
        Route::post('/verify-email',         [AuthController::class, 'verifyEmail']);
        Route::post('/resend-verification',  [AuthController::class, 'resendVerification']);
        Route::post('/login',                [AuthController::class, 'login']);
        Route::post('/forgot-password',      [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',       [AuthController::class, 'resetPassword']);
    });

    // -------------------------------------------------------------------------
    // Authenticated routes — requires valid token, verified email, active account
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'verified.email', 'active.account'])->group(function () {

        // Auth
        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Additional authenticated routes will be added in subsequent steps

    });

});