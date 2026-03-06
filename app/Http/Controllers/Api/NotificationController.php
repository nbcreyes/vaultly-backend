<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NotificationController
 *
 * Serves in-app notifications for the authenticated user.
 *
 * Endpoints:
 *   GET  /api/v1/notifications          - list notifications
 *   POST /api/v1/notifications/read-all - mark all as read
 *   POST /api/v1/notifications/{id}/read - mark one as read
 *   GET  /api/v1/notifications/unread-count - fast unread count for bell badge
 */
class NotificationController extends Controller
{
    /**
     * List the authenticated user's notifications.
     * Returns newest first, paginated.
     *
     * GET /api/v1/notifications
     *
     * Query parameters:
     *   unread_only - true|false (default false)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->latest();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20);

        $notifications->getCollection()->transform(fn($n) => [
            'id'         => $n->id,
            'type'       => $n->type,
            'title'      => $n->title,
            'body'       => $n->body,
            'data'       => $n->data,
            'read_at'    => $n->read_at,
            'is_read'    => !is_null($n->read_at),
            'created_at' => $n->created_at,
        ]);

        return ApiResponse::paginated($notifications, 'Notifications retrieved.');
    }

    /**
     * Get the unread notification count for the bell badge.
     * Lightweight endpoint meant to be polled frequently.
     *
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread_count' => $count]);
    }

    /**
     * Mark a single notification as read.
     *
     * POST /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$notification) {
            return ApiResponse::notFound('Notification not found.');
        }

        if ($notification->read_at) {
            return ApiResponse::success(null, 'Notification already read.');
        }

        $notification->update(['read_at' => now()]);

        return ApiResponse::success(null, 'Notification marked as read.');
    }

    /**
     * Mark all unread notifications as read.
     *
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success([
            'marked_read' => $count,
        ], "{$count} notification(s) marked as read.");
    }
}