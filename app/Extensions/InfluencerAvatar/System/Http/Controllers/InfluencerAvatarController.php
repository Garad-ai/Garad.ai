<?php

namespace App\Extensions\InfluencerAvatar\System\Http\Controllers;

use App\Concerns\HasErrorResponse;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Enums\AiInfluencer\VideoStatusEnum;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Models\ExportedVideo;
use App\Packages\FalAI\FalAIService;
use App\Packages\FalAI\Requests\VeedSubmitRequest;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class InfluencerAvatarController
{
    use HasErrorResponse;

    // index
    public function index()
    {
        return view('influencer-avatar::index');
    }

    // generate short video
    public function generateShortVideo(VeedSubmitRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $validated = $request->validated();

        $driver = Entity::driver(EntityEnum::VEED)->inputVideoCount(1)->calculateCredit();
        $driver->redirectIfNoCreditBalance();
        $driver->decreaseCredit();

        try {

            $service = new FalAIService(ApiHelper::setFalAIKey());
            $res = $service->textToVideoModel(EntityEnum::VEED)->submit($validated);

            $requestId = $res->getData()?->resData?->request_id;

            if (! empty($requestId)) {
                ExportedVideo::create([
                    'task_id'      => $requestId,
                    'status'       => VideoStatusEnum::IN_PROGRESS->value,
                    'used_ai_tool' => 'fal-ai',
                ]);
            }

            return $res;
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while generate short video on ai-influcener');
        }
    }

    // check status of generating video
    public function checkStatus(string $requestId): JsonResponse
    {
        try {
            $service = new FalAIService(ApiHelper::setFalAIKey());

            return $service->textToVideoModel(EntityEnum::VEED)->checkStatus($requestId);
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while checking status of generating video on ai-influcener');
        }
    }

    // get final video
    public function getFinalVideo(string $requestId): JsonResponse
    {
        try {
            $service = new FalAIService(ApiHelper::setFalAIKey());
            $resData = $service->textToVideoModel(EntityEnum::VEED)->getResult($requestId)->getData();

            if ($resData->status === 'success') {
                $videoResult = $resData->resData->video;
                $video = ExportedVideo::where('task_id', $requestId)->first();
                $video->update([
                    'video_url' => $videoResult->url,
                    'title'     => 'Short Video',
                    'status'    => VideoStatusEnum::COMPLETED->value,
                ]);

                return response()->json([
                    'status'  => 'success',
                    'resData' => $video,
                ]);
            }

            $video = ExportedVideo::where('task_id', $requestId)->update([
                'status' => VideoStatusEnum::FAILED->value,
            ]);

            throw new RuntimeException($resData->message ?? 'Unexpected issue happen');
        } catch (Throwable $th) {
            return $this->exceptionRes($th, 'Error happen while get final result on ai-influcener');
        }
    }
}
