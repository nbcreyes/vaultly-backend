<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureAccountIsActive
 *
 * Blocks access to protected routes if the authenticated user's
 * account has been suspended or banned by an admin.
 *
 * Applied to all routes that require an active account.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isBanned()) {
            return ApiResponse::error('Your account has been permanently suspended.', 403);
        }

        if ($user?->status === 'suspended') {
            return ApiResponse::error(
                'Your account has been temporarily suspended. Please contact support.',
                403
            );
        }

        return $next($request);
    }
}