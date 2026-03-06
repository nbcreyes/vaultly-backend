<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureEmailIsVerified
 *
 * Blocks access to protected routes if the authenticated user
 * has not yet verified their email address.
 *
 * Applied to all routes that require a verified account.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->email_verified_at) {
            return ApiResponse::error(
                'Please verify your email address to access this resource.',
                403
            );
        }

        return $next($request);
    }
}