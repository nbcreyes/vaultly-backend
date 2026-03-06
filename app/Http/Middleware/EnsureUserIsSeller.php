<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserIsSeller
 *
 * Restricts access to seller-only routes.
 * Applied to all /api/v1/seller/* routes that require an approved seller.
 */
class EnsureUserIsSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isSeller()) {
            return ApiResponse::forbidden('This area is restricted to approved sellers.');
        }

        return $next($request);
    }
}