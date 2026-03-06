<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;

/*
|--------------------------------------------------------------------------
| Vaultly API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api automatically by Laravel.
| Authentication is handled via Laravel Sanctum bearer tokens.
|
| Route groups are organized in this order:
|   1. Public routes (no authentication required)
|   2. Auth routes (register, login, etc.)
|   3. Authenticated buyer routes
|   4. Authenticated seller routes
|   5. Authenticated admin routes
|
*/

// Health check — used by Railway and monitoring tools
Route::get('/health', [HealthController::class, 'index']);

// API version prefix for all application routes
Route::prefix('v1')->group(function () {

    // Placeholder — routes will be added in subsequent steps
    Route::get('/ping', function () {
        return \App\Http\Responses\ApiResponse::success(null, 'pong');
    });

});