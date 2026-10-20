<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Services\GaradPresentationToolServiceV5;
use App\Extensions\AIChatPro\System\Services\GaradVideoToolServiceV5;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Throwable;

final class GaradToolStatusController extends Controller
{
    public function video(int $videoId): JsonResponse
    {
        try {
            $message = app(GaradVideoToolServiceV5::class)->status(['video_id' => $videoId]);
            return response()->json(['ok' => true, 'message' => $message]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function presentation(string $generationId): JsonResponse
    {
        try {
            $message = app(GaradPresentationToolServiceV5::class)->status(['generation_id' => $generationId]);
            return response()->json(['ok' => true, 'message' => $message]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
