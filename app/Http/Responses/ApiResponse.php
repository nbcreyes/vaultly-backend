<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse
 *
 * Central response factory for all API endpoints.
 * Every controller in the application must return responses
 * through this class to ensure a consistent JSON structure.
 *
 * Success envelope:
 * {
 *   "success": true,
 *   "message": "Human readable message",
 *   "data": { ... } or [ ... ] or null
 * }
 *
 * Error envelope:
 * {
 *   "success": false,
 *   "message": "Human readable error message",
 *   "errors": { ... } or null
 * }
 */
class ApiResponse
{
    /**
     * Return a successful JSON response.
     *
     * @param  mixed       $data    The payload to return to the client.
     * @param  string      $message A human-readable success message.
     * @param  int         $status  HTTP status code. Defaults to 200.
     * @return JsonResponse
     */
    public static function success(mixed $data = null, string $message = 'Request successful.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return a created (201) JSON response.
     *
     * @param  mixed       $data    The newly created resource.
     * @param  string      $message A human-readable success message.
     * @return JsonResponse
     */
    public static function created(mixed $data = null, string $message = 'Resource created successfully.'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string      $message A human-readable error message.
     * @param  int         $status  HTTP status code. Defaults to 400.
     * @param  mixed       $errors  Optional structured error details.
     * @return JsonResponse
     */
    public static function error(string $message = 'An error occurred.', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Return a validation error JSON response.
     * Always returns HTTP 422.
     *
     * @param  array       $errors  The validation errors keyed by field name.
     * @param  string      $message A human-readable message.
     * @return JsonResponse
     */
    public static function validationError(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }

    /**
     * Return a not found error JSON response.
     * Always returns HTTP 404.
     *
     * @param  string      $message A human-readable message.
     * @return JsonResponse
     */
    public static function notFound(string $message = 'The requested resource was not found.'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * Return an unauthorized error JSON response.
     * Always returns HTTP 401.
     *
     * @param  string      $message A human-readable message.
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * Return a forbidden error JSON response.
     * Always returns HTTP 403.
     *
     * @param  string      $message A human-readable message.
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * Return a paginated JSON response.
     * Wraps Laravel's paginator output in the standard envelope.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param  string      $message A human-readable message.
     * @return JsonResponse
     */
    public static function paginated(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, string $message = 'Request successful.'): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'message'  => $message,
            'data'     => $paginator->items(),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links'    => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], 200);
    }
}