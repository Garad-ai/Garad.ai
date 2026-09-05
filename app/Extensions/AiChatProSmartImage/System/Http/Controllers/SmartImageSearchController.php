<?php

declare(strict_types=1);

namespace App\Extensions\AiChatProSmartImage\System\Http\Controllers;

use App\Extensions\AiChatProSmartImage\System\Services\ImageSearchService;
use App\Extensions\AiChatProSmartImage\System\Services\SmartImageService;
use App\Models\SettingTwo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmartImageSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query'      => 'required|string|max:300',
            'message_id' => 'nullable|integer',
        ]);

        $query = $request->input('query');
        $messageId = $request->input('message_id');
        $maxImages = SmartImageService::getMaxImages();
        $provider = setting('default_realtime', 'serper');

        try {
            $searchService = new ImageSearchService;
            $images = $searchService->search($query, $maxImages);

            // If primary provider returned empty, try serper as fallback
            if (empty($images) && $provider !== 'serper') {
                $serperKey = SettingTwo::getCache()->serper_api_key ?? null;
                if ($serperKey) {
                    $fallbackResponse = Http::withHeaders([
                        'X-API-KEY'    => $serperKey,
                        'Content-Type' => 'application/json',
                    ])->connectTimeout(300)->timeout(300)->post('https://google.serper.dev/images', [
                        'q'   => $query,
                        'num' => $maxImages,
                    ]);

                    if ($fallbackResponse->successful()) {
                        $images = collect($fallbackResponse->json('images', []))
                            ->take($maxImages)
                            ->map(fn (array $img): array => [
                                'title'        => $img['title'] ?? '',
                                'imageUrl'     => $img['imageUrl'] ?? '',
                                'thumbnailUrl' => $img['thumbnailUrl'] ?? $img['imageUrl'] ?? '',
                                'source'       => $img['source'] ?? '',
                                'domain'       => $img['domain'] ?? '',
                                'link'         => $img['link'] ?? '',
                                'imageWidth'   => $img['imageWidth'] ?? 0,
                                'imageHeight'  => $img['imageHeight'] ?? 0,
                            ])
                            ->filter(fn (array $img): bool => ! empty($img['imageUrl']))
                            ->values()
                            ->toArray();
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('[SmartImage] Async image search error', [
                'error' => $e->getMessage(),
            ]);
            $images = [];
        }

        // Persist to DB if message_id provided
        if ($messageId && ! empty($images)) {
            SmartImageService::saveForMessage(
                (int) $messageId,
                (int) Auth::id(),
                $query,
                $images
            );
        }

        return response()->json(['images' => $images]);
    }
}
