<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\SocialMedia\System\Services\Generator\GoogleVeo2Service;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialMediaVideoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['prompt' => 'required']);

        $driver = Entity::driver(EntityEnum::VEO_2)->inputVideoCount(1)->calculateCredit();

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 'error',
            ], 422);
        }

        $driver->decreaseCredit();

        $generate = GoogleVeo2Service::generate($request->get('prompt'));

        if ($generate->json('status') === 'IN_QUEUE' && $generate->json('request_id')) {
            session(['video_id_' . Auth::id() => $generate->json('request_id')]);
            session()->save();

            return response()->json([
                'status'  => 'success',
                'message' => trans('Video is generating...'),
            ]);
        }

        return response()->json([
            'message' => 'Video could not be generated.',
            'status'  => 'error',
        ], 422);
    }

    public function status(): JsonResponse
    {
        $videoId = session('video_id_' . Auth::id());

        if (! $videoId) {
            return response()->json([
                'success' => false,
                'message' => __('Video ID not found.'),
                'status'  => 'not_found',
            ], 404);
        }

        $status = GoogleVeo2Service::status($videoId);

        try {
            $statusJson = $status->json('status');

            if ($statusJson === 'COMPLETED') {
                $content = GoogleVeo2Service::content($videoId);

                $video = $content->json('video.url');

                if ($video) {
                    $videoPath = GoogleVeo2Service::downloadAndSaveVideoFromUrl($video);

                    return response()->json([
                        'status'     => 'COMPLETED',
                        'video_path' => $videoPath,
                    ]);
                }

                return response()->json([
                    'message'  => trans('Video is not completed.'),
                    'status'   => 'error',
                ], 422);
            }

            if ($statusJson === 'IN_PROGRESS') {
                return response()->json([
                    'status'  => 'IN_PROGRESS',
                    'message' => trans('Video is processing..'),
                ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'message'  => trans('Video is not completed.'),
                'status'   => 'error',
            ], 422);
        }

        return response()->json([
            'message'  => trans('Video is not completed.'),
            'status'   => 'ERROR',
        ], 422);
    }
}
