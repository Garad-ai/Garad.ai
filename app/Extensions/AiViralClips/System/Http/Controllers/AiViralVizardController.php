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
use App\Packages\Vizard\Requests\VideoToShortSubmitRequest;
use App\Packages\Vizard\VizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiViralVizardController extends Controller
{
    use HasErrorResponse;

    public VizardService $service;

    public function __construct()
    {
        $this->service = new VizardService(ApiHelper::setVizardApiKey());
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

        $driver = Entity::driver(EntityEnum::AI_CLIP_VIZARD)->inputVideoCount(1)->calculateCredit();
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
                $validated['videoUrl'] = Storage::disk($uploadDisk)->url($path);
                $validated['videoType'] = 1;
            }
            unset($validated['file']);

            $validated['preferLength'] = [$validated['preferLength']];
            $validated['videoType'] = (int) $validated['videoType'];
            $validated['ratioOfClip'] = (int) $validated['ratioOfClip'];
            $validated['headlineSwitch'] = (int) $validated['headlineSwitch'];
            $validated['subtitleSwitch'] = (int) $validated['subtitleSwitch'];
            $validated['maxClipNumber'] = (int) $validated['maxClipNumber'];
            $validated['ext'] = pathinfo($validated['videoUrl'], PATHINFO_EXTENSION);
            $driver->decreaseCredit();

            return $this->service->submitVideoForClipping($validated);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'error happen while generate short clips using vizard');
        }

    }

    // check the task status
    public function retrieveClips(string $taskId): JsonResponse
    {
        return $this->service->retrieveClips($taskId);
    }

    // store final result for vizard
    public function storeFinalVideoVizard(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'videos' => 'required|array',
            ]);

            $folderPath = public_path('uploads/videos');
            if (! file_exists($folderPath)) {
                if (! mkdir($folderPath, 755, true) && ! is_dir($folderPath)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $folderPath));
                }
            }

            foreach ($validated['videos'] as $video) {
                $videoUrl = $video['videoUrl'];

                $pathInfo = pathinfo(parse_url($videoUrl, PHP_URL_PATH));
                $extension = $pathInfo['extension'] ?? 'mp4';

                $response = Http::get($videoUrl);
                if (! $response->successful()) {
                    continue;
                }

                $videoContent = $response->body();
                $filename = Str::uuid() . '.' . $extension;
                Storage::disk('uploads')->put("videos/{$filename}", $videoContent);

                ExportedVideo::create([
                    'video_url'    => Storage::disk('uploads')->url("videos/{$filename}"),
                    'used_ai_tool' => 'vizard',
                    'title'        => $video['title'],
                    'task_id'      => 'vizard-video-' . $video['videoId'],
                    'status'       => VideoStatusEnum::COMPLETED->value,
                ]);
            }

            $storedVideos = ExportedVideo::whereIn('task_id', array_map(fn ($array) => 'vizard-video-' . $array['videoId'], $validated['videos']))->get();

            return response()->json([
                'status'  => 'success',
                'resData' => $storedVideos,
            ]);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while store final video result for vizard');
        }
    }
}
