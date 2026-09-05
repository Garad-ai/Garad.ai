<?php

namespace App\Extensions\AdvancedImage\System\Http\Controllers;

use App\Extensions\AdvancedImage\System\Services\AdvancedFreepikService;
use App\Extensions\AdvancedImage\System\Services\AdvancedNovitaService;
use App\Extensions\AdvancedImage\System\Services\ClipDropService;
use App\Http\Controllers\Controller;
use App\Models\UserOpenai;
use Illuminate\Http\Request;

class AdvancedImageWebhookController extends Controller
{
    public function __construct(
        public AdvancedNovitaService $novitaService,
        public ClipDropService $clipDropService,
        public AdvancedFreepikService $freepikService
    ) {}

    public function __invoke(Request $request, string $model = 'freepik'): void
    {
        $taskId = match ($model) {
            'freepik' => $request->get('request_id'),
            'novita'  => $request->input('payload.task.task_id'),
            default   => null,
        };

        /**
         * @var UserOpenai $task
         */
        $task = UserOpenai::query()
            ->where('request_id', $taskId)
            ->firstOrFail();

        if ($model === 'freepik') {
            $this->freepikService->webhook($task, $request->all());
        }
        if ($model === 'novita') {
            $this->novitaService->webhook($task, $request->input('payload.images') ?: []);
        }
    }
}
