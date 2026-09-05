<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Controllers;

use App\Extensions\UGCCreator\System\Actions\BuildCreatorPrompt;
use App\Extensions\UGCCreator\System\Actions\FinalizeUGCCreatorVideo;
use App\Extensions\UGCCreator\System\Http\Requests\StoreCreatorVideoRequest;
use App\Extensions\UGCCreator\System\Jobs\ProcessUGCCreatorVideoJob;
use App\Extensions\UGCCreator\System\Models\UGCCreatorAsset;
use App\Extensions\UGCCreator\System\Models\UGCCreatorVideo;
use App\Extensions\UGCCreator\System\Services\FalVideoService;
use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UGCCreatorVideoController extends Controller
{
    public function store(StoreCreatorVideoRequest $request, BuildCreatorPrompt $buildPrompt): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'error' => __('Video generation is disabled in demo mode.'),
            ], 403);
        }

        $user = $request->user();
        $plan = $user?->relationPlan;

        if ($plan && ! $plan->checkOpenAiItem('ugc_creator')) {
            return response()->json([
                'error' => __('Your plan does not include UGC Creator.'),
            ], 403);
        }

        $limit = $plan?->ugc_creator_videos_limit ?? -1;

        if ($limit === 0) {
            return response()->json([
                'error' => __('UGC Creator video generation is disabled on your plan.'),
            ], 403);
        }

        if ($limit > 0) {
            $used = UGCCreatorVideo::query()
                ->forUser($user->id)
                ->currentMonth()
                ->whereNotIn('status', [UGCCreatorVideo::STATUS_FAILED])
                ->count();

            if ($used >= $limit) {
                return response()->json([
                    'error' => __('You have reached your monthly UGC Creator video limit.'),
                ], 429);
            }
        }

        $productFiles = array_values(array_filter(
            (array) $request->file('product_files', []),
            static fn ($f) => $f instanceof UploadedFile,
        ));
        $characterFile = $request->file('character_file');
        if (! ($characterFile instanceof UploadedFile)) {
            $characterFile = null;
        }

        // fal.ai endpoints all require an image_url. If the user uploaded no
        // assets, a non-auto camera preset thumbnail seeds the first frame —
        // 'auto' has no usable image, so we reject that combination here too.
        $cameraPreset = trim((string) $request->input('camera_preset'));
        if ($productFiles === [] && $characterFile === null && ($cameraPreset === '' || $cameraPreset === 'auto')) {
            return response()->json([
                'error' => __('Please upload at least one product or character image, or pick a camera preset.'),
            ], 422);
        }

        foreach ($productFiles as $file) {
            if (! isFileSecure($file)) {
                return response()->json([
                    'error' => __("File ':name' is not allowed for security reasons.", ['name' => $file->getClientOriginalName()]),
                ], 422);
            }
        }
        if ($characterFile !== null && ! isFileSecure($characterFile)) {
            return response()->json([
                'error' => __("File ':name' is not allowed for security reasons.", ['name' => $characterFile->getClientOriginalName()]),
            ], 422);
        }

        $productIds = [];
        foreach ($productFiles as $file) {
            $productIds[] = $this->storeAsset($user->id, $file, UGCCreatorAsset::KIND_PRODUCT)->id;
        }
        $characterIds = [];
        if ($characterFile !== null) {
            $characterIds[] = $this->storeAsset($user->id, $characterFile, UGCCreatorAsset::KIND_CHARACTER)->id;
        }

        $snapshot = $this->buildAssetSnapshot($productIds, $characterIds);

        $modelKey = (string) $request->input('model');
        if (! array_key_exists($modelKey, UGCCreatorRegistry::getEnabledModels())) {
            $modelKey = UGCCreatorRegistry::getDefaultModel();
        }

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            $title = mb_substr(trim((string) $request->input('video_details')), 0, 80) ?: __('Untitled UGC video');
        }

        $video = UGCCreatorVideo::create([
            'user_id'             => $user->id,
            'title'               => $title,
            'prompt'              => (string) $request->input('video_details'),
            'camera_preset'       => $request->input('camera_preset') ?: 'auto',
            'video_details'       => $request->input('video_details'),
            'asset_snapshot'      => $snapshot,
            'product_asset_ids'   => $productIds,
            'character_asset_ids' => $characterIds,
            'audio_enabled'       => (bool) $request->boolean('audio_enabled'),
            'model'               => $modelKey,
            'quality'             => UGCCreatorRegistry::getQuality(),
            'status'              => UGCCreatorVideo::STATUS_QUEUED,
        ]);

        $video->update([
            'final_prompt' => ($buildPrompt)($video),
        ]);

        if (config('queue.default') === 'sync') {
            ProcessUGCCreatorVideoJob::dispatchAfterResponse($video->id);
        } else {
            ProcessUGCCreatorVideoJob::dispatch($video->id);
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

        $model = UGCCreatorVideo::query()
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
            'title' => 'required|string|max:200',
        ]);

        $model = UGCCreatorVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        $model->update(['title' => $data['title']]);

        return response()->json(['data' => $this->serialize($model, $request)]);
    }

    public function show(Request $request, int $video): JsonResponse
    {
        $model = UGCCreatorVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        return response()->json(['data' => $this->serialize($model, $request)]);
    }

    public function status(Request $request, int $video, FinalizeUGCCreatorVideo $finalize): JsonResponse
    {
        $model = UGCCreatorVideo::query()
            ->forUser($request->user()->id)
            ->findOrFail($video);

        if (in_array($model->status, [UGCCreatorVideo::STATUS_PROCESSING, UGCCreatorVideo::STATUS_QUEUED], true)
            && $model->fal_request_id
            && ($model->fal_response_url || $model->fal_status_url)) {
            try {
                $result = FalVideoService::check($model->fal_response_url, $model->fal_status_url);

                if (($result['status'] ?? null) === 'completed') {
                    $finalize($model, (string) $result['video_url'], $result['duration'] ?? null);
                    $model->refresh();
                } elseif (($result['status'] ?? null) === 'failed') {
                    $model->update([
                        'status' => UGCCreatorVideo::STATUS_FAILED,
                        'error'  => $result['error'] ?? __('Video generation failed.'),
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('UGCCreator status poll failed', [
                    'video_id'   => $model->id,
                    'request_id' => $model->fal_request_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['data' => $this->serialize($model, $request)]);
    }

    private function serialize(UGCCreatorVideo $model, Request $request): array
    {
        $isAdmin = (bool) $request->user()?->isAdmin();

        return [
            'id'                 => $model->id,
            'title'              => $model->title,
            'status'             => $model->status,
            'video_url'          => $model->video_url,
            'duration_seconds'   => $model->duration_seconds,
            'formatted_duration' => $model->duration_seconds ? gmdate(($model->duration_seconds >= 3600 ? 'H:i:s' : 'i:s'), (int) $model->duration_seconds) : null,
            'thumb_url'          => $model->thumb_url,
            'prompt'             => $model->video_details ?: $model->prompt,
            'model'              => $model->model,
            'source_label'       => __('UGC Creator'),
            'created_at'         => $model->created_at?->toIso8601String(),
            'error'              => $this->presentError($model->error, $isAdmin),
        ];
    }

    /**
     * Show the raw fal.ai error to admins so they can debug billing / key
     * issues, but always give end users a friendly message — they can't act
     * on "User is locked. Exhausted balance." themselves.
     */
    private function presentError(?string $error, bool $isAdmin): ?string
    {
        if ($error === null || $error === '') {
            return null;
        }

        if ($isAdmin) {
            return $error;
        }

        return __('Video generation failed. Please try again later or contact support.');
    }

    private function storeAsset(int $userId, UploadedFile $file, string $kind): UGCCreatorAsset
    {
        $extension = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'png');
        $path = $file->storeAs(
            'ugc-creator/assets/' . $kind,
            Str::uuid()->toString() . '.' . $extension,
            'public',
        );

        return UGCCreatorAsset::create([
            'user_id'    => $userId,
            'kind'       => $kind,
            'image_path' => $path,
            'mime'       => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $characterIds
     *
     * @return array<int, array{kind: string, asset_id: int, image_path: string}>
     */
    private function buildAssetSnapshot(array $productIds, array $characterIds): array
    {
        $ids = array_merge($productIds, $characterIds);
        if ($ids === []) {
            return [];
        }

        $assets = UGCCreatorAsset::query()->whereIn('id', $ids)->get()->keyBy('id');

        $snapshot = [];

        foreach ($productIds as $id) {
            $asset = $assets->get($id);
            if ($asset) {
                $snapshot[] = [
                    'kind'       => UGCCreatorAsset::KIND_PRODUCT,
                    'asset_id'   => (int) $asset->id,
                    'image_path' => (string) $asset->image_path,
                ];
            }
        }

        foreach ($characterIds as $id) {
            $asset = $assets->get($id);
            if ($asset) {
                $snapshot[] = [
                    'kind'       => UGCCreatorAsset::KIND_CHARACTER,
                    'asset_id'   => (int) $asset->id,
                    'image_path' => (string) $asset->image_path,
                ];
            }
        }

        return $snapshot;
    }
}
