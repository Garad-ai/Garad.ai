<?php

namespace App\Extensions\AdvancedImage\System\Http\Controllers;

use App\Extensions\AdvancedImage\System\Services\AdvancedFreepikService;
use App\Extensions\AdvancedImage\System\Services\AdvancedNovitaService;
use App\Extensions\AdvancedImage\System\Services\FalAIService;
use App\Extensions\AdvancedImage\System\Services\NanoBananaService;
use App\Http\Controllers\Controller;
use App\Models\UserOpenai;
use Illuminate\Http\JsonResponse;

class AdvancedImageStatusController extends Controller
{
    private const PENDING_STATUSES = ['CREATED', 'IN_PROGRESS', 'IN_QUEUE'];

    public function __construct(
        private readonly AdvancedNovitaService $novitaService,
        private readonly AdvancedFreepikService $freepikService,
        private readonly FalAIService $falaiService,
        private readonly NanoBananaService $nanoBananaService
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $task = UserOpenai::findOrFail($id);

        if ($this->shouldCheckStatus($task)) {
            $task = match (data_get($task->payload, 'model')) {
                'freepik'          => $this->freepikService->checkStatus($task),
                'novita'           => $this->novitaService->checkStatus($task),
                'flux-pro/kontext', 'flux-2-flex', 'flux-2-flex/edit', 'xai/grok-imagine-image', 'xai/grok-imagine-image/edit' => $this->falaiService->checkStatus($task),
                'nano-banana/edit', 'nano-banana-pro/edit', 'nano-banana-2/edit' => $this->nanoBananaService->checkStatus($task),
                default            => $task,
            };
        }

        if ($task->output) {
            $task->output = $task->output_url;
        }

        return response()->json([
            'message' => __('Generated Successfully'),
            'status'  => 'success',
            'data'    => $task,
        ]);
    }

    private function shouldCheckStatus(UserOpenai $task): bool
    {
        return in_array($task->status, self::PENDING_STATUSES, true);
    }
}
