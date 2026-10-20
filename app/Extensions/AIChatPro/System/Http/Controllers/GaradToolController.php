<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Services\GaradImageToolService;
use App\Extensions\AIChatPro\System\Services\GaradPresentationToolService;
use App\Extensions\AIChatPro\System\Services\GaradVideoToolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class GaradToolController extends Controller
{
    public function presentationStatus(string $generationId, GaradPresentationToolService $service): JsonResponse
    {
        return $this->safe(fn () => $service->statusPayload($generationId));
    }

    public function videoStatus(int $videoId, GaradVideoToolService $service): JsonResponse
    {
        return $this->safe(fn () => $service->statusPayload($videoId));
    }

    public function imageStatus(int $recordId, GaradImageToolService $service): JsonResponse
    {
        return $this->safe(fn () => ['marker' => $service->status(['record_id' => $recordId])]);
    }

    private function safe(callable $callback): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $callback()]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => __('Unable to update the tool status right now.')], 500);
        }
    }
}
