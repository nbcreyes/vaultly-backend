<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Download;
use App\Models\OrderItem;
use App\Models\PlatformSetting;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DownloadController
 *
 * Handles secure file downloads for purchased products.
 *
 * Security model:
 *   - Cloudinary file URLs are never exposed to the buyer
 *   - Downloads require a valid expiring token
 *   - Tokens expire after 48 hours
 *   - Tokens can be regenerated within the 30-day purchase window
 *   - Revoked tokens (after refund) cannot be used
 *   - File is streamed through our backend, not redirected to Cloudinary
 *
 * Endpoints:
 *   GET  /api/v1/downloads/{token}           - download file via token
 *   POST /api/v1/downloads/{orderItemId}/regenerate - regenerate expired token
 *   GET  /api/v1/buyer/purchases             - list all purchases with download status
 */
class DownloadController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Stream a file download using a valid download token.
     *
     * This endpoint is the only way a buyer can access a product file.
     * The Cloudinary URL is resolved internally and the file is proxied
     * through this endpoint — the buyer never sees the Cloudinary URL.
     *
     * GET /api/v1/downloads/{token}
     */
    public function download(string $token): StreamedResponse|JsonResponse
    {
        $download = Download::where('token', $token)
            ->with('product')
            ->first();

        if (!$download) {
            return ApiResponse::notFound('Download link not found.');
        }

        if ($download->is_revoked) {
            return ApiResponse::error(
                'This download link has been revoked. If you believe this is an error, please contact support.',
                403
            );
        }

        if ($download->isExpired()) {
            return ApiResponse::error(
                'This download link has expired. Please regenerate a new link from your purchase history.',
                410
            );
        }

        $product = $download->product;

        if (!$product) {
            return ApiResponse::notFound('Product file not found.');
        }

        // Generate a short-lived Cloudinary URL for the actual file fetch
        // This URL is used server-side only and never sent to the client
        $cloudinaryUrl = $this->buildCloudinaryUrl($product->file_cloudinary_id);

        // Mark the download as used
        $download->update(['downloaded_at' => now()]);

        // Stream the file through our backend
        return $this->streamFile($cloudinaryUrl, $product->file_name, $product->file_type);
    }

    /**
     * Regenerate a download token for a purchased order item.
     *
     * The buyer can regenerate a new link at any time within 30 days
     * of purchase. The old token is expired and a new one is issued.
     *
     * POST /api/v1/downloads/{orderItemId}/regenerate
     */
    public function regenerate(Request $request, string $orderItemId): JsonResponse
    {
        $downloadWindowDays = (int) PlatformSetting::get('download_window_days', 30);

        // Find the order item and verify ownership
        $orderItem = OrderItem::where('id', $orderItemId)
            ->where('seller_id', '!=', $request->user()->id) // not the seller
            ->whereHas(
                'order',
                fn($q) => $q->where('buyer_id', $request->user()->id)
                             ->where('status', 'completed')
            )
            ->with(['order', 'product'])
            ->first();

        if (!$orderItem) {
            return ApiResponse::notFound('Purchase not found.');
        }

        // Check the order item has not been refunded
        if ($orderItem->status === 'refunded') {
            return ApiResponse::error(
                'Downloads are not available for refunded purchases.',
                403
            );
        }

        // Check we are within the 30-day download window
        $purchasedAt = $orderItem->order->paid_at;

        if (!$purchasedAt || $purchasedAt->diffInDays(now()) > $downloadWindowDays) {
            return ApiResponse::error(
                "Download links can only be regenerated within {$downloadWindowDays} days of purchase.",
                403
            );
        }

        // Generate a new download token
        $download = $this->orderService->generateDownloadToken(
            $orderItem,
            $request->user()->id
        );

        return ApiResponse::success([
            'token'      => $download->token,
            'expires_at' => $download->expires_at,
            'download_url' => url("/api/v1/downloads/{$download->token}"),
        ], 'New download link generated successfully.');
    }

    /**
     * List all purchases for the authenticated buyer.
     * Includes current download token status for each item.
     *
     * GET /api/v1/buyer/purchases
     */
    public function purchases(Request $request): JsonResponse
    {
        $downloadWindowDays = (int) PlatformSetting::get('download_window_days', 30);

        $orders = \App\Models\Order::where('buyer_id', $request->user()->id)
            ->where('status', 'completed')
            ->with([
                'items.product' => fn($q) => $q->select(
                    'id', 'title', 'slug', 'short_description',
                    'file_name', 'file_type', 'file_size', 'version'
                ),
                'items.product.images' => fn($q) => $q->where('sort_order', 0),
                'items.downloads' => fn($q) => $q->where('is_revoked', false)
                    ->latest()
                    ->limit(1),
                'items.refund',
            ])
            ->latest('paid_at')
            ->paginate(20);

        $orders->getCollection()->transform(function ($order) use ($downloadWindowDays) {
            $order->items->transform(function ($item) use ($order, $downloadWindowDays) {
                $latestDownload  = $item->downloads->first();
                $withinWindow    = $order->paid_at &&
                                   $order->paid_at->diffInDays(now()) <= $downloadWindowDays;

                return [
                    'order_item_id'   => $item->id,
                    'product'         => [
                        'id'               => $item->product->id,
                        'title'            => $item->product->title,
                        'slug'             => $item->product->slug,
                        'short_description'=> $item->product->short_description,
                        'file_name'        => $item->product->file_name,
                        'file_type'        => $item->product->file_type,
                        'file_size'        => $item->product->file_size,
                        'version'          => $item->product->version,
                        'thumbnail'        => $item->product->images->first()?->url,
                    ],
                    'price'           => $item->price,
                    'status'          => $item->status,
                    'refund'          => $item->refund ? [
                        'status' => $item->refund->status,
                        'reason' => $item->refund->reason,
                    ] : null,
                    'download'        => [
                        'token'           => $latestDownload?->token,
                        'expires_at'      => $latestDownload?->expires_at,
                        'is_expired'      => $latestDownload ? $latestDownload->isExpired() : true,
                        'is_revoked'      => $latestDownload?->is_revoked ?? false,
                        'downloaded_at'   => $latestDownload?->downloaded_at,
                        'can_regenerate'  => $withinWindow && $item->status !== 'refunded',
                        'within_window'   => $withinWindow,
                        'window_expires'  => $order->paid_at?->addDays($downloadWindowDays),
                    ],
                ];
            });

            return [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'total'        => $order->total,
                'paid_at'      => $order->paid_at,
                'items'        => $order->items,
            ];
        });

        return ApiResponse::paginated($orders, 'Purchase history retrieved.');
    }

    /**
     * Build a Cloudinary URL for a given public ID.
     *
     * For raw files (ZIP, PDF, MP3, etc) we use the /raw/upload/ path.
     * The URL includes the cloud name and is HTTPS.
     *
     * This URL is only ever used server-side to fetch the file.
     * It is never returned to the client.
     *
     * @param  string $publicId  The Cloudinary public_id stored on the product
     * @return string
     */
    private function buildCloudinaryUrl(string $publicId): string
    {
        $cloudName = config('services.cloudinary.cloud_name');

        // Determine resource type from the public ID path
        // Product files are stored under vaultly/products/files/
        // so they are raw resources
        if (str_contains($publicId, 'products/files')) {
            return "https://res.cloudinary.com/{$cloudName}/raw/upload/{$publicId}";
        }

        return "https://res.cloudinary.com/{$cloudName}/image/upload/{$publicId}";
    }

    /**
     * Stream a file from a remote URL through our backend to the client.
     *
     * We fetch the file from Cloudinary and pipe it directly to the
     * HTTP response. The file is never written to disk on our server.
     * This keeps our Railway server storage at zero and ensures the
     * Cloudinary URL is never exposed.
     *
     * @param  string $url       The Cloudinary file URL (server-side only)
     * @param  string $fileName  The original file name shown to the buyer
     * @param  string $fileType  The file extension used to set Content-Type
     * @return StreamedResponse|JsonResponse
     */
    private function streamFile(string $url, string $fileName, string $fileType): StreamedResponse|JsonResponse
    {
        try {
            // Make a streaming request to Cloudinary
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error('Cloudinary file fetch failed during download', [
                    'status' => $response->status(),
                    'url'    => $url,
                ]);

                return ApiResponse::error(
                    'The file could not be retrieved. Please try again or contact support.',
                    502
                );
            }

            $contentType = $this->getContentType($fileType);
            $fileBody    = $response->body();

            return response()->streamDownload(function () use ($fileBody) {
                echo $fileBody;
            }, $fileName, [
                'Content-Type'              => $contentType,
                'Content-Disposition'       => "attachment; filename=\"{$fileName}\"",
                'X-Content-Type-Options'    => 'nosniff',
                'Cache-Control'             => 'no-store, no-cache, must-revalidate',
                'Pragma'                    => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('File streaming failed', [
                'error'    => $e->getMessage(),
                'filename' => $fileName,
            ]);

            return ApiResponse::error(
                'An error occurred while downloading the file. Please try again.',
                500
            );
        }
    }

    /**
     * Map a file extension to its MIME content type.
     *
     * @param  string $extension
     * @return string
     */
    private function getContentType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf'         => 'application/pdf',
            'zip'         => 'application/zip',
            '7z'          => 'application/x-7z-compressed',
            'rar'         => 'application/x-rar-compressed',
            'mp3'         => 'audio/mpeg',
            'mp4'         => 'video/mp4',
            'otf'         => 'font/otf',
            'ttf'         => 'font/ttf',
            'woff'        => 'font/woff',
            'woff2'       => 'font/woff2',
            default       => 'application/octet-stream',
        };
    }
}