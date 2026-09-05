<?php

namespace App\Extensions\AiViralClips\System\Http\Controllers;

use App\Concerns\HasErrorResponse;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\AiInfluencer\VideoStatusEnum;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\ExportedVideo;
use App\Packages\Klap\KlapService;
use App\Packages\Klap\Requests\VideoToShortSubmitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AiViralKlapController extends Controller
{
    use HasErrorResponse;

    public KlapService $service;

    public function __construct()
    {
        $this->service = new KlapService(ApiHelper::setKlapApiKey());
    }

    // submit the task to generate shorts.
    public function generateShorts(VideoToShortSubmitRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $validated = $request->validated();
        $driver = Entity::driver(EntityEnum::AI_CLIP_KLAP)->inputVideoCount(1)->calculateCredit();
        $driver->redirectIfNoCreditBalance();

        try {
            if (isset($validated['file'])) {
                $uploadDisk = 'uploads';
                $rootDir = 'ai-viral-clips';

                $folderPath = public_path('uploads/ai-viral-clips');
                if (! file_exists($folderPath)) {
                    if (! mkdir($folderPath, 755, true) && ! is_dir($folderPath)) {
                        throw new RuntimeException(sprintf('Directory "%s" was not created', $folderPath));
                    }
                }

                $path = $validated['file']->store($rootDir, ['disk' => $uploadDisk]);
                $validated['source_video_url'] = Storage::disk($uploadDisk)->url($path);
            }

            unset($validated['file']);

            // convert data types from string to accurate type
            $validated['max_duration'] = (int) $validated['max_duration'];
            $validated['target_clip_count'] = (int) $validated['target_clip_count'];
            $validated['editing_options']['captions'] = filter_var($validated['editing_options']['captions'], FILTER_VALIDATE_BOOLEAN);
            $validated['editing_options']['intro_title'] = filter_var($validated['editing_options']['intro_title'], FILTER_VALIDATE_BOOLEAN);
            $validated['editing_options']['emojis'] = filter_var($validated['editing_options']['emojis'], FILTER_VALIDATE_BOOLEAN);

            return $this->service->generateShorts()->submitTask($validated);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'error happen while generate short clips using klap');
        }

    }

    // check the task status
    public function checkTaskStatus(string $taskId)
    {
        return $this->service->generateShorts()->checkTask($taskId);
    }

    // get list of preview
    public function previewLists(string $folderId)
    {
        try {
            return $this->service->generateShorts()->getListGeneratedShorts($folderId);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while fetch preview lists');
        }
    }

    // export the clips
    public function exportClips(Request $request)
    {
        $validated = $request->validate([
            'folder_id'   => 'required|string',
            'project_ids' => 'required|array',
        ]);

        $requestIds = [];

        $driver = Entity::driver(EntityEnum::AI_CLIP_KLAP)->inputVideoCount(count($validated['project_ids']))->calculateCredit();
        $driver->redirectIfNoCreditBalance();
        $driver->decreaseCredit();

        try {
            foreach ($validated['project_ids'] as $id) {

                $res = $this->service->exportShort()->submitTask([
                    'folder_id'  => $validated['folder_id'],
                    'project_id' => $id,
                ]);

                $requestId = $res->getData()->resData->id . ',' . $validated['folder_id'] . ",$id";
                $requestIds[] = $requestId;

                ExportedVideo::create([
                    'task_id'      => $requestId,
                    'status'       => VideoStatusEnum::IN_PROGRESS->value,
                    'used_ai_tool' => 'klap',
                ]);
            }

            $exportingVideos = ExportedVideo::whereIn('task_id', $requestIds)->get();

            return response()->json([
                'status'  => 'success',
                'resData' => $exportingVideos,
            ]);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while export clips');
        }
    }

    // check status of export video
    public function checkExportStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'folderId'  => 'required|string',
                'projectId' => 'required|string',
                'export_id' => 'required|string',
            ]);

            return $this->service->exportShort()->checkTask($validated['export_id'], $validated);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while check export status on klap');
        }
    }

    // store final video result from klap
    public function storeFinalVideoKlap(Request $request)
    {
        try {
            $validated = $request->validate([
                'task_id'   => 'required|string',
                'video_url' => 'sometimes',
                'title'     => 'sometimes',
                'status'    => 'required|string',
            ]);
            $validated['status'] = $validated['status'] == 'success' ? VideoStatusEnum::COMPLETED->value : VideoStatusEnum::FAILED->value;

            $video = ExportedVideo::where('task_id', $validated['task_id'])->first();
            unset($validated['task_id']);
            $video->update($validated);

            return response()->json([
                'status'  => 'success',
                'resData' => $video,
            ]);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while store final result video from klap');
        }
    }
}
