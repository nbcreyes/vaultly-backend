<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MessageController
 *
 * Handles order-scoped messaging between buyers and sellers.
 *
 * Every conversation is tied to a specific order.
 * Only the buyer and the seller(s) involved in that order
 * can participate in its conversation thread.
 *
 * Endpoints:
 *   GET  /api/v1/messages                        - list all conversations
 *   GET  /api/v1/messages/order/{orderId}         - get full thread for an order
 *   POST /api/v1/messages/order/{orderId}         - send a message in an order thread
 *   POST /api/v1/messages/order/{orderId}/read    - mark all messages as read
 */
class MessageController extends Controller
{
    /**
     * List all conversations for the authenticated user.
     *
     * Returns the most recent message per order, grouped by order.
     * Used to render the inbox list view.
     *
     * GET /api/v1/messages
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Get all orders this user is involved in that have messages
        $threads = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('recipient_id', $userId);
            })
            ->with([
                'order:id,order_number,buyer_id',
                'order.buyer:id,name,avatar_url',
                'sender:id,name,avatar_url',
                'recipient:id,name,avatar_url',
            ])
            ->selectRaw('*, MAX(created_at) as latest_at')
            ->groupBy('order_id')
            ->orderByDesc('latest_at')
            ->get();

        // For each thread get the unread count for this user
        $formatted = $threads->map(function ($message) use ($userId) {
            $unreadCount = Message::where('order_id', $message->order_id)
                ->where('recipient_id', $userId)
                ->whereNull('read_at')
                ->count();

            // Determine the other party in the conversation
            $otherParty = $message->sender_id === $userId
                ? $message->recipient
                : $message->sender;

            return [
                'order_id'     => $message->order_id,
                'order_number' => $message->order->order_number,
                'other_party'  => [
                    'id'         => $otherParty->id,
                    'name'       => $otherParty->name,
                    'avatar_url' => $otherParty->avatar_url,
                ],
                'last_message' => [
                    'body'       => $message->body,
                    'sent_at'    => $message->created_at,
                    'is_mine'    => $message->sender_id === $userId,
                ],
                'unread_count' => $unreadCount,
            ];
        });

        return ApiResponse::success([
            'threads'       => $formatted,
            'total_unread'  => $formatted->sum('unread_count'),
        ]);
    }

    /**
     * Get the full message thread for a specific order.
     *
     * Both the buyer and any seller involved in the order
     * can access this thread.
     *
     * GET /api/v1/messages/order/{orderId}
     */
    public function thread(Request $request, string $orderId): JsonResponse
    {
        $user  = $request->user();
        $order = $this->resolveOrder($orderId, $user->id);

        if (!$order) {
            return ApiResponse::notFound('Order not found or you do not have access to this conversation.');
        }

        $messages = Message::where('order_id', $orderId)
            ->with([
                'sender:id,name,avatar_url',
            ])
            ->oldest()
            ->get()
            ->map(fn($message) => [
                'id'         => $message->id,
                'body'       => $message->body,
                'is_mine'    => $message->sender_id === $user->id,
                'read_at'    => $message->read_at,
                'sent_at'    => $message->created_at,
                'sender'     => [
                    'id'         => $message->sender->id,
                    'name'       => $message->sender->name,
                    'avatar_url' => $message->sender->avatar_url,
                ],
            ]);

        return ApiResponse::success([
            'order_id'     => (int) $orderId,
            'order_number' => $order->order_number,
            'messages'     => $messages,
        ]);
    }

    /**
     * Send a message in an order thread.
     *
     * The recipient is determined automatically:
     *   - If sender is the buyer, recipient is the seller of the first item
     *   - If sender is the seller, recipient is the buyer
     *
     * POST /api/v1/messages/order/{orderId}
     */
    public function send(SendMessageRequest $request, string $orderId): JsonResponse
    {
        $user  = $request->user();
        $order = $this->resolveOrder($orderId, $user->id);

        if (!$order) {
            return ApiResponse::notFound('Order not found or you do not have access to this conversation.');
        }

        $order->load('items:id,order_id,seller_id');

        // Determine the recipient
        if ($order->buyer_id === $user->id) {
            // Sender is the buyer — recipient is the seller of the first item
            $recipientId = $order->items->first()?->seller_id;
        } else {
            // Sender is a seller — recipient is the buyer
            $recipientId = $order->buyer_id;
        }

        if (!$recipientId) {
            return ApiResponse::error('Could not determine message recipient.', 422);
        }

        $message = Message::create([
            'order_id'     => $order->id,
            'sender_id'    => $user->id,
            'recipient_id' => $recipientId,
            'body'         => $request->body,
        ]);

        $message->load('sender:id,name,avatar_url');

        return ApiResponse::created([
            'message' => [
                'id'         => $message->id,
                'body'       => $message->body,
                'is_mine'    => true,
                'read_at'    => null,
                'sent_at'    => $message->created_at,
                'sender'     => [
                    'id'         => $message->sender->id,
                    'name'       => $message->sender->name,
                    'avatar_url' => $message->sender->avatar_url,
                ],
            ],
        ], 'Message sent.');
    }

    /**
     * Mark all unread messages in an order thread as read.
     * Only marks messages where the current user is the recipient.
     *
     * POST /api/v1/messages/order/{orderId}/read
     */
    public function markRead(Request $request, string $orderId): JsonResponse
    {
        $user  = $request->user();
        $order = $this->resolveOrder($orderId, $user->id);

        if (!$order) {
            return ApiResponse::notFound('Order not found.');
        }

        $count = Message::where('order_id', $orderId)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success([
            'marked_read' => $count,
        ], "{$count} message(s) marked as read.");
    }

    /**
     * Resolve an order that the given user has access to.
     *
     * A user has access if they are the buyer OR a seller
     * of at least one item in the order.
     *
     * @param  string $orderId
     * @param  int    $userId
     * @return Order|null
     */
    private function resolveOrder(string $orderId, int $userId): ?Order
    {
        return Order::where('id', $orderId)
            ->where('status', 'completed')
            ->where(function ($q) use ($userId) {
                $q->where('buyer_id', $userId)
                  ->orWhereHas(
                      'items',
                      fn($q) => $q->where('seller_id', $userId)
                  );
            })
            ->first();
    }
}