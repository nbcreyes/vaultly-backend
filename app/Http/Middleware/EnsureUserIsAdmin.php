<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserIsAdmin
 *
 * Restricts access to admin-only routes.
 * Applied to all /api/v1/admin/* routes.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isAdmin()) {
            return ApiResponse::forbidden('This area is restricted to administrators.');
        }

        return $next($request);
    }
}