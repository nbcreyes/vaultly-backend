<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * HealthController
 *
 * Provides a simple endpoint to verify the API is running.
 * Used by deployment platforms and monitoring tools.
 */
class HealthController extends Controller
{
    /**
     * Return the API health status.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'api'     => 'Vaultly API',
            'version' => '1.0.0',
            'status'  => 'operational',
        ], 'API is operational.');
    }
}