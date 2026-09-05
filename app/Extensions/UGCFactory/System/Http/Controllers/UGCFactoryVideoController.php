<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Controllers;

use App\Extensions\UGCFactory\System\Actions\FinalizeUGCVideo;
use App\Extensions\UGCFactory\System\Http\Requests\StoreVideoRequest;
use App\Extensions\UGCFactory\System\Jobs\ProcessUGCVideoJob;
use App\Extensions\UGCFactory\System\Models\UGCFactoryActor;
use App\Extensions\UGCFactory\System\Models\UGCFactoryVideo;
use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use App\Extensions\UGCFactory\System\Services\VeedFabricService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class UGCFactoryVideoController extends Controller
{
    public function store(StoreVideoRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'error' => __('Video generation is disabled in demo mode.'),
            ], 403);
        }

        $user = $request->user();
        $plan = $user?->relationPlan;

        if ($plan && ! $plan->checkOpenAiItem('ugc_factory')) {
            return response()->json([
                'error' => __('Your plan does not include UGC Factory.'),
            ], 403);
        }

        $limit = $plan?->ugc_videos_limit ?? -1;

        if ($limit === 0) {
            return response()->json([
                'error' => __('UGC Factory video generation is disabled on your plan.'),
            ], 403);
        }

        if ($limit > 0) {
            $used = UGCFactoryVideo::query()
                ->forUser($user->id)
                ->currentMonth()
                ->whereNotIn('status', [UGCFactoryVideo::STATUS_FAILED])
                ->count();

            if ($used >= $limit) {
                return response()->json([
                    'error' => __('You have reached your monthly UGC video limit.'),
                ], 429);
            }
        }

        $actorSnapshot = $this->buildActorSnapshot($request);

        if (! $actorSnapshot) {
            return response()->json([
                'error' => __('Selected actor is invalid.'),
            ], 422);
        }

        $audioPath = null;
        if (in_array($request->string('audio_source')->toString(), [UGCFactoryVideo::SOURCE_UPLOAD, UGCFactoryVideo::SOURCE_RECORD], true)) {
            $audioFile = $request->file('audio');

            if ($audioFile) {
                // fal.ai VEED Fabric expects mp3/wav/m4a/aac/ogg by URL extension.
                // Laravel's auto-generated extension can land on `.mp4` for
                // audio-only mp4 containers, so map the detected MIME to one
                // of the supported extensions ourselves.
                $ext = $this->normalizeAudioExtension($audioFile);
                $audioPath = $audioFile->storeAs(
                    'ugc-factory/audio',
                    Str::random(40) . '.' . $ext,
                    'public',
                );
            }
        }

        $video = UGCFactoryVideo::create([
            'user_id'        => $user->id,
            'actor_id'       => $request->input('actor_type') === UGCFactoryActor::TYPE_PRESET
                ? null
                : (int) $request->input('actor_id'),
            'actor_snapshot' => $actorSnapshot,
            'title'          => (string) $request->input('title'),
            'script'         => $request->input('script'),
            'voice_provider' => $request->input('voice_provider'),
            'voice_id'       => $request->input('voice_id'),
            'voice_language' => $request->input('voice_language'),
            'audio_path'     => $audioPath,
            'audio_source'   => (string) $request->input('audio_source'),
            'resolution'     => UGCFactoryRegistry::getResolution(),
            'status'         => UGCFactoryVideo::STATUS_QUEUED,
        ]);

        // Match CreativeSuiteAITemplate's dispatch pattern: on the sync queue
        // run the submit job after the response so the user isn't blocked,
        // otherwise hand off to the configured queue worker.
        if (config('queue.default') === 'sync') {
            ProcessUGCVideoJob::dispatchAfterResponse($video->id);
        } else {
            ProcessUGCVideoJob::dispatch($video->id);
        }

        return response()->json([
            'data'        => [
                'id'     => $video->id,
                'status' => $video->status,
            ],
            'redirect_to' => route('dashboard.user.ugc-studio.index'),
        ], 201);
    }

    public function destroy(Request $request, int $video): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['error' => __('This feature is disabled in demo mode.')], 403);
        }

        $model = UGCFactoryVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        $model->delete();

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, int $video): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['error' => __('This feature is disabled in demo mode.')], 403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:160',
        ]);

        $model = UGCFactoryVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        $model->update(['title' => $data['title']]);

        return response()->json(['data' => $this->serialize($model)]);
    }

    public function show(Request $request, int $video): JsonResponse
    {
        $model = UGCFactoryVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        return response()->json(['data' => $this->serialize($model)]);
    }

    /**
     * Polled by the frontend to drive video generation forward.
     *
     * If the video is still processing and we have a fal.ai request id, this
     * checks fal.ai inline and finalizes (download + credit deduct) when
     * complete. Mirrors the CreativeSuiteAIController::status pattern so the
     * flow is queue-driver agnostic.
     */
    public function status(Request $request, int $video, FinalizeUGCVideo $finalize): JsonResponse
    {
        $model = UGCFactoryVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        if (in_array($model->status, [UGCFactoryVideo::STATUS_PROCESSING, UGCFactoryVideo::STATUS_QUEUED], true)
            && $model->fal_request_id) {
            try {
                $result = VeedFabricService::check($model->fal_request_id);

                if (($result['status'] ?? null) === 'completed') {
                    $finalize($model, (string) $result['video_url'], $result['duration'] ?? null);
                    $model->refresh();
                } elseif (($result['status'] ?? null) === 'failed') {
                    $model->update([
                        'status' => UGCFactoryVideo::STATUS_FAILED,
                        'error'  => $result['error'] ?? __('Video generation failed.'),
                    ]);
                }
            } catch (Throwable $e) {
                // Soft-fail: leave status as-is, frontend will retry.
            }
        }

        return response()->json(['data' => $this->serialize($model)]);
    }

    private function serialize(UGCFactoryVideo $model): array
    {
        return [
            'id'               => $model->id,
            'title'            => $model->title,
            'status'           => $model->status,
            'video_url'        => $model->video_url,
            'duration_seconds' => $model->duration_seconds,
            'thumb_url'        => $model->thumb_url,
            'created_at'       => $model->created_at?->toIso8601String(),
            'error'            => $model->error,
        ];
    }

    /**
     * Map the uploaded audio file's detected MIME to a fal.ai-supported extension.
     *
     * VEED Fabric expects the audio_url to end in mp3/wav/m4a/aac/ogg; any
     * other extension (including the `.mp4` Laravel would auto-pick for an
     * audio-only mp4 container) causes fal.ai to reject the request.
     */
    private function normalizeAudioExtension(UploadedFile $file): string
    {
        $mime = strtolower((string) $file->getMimeType());

        return match (true) {
            in_array($mime, ['audio/mpeg', 'audio/mp3'], true)                                   => 'mp3',
            in_array($mime, ['audio/wav', 'audio/wave', 'audio/x-wav'], true)                    => 'wav',
            in_array($mime, ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4'], true)  => 'm4a',
            in_array($mime, ['audio/aac', 'audio/x-aac', 'audio/aacp'], true)                    => 'aac',
            in_array($mime, ['audio/ogg', 'application/ogg'], true)                              => 'ogg',
            default                                                                              => $file->guessExtension() ?: 'bin',
        };
    }

    private function buildActorSnapshot(StoreVideoRequest $request): ?array
    {
        $user = $request->user();
        $type = (string) $request->input('actor_type');

        if ($type === UGCFactoryActor::TYPE_PRESET) {
            $presetKey = (string) $request->input('preset_key');

            if (! UGCFactoryRegistry::isPresetEnabled($presetKey)) {
                return null;
            }

            return [
                'type'       => UGCFactoryActor::TYPE_PRESET,
                'preset_key' => $presetKey,
                'name'       => $presetKey,
            ];
        }

        $actor = UGCFactoryActor::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', UGCFactoryActor::STATUS_READY)
            ->find((int) $request->input('actor_id'));

        if (! $actor) {
            return null;
        }

        return [
            'type'       => $actor->type,
            'image_path' => $actor->image_path,
            'name'       => $actor->name,
        ];
    }
}
