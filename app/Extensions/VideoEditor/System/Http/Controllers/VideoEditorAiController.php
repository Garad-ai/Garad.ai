<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Controllers;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiMusicPro\System\Services\LyriaMusicService;
use App\Extensions\AiVideoPro\System\Models\UserFall;
use App\Extensions\AiVideoPro\System\Services\ModelConfigurationService;
use App\Extensions\AiVideoPro\System\Services\SoraService;
use App\Extensions\VideoEditor\System\Http\Requests\GenerateAiVideoRequest;
use App\Extensions\VideoEditor\System\Models\VideoEditorMedia;
use App\Extensions\VideoEditor\System\Models\VideoEditorProject;
use App\Extensions\VideoEditor\System\Services\MediaProcessingService;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SettingTwo;
use App\Packages\Elevenlabs\ElevenlabsService as PackageElevenlabsService;
use App\Packages\FalAI\FalAIService as PackageFalAIService;
use App\Services\ElevenlabsService;
use Exception;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class VideoEditorAiController extends Controller
{
    private PackageFalAIService $falAIService;

    public function __construct()
    {
        $this->falAIService = new PackageFalAIService(ApiHelper::setFalAIKey());
    }

    /**
     * Return the full nested model configuration sourced live from
     * AiVideoPro\ModelConfigurationService — keeps the editor in sync with
     * any future model/feature additions made in the AiVideoPro extension.
     *
     * All active features are exposed. File inputs are preserved so that
     * features which need an image/video (e.g. background-removal,
     * image-to-video) remain usable from the editor's AI Generate panel.
     */
    public function models(): JsonResponse
    {
        $config = ModelConfigurationService::getConfig();
        $sanitized = [];

        foreach ($config as $groupKey => $group) {
            if (! ($group['isActive'] ?? false)) {
                continue;
            }

            $subModels = [];

            foreach ($group['subModels'] ?? [] as $subModelKey => $subModel) {
                if (! ($subModel['isActive'] ?? false)) {
                    continue;
                }

                $features = [];

                foreach ($subModel['features'] ?? [] as $slug => $feature) {
                    $features[$slug] = [
                        'label'       => $feature['label'] ?? $slug,
                        'description' => $feature['description'] ?? '',
                        'inputs'      => $this->sanitizeFeatureInputs($feature['inputs'] ?? []),
                    ];
                }

                if (empty($features)) {
                    continue;
                }

                $subModels[$subModelKey] = [
                    'isActive' => true,
                    'features' => $features,
                ];
            }

            if (empty($subModels)) {
                continue;
            }

            $sanitized[$groupKey] = [
                'label'       => $group['label'] ?? $groupKey,
                'description' => $group['description'] ?? '',
                'isActive'    => true,
                'subModels'   => $subModels,
            ];
        }

        return response()->json([
            'status' => 'success',
            'config' => $sanitized,
        ]);
    }

    private function sanitizeFeatureInputs(array $rawInputs): array
    {
        $inputs = [];

        foreach ($rawInputs as $input) {
            $inputs[] = [
                'name'        => $input['name'] ?? '',
                'type'        => $input['type'] ?? 'text',
                'label'       => $input['label'] ?? ($input['name'] ?? ''),
                'required'    => $input['required'] ?? false,
                'default'     => $input['default'] ?? null,
                'options'     => $input['options'] ?? [],
                'rows'        => $input['rows'] ?? null,
                'placeholder' => $input['placeholder'] ?? null,
                'tooltip'     => $input['tooltip'] ?? null,
                'min'         => $input['min'] ?? null,
                'max'         => $input['max'] ?? null,
                'step'        => $input['step'] ?? null,
                'accept'      => $input['accept'] ?? null,
                'multiple'    => $input['multiple'] ?? false,
                'show_if'     => $input['show_if'] ?? null,
            ];
        }

        return $inputs;
    }

    // =========================================================================
    //  GENERATE
    // =========================================================================

    public function generate(GenerateAiVideoRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        if (! ApiHelper::setFalAIKey()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('FAL AI key is not configured.'),
            ], 400);
        }

        $validated = $request->validated();
        $model = $validated['model'];
        $action = $validated['action'] ?? '';

        try {
            $entityEnum = EntityEnum::fromSlug($model);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Unknown model: ') . $model,
            ], 400);
        }

        // Credit check — Sora uses inputSecond, others use inputVideoCount
        try {
            $driver = Entity::driver($entityEnum);

            if ($action === 'sora') {
                $seconds = (int) ($validated['sora_seconds'] ?? 4);
                $driver = $driver->inputSecond($seconds)->calculateCredit();
            } else {
                $driver = $driver->inputVideoCount(1)->calculateCredit();
            }

            if (! $driver->hasCreditBalance()) {
                return response()->json([
                    'status'      => 'error',
                    'message'     => __('Insufficient credits for this operation.'),
                    'upgrade_url' => route('dashboard.user.payment.subscription'),
                ], 402);
            }
        } catch (Exception $e) {
            Log::error('Video Editor AI credit check failed', [
                'error'   => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => __('Failed to check credits. Please try again.'),
            ], 400);
        }

        // Dispatch to model-specific handler based on action group
        try {
            $falResponse = match ($action) {
                'sora'               => $this->handleSora($validated, $entityEnum),
                'veo'                => $this->handleVeo($validated, $entityEnum),
                'kling'              => $this->handleKling($validated, $entityEnum),
                'luma-dream-machine' => $this->handleLumaDreamMachine($validated),
                'minimax'            => $this->handleMinimax($validated),
                default              => $this->handlePackageFallback($validated, $entityEnum),
            };

            if (isset($falResponse['__error'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $falResponse['__error'],
                ], 400);
            }

            $userFall = UserFall::create([
                'user_id'      => Auth::id(),
                'prompt'       => $validated['prompt'],
                'model'        => $model,
                'status'       => $falResponse['status'] ?? 'IN_QUEUE',
                'request_id'   => $falResponse['request_id'] ?? $falResponse['id'] ?? null,
                'response_url' => $falResponse['response_url'] ?? null,
            ]);

            $driver->decreaseCredit();

            return response()->json([
                'status'     => 'success',
                'message'    => __('Video generation started'),
                'generation' => [
                    'id'        => $userFall->id,
                    'prompt'    => $userFall->prompt,
                    'model'     => $userFall->model,
                    'status'    => $userFall->status,
                    'video_url' => null,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Video Editor AI generation failed', [
                'error'   => $e->getMessage(),
                'model'   => $model,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => __('Video generation failed. Please try again.'),
            ], 500);
        }
    }

    // ─── Model-specific handlers ─────────────────────────────────────────

    /**
     * Sora — uses OpenAI Sora API via SoraService.
     */
    private function handleSora(array $validated, EntityEnum $entityEnum): array
    {
        $params = [
            'prompt'  => $validated['prompt'],
            'model'   => $entityEnum->value,
            'seconds' => (int) ($validated['sora_seconds'] ?? 4),
            'size'    => $validated['sora_size'] ?? '720x1280',
        ];

        $response = SoraService::generate($params);

        if (isset($response['error'])) {
            return ['__error' => $response['error']['message'] ?? __('Sora generation failed')];
        }

        return $response;
    }

    /**
     * Veo family — routes to legacy Veo2 or package-based Veo 3/3.1.
     */
    private function handleVeo(array $validated, EntityEnum $entityEnum): array
    {
        $feature = $validated['feature'] ?? $validated['model'];

        // Legacy Veo2
        if ($feature === 'veo2') {
            return $this->handleLegacyVeo2($validated);
        }

        // Modern Veo (3, 3 Fast, 3.1 variants)
        $payload = $this->buildVeoPayload($validated);

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return ['__error' => $resData->message ?? __('Veo generation failed')];
        }

        return (array) $resData->resData;
    }

    /**
     * Legacy Veo2 — uses Domain Engine FalAIService.
     */
    private function handleLegacyVeo2(array $validated): array
    {
        $response = FalAIService::veo2Generate($validated['prompt']);

        if ($response->failed()) {
            return ['__error' => $response->json('detail', __('Veo2 generation failed'))];
        }

        $json = $response->json();

        if (isset($json['status']) && $json['status'] === 'error') {
            return ['__error' => $json['message'] ?? __('Veo2 generation failed')];
        }

        return $json;
    }

    /**
     * Build Veo payload with all model-specific parameters.
     */
    private function buildVeoPayload(array $validated): array
    {
        $payload = ['prompt' => $validated['prompt']];
        $feature = $validated['feature'] ?? $validated['model'];

        // Veo 3.1 requires mode parameter
        if (str_starts_with($feature, 'veo3.1/')) {
            $payload['mode'] = $feature;
        }

        $this->addIfPresent($payload, $validated, 'duration');
        $this->addIfPresent($payload, $validated, 'resolution');
        $this->addIfPresent($payload, $validated, 'aspect_ratio');
        $this->addIfPresent($payload, $validated, 'negative_prompt');

        // Boolean fields
        if (isset($validated['generate_audio'])) {
            $payload['generate_audio'] = (bool) $validated['generate_audio'];
        }
        if (isset($validated['enhance_prompt'])) {
            $payload['enhance_prompt'] = (bool) $validated['enhance_prompt'];
        }
        if (isset($validated['auto_fix'])) {
            $payload['auto_fix'] = (bool) $validated['auto_fix'];
        }

        // Seed
        if (! empty($validated['seed'])) {
            $payload['seed'] = (int) $validated['seed'];
        }

        return $payload;
    }

    /**
     * Kling family — routes to legacy Kling, Kling 2.5, or Kling 2.6.
     */
    private function handleKling(array $validated, EntityEnum $entityEnum): array
    {
        $model = $validated['model'];

        // Legacy kling v1
        if ($model === 'kling') {
            return $this->handleLegacyKling($validated);
        }

        // Kling 2.5 Turbo
        if (str_starts_with($model, 'kling-2.5-turbo/')) {
            return $this->handleKling25($validated, $entityEnum);
        }

        // Kling 2.6 Pro
        if (str_starts_with($model, 'kling-video/v2.6/pro/')) {
            return $this->handleKling26Pro($validated, $entityEnum);
        }

        // Fallback to package
        return $this->handlePackageFallback($validated, $entityEnum);
    }

    /**
     * Legacy Kling v1 — uses Domain Engine FalAIService.
     */
    private function handleLegacyKling(array $validated): array
    {
        $response = FalAIService::klingGenerate($validated['prompt']);

        if (isset($response['detail']) && is_string($response['detail'])) {
            return ['__error' => $response['detail']];
        }

        return $response;
    }

    /**
     * Kling 2.5 Turbo — uses FalAI Package service.
     */
    private function handleKling25(array $validated, EntityEnum $entityEnum): array
    {
        $payload = [
            'prompt'       => $validated['prompt'],
            'duration'     => (int) ($validated['kling25turbo_duration'] ?? 5),
            'aspect_ratio' => $validated['kling25turbo_aspect_ratio'] ?? $validated['aspect_ratio'] ?? '16:9',
        ];

        // Optional fields
        if (! empty($validated['seed'])) {
            $payload['seed'] = (int) $validated['seed'];
        }
        if (! empty($validated['negative_prompt'])) {
            $payload['negative_prompt'] = $validated['negative_prompt'];
        }
        if (! empty($validated['cfg_scale'])) {
            $payload['cfg_scale'] = (float) $validated['cfg_scale'];
        }
        if (isset($validated['loop'])) {
            $payload['loop'] = (bool) $validated['loop'];
        }

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return ['__error' => $resData->message ?? __('Kling 2.5 generation failed')];
        }

        return (array) $resData->resData;
    }

    /**
     * Kling 2.6 Pro — uses FalAI Package service.
     */
    private function handleKling26Pro(array $validated, EntityEnum $entityEnum): array
    {
        $payload = [
            'prompt'       => $validated['prompt'],
            'duration'     => (int) ($validated['kling26pro_duration'] ?? $validated['kling25pro_duration'] ?? 5),
            'aspect_ratio' => $validated['kling26pro_aspect_ratio'] ?? $validated['aspect_ratio'] ?? '16:9',
        ];

        if (! empty($validated['cfg_scale'])) {
            $payload['cfg_scale'] = (float) $validated['cfg_scale'];
        }
        if (! empty($validated['negative_prompt'])) {
            $payload['negative_prompt'] = $validated['negative_prompt'];
        }

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return ['__error' => $resData->message ?? __('Kling 2.6 generation failed')];
        }

        return (array) $resData->resData;
    }

    /**
     * Luma Dream Machine — uses Domain Engine FalAIService.
     */
    private function handleLumaDreamMachine(array $validated): array
    {
        $response = FalAIService::lumaGenerate($validated['prompt']);

        if (isset($response['detail']) && is_string($response['detail'])) {
            return ['__error' => $response['detail']];
        }

        return $response;
    }

    /**
     * Minimax — uses Domain Engine FalAIService.
     */
    private function handleMinimax(array $validated): array
    {
        $response = FalAIService::minimaxGenerate($validated['prompt']);

        if (isset($response['detail']) && is_string($response['detail'])) {
            return ['__error' => $response['detail']];
        }

        return $response;
    }

    /**
     * Fallback for any other package-based models.
     */
    private function handlePackageFallback(array $validated, EntityEnum $entityEnum): array
    {
        $payload = ['prompt' => $validated['prompt']];

        $this->addIfPresent($payload, $validated, 'duration');
        $this->addIfPresent($payload, $validated, 'aspect_ratio');
        $this->addIfPresent($payload, $validated, 'negative_prompt');

        if (isset($validated['generate_audio'])) {
            $payload['generate_audio'] = (bool) $validated['generate_audio'];
        }
        if (isset($validated['enhance_prompt'])) {
            $payload['enhance_prompt'] = (bool) $validated['enhance_prompt'];
        }
        if (! empty($validated['seed'])) {
            $payload['seed'] = (int) $validated['seed'];
        }

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return ['__error' => $resData->message ?? __('Generation failed')];
        }

        return (array) $resData->resData;
    }

    /**
     * Add a field to payload if present and non-empty in validated data.
     */
    private function addIfPresent(array &$payload, array $validated, string $key): void
    {
        if (! empty($validated[$key])) {
            $payload[$key] = $validated[$key];
        }
    }

    // =========================================================================
    //  STATUS CHECK
    // =========================================================================

    public function checkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'entries'   => ['required', 'array', 'max:20'],
            'entries.*' => ['integer'],
        ]);

        $entries = UserFall::query()
            ->whereIn('id', $request->input('entries'))
            ->where('user_id', Auth::id())
            ->whereNotIn('status', ['complete', 'error'])
            ->get();

        $results = [];

        foreach ($entries as $entry) {
            try {
                $entryModel = $entry->model ?? '';

                $result = match (true) {
                    str_starts_with($entryModel, 'sora')                  => $this->checkSoraStatus($entry),
                    str_starts_with($entryModel, 'veo3.1/')               => $this->checkPackageStatus($entry),
                    in_array($entryModel, ['veo3', 'veo3-fast'], true)    => $this->checkPackageStatus($entry),
                    str_starts_with($entryModel, 'kling-2.5-turbo/')      => $this->checkPackageStatus($entry),
                    str_starts_with($entryModel, 'kling-video/v2.6/pro/') => $this->checkPackageStatus($entry),
                    default                                               => $this->checkLegacyStatus($entry),
                };

                if ($result) {
                    $results[] = $result;
                } else {
                    $results[] = [
                        'id'     => $entry->id,
                        'status' => 'processing',
                    ];
                }
            } catch (InvalidArgumentException $e) {
                // Model not supported — mark as error instead of polling forever
                Log::error('Video Editor AI unsupported model during status check', [
                    'entry_id' => $entry->id,
                    'model'    => $entry->model,
                    'error'    => $e->getMessage(),
                ]);

                $entry->update(['status' => 'error']);

                $results[] = [
                    'id'     => $entry->id,
                    'status' => 'error',
                ];
            } catch (Exception $e) {
                Log::error('Video Editor AI status check failed', [
                    'entry_id' => $entry->id,
                    'model'    => $entry->model,
                    'error'    => $e->getMessage(),
                ]);

                $results[] = [
                    'id'     => $entry->id,
                    'status' => 'processing',
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'results' => $results,
        ]);
    }

    /**
     * Sora status check — uses OpenAI Sora API.
     */
    private function checkSoraStatus(UserFall $entry): ?array
    {
        $response = SoraService::getStatus($entry->request_id);
        $status = $response['status'] ?? null;

        if ($status === 'completed') {
            $videoUrl = SoraService::getVideo($entry->request_id);

            if ($videoUrl) {
                $entry->update(['status' => 'complete', 'video_url' => $videoUrl]);

                return ['id' => $entry->id, 'status' => 'complete', 'video_url' => $videoUrl];
            }

            $entry->update(['status' => 'error']);

            return ['id' => $entry->id, 'status' => 'error'];
        }

        if ($status === 'failed') {
            $entry->update(['status' => 'error']);

            return ['id' => $entry->id, 'status' => 'error'];
        }

        return null; // still processing
    }

    /**
     * Package-based status check (Veo 3.1, Kling 2.5, Kling 2.6) — uses request_id.
     */
    private function checkPackageStatus(UserFall $entry): ?array
    {
        $entityEnum = EntityEnum::fromSlug($entry->model);

        $check = $this->falAIService->textToVideoModel($entityEnum)->checkStatus($entry->request_id)->getData();
        $status = $check->resData->status ?? null;

        if ($status === 'NOT_FOUND') {
            $entry->update(['status' => 'error']);

            return ['id' => $entry->id, 'status' => 'error'];
        }

        if ($status !== 'COMPLETED') {
            return null; // still processing
        }

        $result = $this->falAIService->textToVideoModel($entityEnum)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;

            if ($videoUrl) {
                $entry->update(['status' => 'complete', 'video_url' => $videoUrl]);

                return ['id' => $entry->id, 'status' => 'complete', 'video_url' => $videoUrl];
            }
        }

        if (in_array($result->status ?? null, ['failed', 'error'])) {
            $entry->update(['status' => 'error']);

            return ['id' => $entry->id, 'status' => 'error'];
        }

        return null;
    }

    /**
     * Legacy status check (Kling v1, Luma, Minimax, Veo2) — uses response_url.
     */
    private function checkLegacyStatus(UserFall $entry): ?array
    {
        // If no response_url, try package path (fallback for entries with request_id)
        if (! $entry->response_url) {
            if ($entry->request_id) {
                return $this->checkPackageStatus($entry);
            }

            $entry->update(['status' => 'error']);

            return ['id' => $entry->id, 'status' => 'error'];
        }

        $response = FalAIService::getStatus($entry->response_url);

        if (! empty($response['video']['url'])) {
            $videoUrl = $response['video']['url'];
            $entry->update(['status' => 'complete', 'video_url' => $videoUrl]);

            return ['id' => $entry->id, 'status' => 'complete', 'video_url' => $videoUrl];
        }

        $detail = $response['detail'] ?? null;

        if (
            in_array($detail, ['Internal Server Error', 'Luma API timed out']) ||
            (isset($detail[0]['type']) && $detail[0]['type'] === 'image_load_error')
        ) {
            $entry->update(['status' => 'error']);

            return ['id' => $entry->id, 'status' => 'error'];
        }

        return null;
    }

    // =========================================================================
    //  IMPORT & CREDITS
    // =========================================================================

    public function importToEditor(Request $request): JsonResponse
    {
        $request->validate([
            'user_fall_id' => ['required', 'integer'],
            'project_id'   => ['nullable', 'integer', 'exists:video_editor_projects,id'],
        ]);

        // Verify project ownership
        if ($request->input('project_id')) {
            $project = VideoEditorProject::findOrFail($request->input('project_id'));
            abort_if($project->user_id !== Auth::id(), 404);
        }

        $userFall = UserFall::query()
            ->where('id', $request->input('user_fall_id'))
            ->where('user_id', Auth::id())
            ->where('status', 'complete')
            ->firstOrFail();

        if (! $userFall->video_url) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Video not ready yet'),
            ], 400);
        }

        // Generate server-side thumbnail + metadata from the remote AI video URL.
        // ffmpeg supports HTTP(S) inputs, so we can probe/snapshot without downloading first.
        // This avoids the cross-origin canvas taint that prevents client-side thumbnails
        // from being generated for AI-generated videos hosted on fal.media / openai.
        $thumbnailPath = MediaProcessingService::generateThumbnail(
            $userFall->video_url,
            'video',
            Auth::id()
        );
        $metadata = MediaProcessingService::extractMetadata($userFall->video_url, 'video');

        $media = new VideoEditorMedia([
            'video_editor_project_id' => $request->input('project_id'),
            'type'                    => 'video',
            'filename'                => basename($userFall->video_url),
            'original_filename'       => 'AI Video - ' . Str::limit($userFall->prompt, 30),
            'path'                    => $userFall->video_url,
            'mime_type'               => 'video/mp4',
            'file_size'               => 0,
            'duration_ms'             => $metadata['duration_ms'] ?? null,
            'width'                   => $metadata['width'] ?? null,
            'height'                  => $metadata['height'] ?? null,
            'thumbnail_path'          => $thumbnailPath,
            'source'                  => 'ai_generated',
            'source_id'               => (string) $userFall->id,
        ]);
        $media->user_id = Auth::id();
        $media->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Video imported to editor'),
            'media'   => $media,
        ]);
    }

    public function checkCredits(Request $request): JsonResponse
    {
        $request->validate([
            'model' => ['required', 'string'],
        ]);

        try {
            $entityEnum = EntityEnum::fromSlug($request->input('model'));
            $driver = Entity::driver($entityEnum);

            // Sora uses inputSecond, others use inputVideoCount
            if (str_starts_with($request->input('model'), 'sora')) {
                $driver = $driver->inputSecond(4)->calculateCredit();
            } else {
                $driver = $driver->inputVideoCount(1)->calculateCredit();
            }

            return response()->json([
                'status'      => 'success',
                'has_credits' => $driver->hasCreditBalance(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'      => 'success',
                'has_credits' => false,
            ]);
        }
    }

    // =========================================================================
    //  AI VOICEOVER (TTS)
    // =========================================================================

    /**
     * Resolve which TTS provider should be used for this request.
     *
     * Prefers the explicit `settings_two.tts` column when set, otherwise falls
     * back to the first *enabled* `feature_tts_*` flag in admin → settings →
     * TTS. The previous fallback unconditionally returned `'openai'`, which
     * broke installs that only have ElevenLabs/Google/Azure/Speechify enabled
     * (the call would hit OpenAI and fail with 403 / missing model).
     */
    public static function resolveTtsProvider(SettingTwo $settingsTwo): string
    {
        $explicit = $settingsTwo->tts ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $candidates = [
            'openai'     => $settingsTwo->feature_tts_openai ?? false,
            'elevenlabs' => $settingsTwo->feature_tts_elevenlabs ?? false,
            'google'     => $settingsTwo->feature_tts_google ?? false,
            'azure'      => $settingsTwo->feature_tts_azure ?? false,
            'speechify'  => $settingsTwo->feature_tts_speechify ?? false,
        ];

        foreach ($candidates as $name => $enabled) {
            if ($enabled) {
                return $name;
            }
        }

        return 'openai';
    }

    /**
     * Return available voices for the admin-configured TTS provider.
     * For non-OpenAI providers, returns ElevenLabs voices (if enabled)
     * so the frontend can populate the voice dropdown dynamically.
     * OpenAI voices are rendered server-side in the Blade template.
     */
    public function voiceModels(): JsonResponse
    {
        $settingsTwo = SettingTwo::getCache();
        $ttsProvider = self::resolveTtsProvider($settingsTwo);

        // OpenAI voices are server-rendered in blade — no AJAX needed
        if ($ttsProvider === 'openai') {
            return response()->json(['status' => 'success', 'provider' => 'openai']);
        }

        // For non-OpenAI: load ElevenLabs voices if the feature is enabled
        $elevenlabsVoices = [];
        if ($settingsTwo->feature_tts_elevenlabs ?? false) {
            try {
                $service = new ElevenlabsService;
                $voiceList = $service->getVoices();

                if (is_string($voiceList)) {
                    $voiceList = json_decode($voiceList, true) ?? [];
                }

                foreach ($voiceList as $voice) {
                    $elevenlabsVoices[] = [
                        'voice_id' => $voice['voice_id'] ?? '',
                        'name'     => $voice['name'] ?? '',
                    ];
                }
            } catch (Exception $e) {
                Log::warning('VideoEditor: Failed to load ElevenLabs voices: ' . $e->getMessage());
            }
        }

        return response()->json([
            'status'            => 'success',
            'provider'          => $ttsProvider,
            'elevenlabs_voices' => $elevenlabsVoices,
        ]);
    }

    /**
     * Generate a voiceover using the admin-configured TTS provider.
     * Matches MagicAI's TTSController processing logic exactly.
     */
    public function generateVoice(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $settingsTwo = SettingTwo::getCache();
        $ttsProvider = self::resolveTtsProvider($settingsTwo);

        $request->validate([
            'voice'      => ['required', 'string'],
            'text'       => ['required', 'string', 'max:5000'],
            'model'      => ['nullable', 'string'],
            'lang'       => ['nullable', 'string'],
            'pace'       => ['nullable', 'string'],
            'platform'   => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $voice = $request->input('voice');
        $text = $request->input('text');

        // Determine entity for credit check based on admin's TTS setting
        if ($ttsProvider === 'openai') {
            $model = $request->input('model', 'tts-1');
            $entityEnum = EntityEnum::fromSlug($model);
        } else {
            // For Google/ElevenLabs/Azure/Speechify, the platform comes from the selected voice option
            $platform = $request->input('platform', $ttsProvider);
            $entityEnum = match ($platform) {
                'elevenlabs' => EntityEnum::ELEVENLABS,
                'google'     => EntityEnum::GOOGLE,
                'azure'      => EntityEnum::AZURE,
                'speechify'  => EntityEnum::Speechify,
                default      => EntityEnum::GOOGLE,
            };
        }

        $driver = Entity::driver($entityEnum)->inputVoiceCount(1)->calculateCredit();

        if (! $driver->hasCreditBalanceForInput()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Insufficient credits to generate voiceover.'),
            ], 402);
        }

        try {
            // Route strictly by the admin-resolved provider. The request's
            // `platform` field used to drive this, but a stale/mismatched
            // voice option (e.g. Google voice selected while only ElevenLabs
            // is enabled) would then attempt to call an unconfigured provider
            // — Google TTS in particular crashed on `file_get_contents` of a
            // null gcs_file path.
            $lang = $request->input('lang', 'en-US');
            $pace = $request->input('pace', 'medium');

            $audioContent = match ($ttsProvider) {
                'openai'     => $this->processOpenAiTts($voice, $text, $model),
                'elevenlabs' => $this->processElevenLabsTts($voice, $text, $pace),
                'google'     => $this->processGoogleTts($voice, $text, $lang, $pace),
                default      => throw new RuntimeException(
                    sprintf('TTS provider "%s" is not supported by VideoEditor.', $ttsProvider)
                ),
            };
        } catch (Exception $e) {
            Log::error('VideoEditor TTS error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => __('Failed to generate voiceover. Please try again.'),
            ], 500);
        }

        // Store audio file in proper subdirectory
        $userId = Auth::id();
        $audioName = $userId . '-voice-' . Str::uuid() . '.mp3';
        $audioPath = 'video-editor/audio/' . $userId . '/' . $audioName;
        Storage::disk('public')->put($audioPath, $audioContent);

        $driver->decreaseCredit();

        // Create media entry
        $media = new VideoEditorMedia([
            'video_editor_project_id' => $request->input('project_id'),
            'type'                    => 'audio',
            'filename'                => $audioName,
            'original_filename'       => 'AI Voice - ' . Str::limit($text, 30),
            'path'                    => $audioPath,
            'mime_type'               => 'audio/mpeg',
            'file_size'               => Storage::disk('public')->size($audioPath),
            'source'                  => 'ai_generated',
        ]);
        $media->user_id = $userId;
        $media->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Voiceover generated successfully.'),
            'media'   => $media,
        ]);
    }

    /**
     * OpenAI TTS — same as TTSController::processOpenAISpeech
     */
    private function processOpenAiTts(string $voice, string $text, string $model): string
    {
        $apiKey = ApiHelper::setOpenAiKey();
        $client = new Client;

        $response = $client->request('POST', 'https://api.openai.com/v1/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'input' => $text,
                'voice' => $voice,
            ],
        ]);

        return (string) $response->getBody();
    }

    /**
     * ElevenLabs TTS — same as TTSController::processElevenLabsSpeech
     */
    private function processElevenLabsTts(string $voiceId, string $text, string $pace = 'medium'): string
    {
        $settingsTwo = SettingTwo::getCache();
        $apiKey = $settingsTwo->elevenlabs_api_key;
        $client = new Client;

        // Pace is a style value 0-100 for ElevenLabs (from the voiceover page JS)
        $styleValue = is_numeric($pace) ? ((float) $pace / 100) : 0.5;

        $response = $client->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/' . $voiceId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'xi-api-key'   => $apiKey,
            ],
            'json' => [
                'text'           => $text,
                'model_id'       => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'similarity_boost'  => 0.75,
                    'stability'         => 0.95,
                    'style'             => $styleValue,
                    'use_speaker_boost' => true,
                ],
            ],
        ]);

        return (string) $response->getBody();
    }

    /**
     * Google TTS — same as TTSController::processGoogleSpeech (uses SSML)
     */
    private function processGoogleTts(string $voice, string $text, string $lang, string $pace = 'medium'): string
    {
        $settings = Setting::getCache();

        $client = new TextToSpeechClient([
            'credentials' => storage_path($settings->gcs_file),
            'project_id'  => $settings->gcs_name,
        ]);

        // Sanitize SSML attributes to prevent injection
        $lang = preg_replace('/[^a-zA-Z\-]/', '', $lang);
        $pace = in_array($pace, ['x-slow', 'slow', 'medium', 'fast', 'x-fast'], true) ? $pace : 'medium';
        $voice = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $voice);

        $ssml = '<speak><lang xml:lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><prosody rate="' . htmlspecialchars($pace, ENT_QUOTES, 'UTF-8') . '"><voice name="' . htmlspecialchars($voice, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($text) . '</voice></prosody></lang></speak>';

        $synthesisInput = (new SynthesisInput)->setSsml($ssml);
        $voiceParams = (new VoiceSelectionParams)
            ->setLanguageCode($lang)
            ->setName($voice);
        $audioConfig = (new AudioConfig)
            ->setAudioEncoding(AudioEncoding::MP3);

        return $client->synthesizeSpeech($synthesisInput, $voiceParams, $audioConfig)->getAudioContent();
    }

    // =========================================================================
    //  AI MUSIC GENERATION
    // =========================================================================

    /**
     * Generate music using whichever provider AiMusicPro is configured to use
     * (ai_music_pro_default_provider setting). Mirrors AiMusicProController's
     * provider routing so the editor stays in sync with AiMusicPro's behaviour.
     */
    public function generateMusic(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        set_time_limit(0);
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', 36000);

        $provider = setting('ai_music_pro_default_provider', 'elevenlabs');

        $rules = [
            'prompt'     => ['required', 'string', 'max:2000'],
            'style'      => ['nullable', 'string', 'max:190'],
            'project_id' => ['nullable', 'integer'],
        ];

        if ($provider === 'elevenlabs') {
            $rules['duration'] = ['nullable', 'integer', 'min:10', 'max:300'];
        } else {
            $rules['lyrics'] = ['nullable', 'string', 'max:5000'];
            $rules['images'] = ['nullable', 'array', 'max:10'];
            $rules['images.*'] = ['image', 'max:25600'];
            if ($provider === 'lyria3pro') {
                $rules['output_format'] = ['nullable', 'in:mp3,wav'];
            }
        }

        $request->validate($rules);

        $entityEnum = match ($provider) {
            'lyria3clip' => EntityEnum::LYRIA_3_CLIP,
            'lyria3pro'  => EntityEnum::LYRIA_3_PRO,
            default      => EntityEnum::ELEVENLABS_AI_MUSIC,
        };

        if ($provider === 'elevenlabs') {
            $driver = Entity::driver($entityEnum)->inputMinute(1)->calculateCredit();
        } else {
            $estimatedSeconds = $provider === 'lyria3clip' ? 30 : 120;
            $driver = Entity::driver($entityEnum)->inputSecond($estimatedSeconds)->calculateCredit();
        }

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 402);
        }

        try {
            $result = $provider === 'elevenlabs'
                ? $this->generateMusicWithElevenlabs($request)
                : $this->generateMusicWithLyria(
                    $request,
                    $provider === 'lyria3clip' ? 'lyria-3-clip-preview' : 'lyria-3-pro-preview'
                );
        } catch (Exception $e) {
            Log::error('VideoEditor Music error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => __('Failed to generate music: ') . $e->getMessage(),
            ], 500);
        }

        // Charge actual usage
        if ($provider === 'elevenlabs') {
            // ElevenlabsAIMusicDriver::inputMinute() is strictly typed int.
            // Round up so partial-minute durations still bill a full minute
            // (matches the driver's billing semantics elsewhere in the app).
            $driver->inputMinute((int) ceil((float) $result['duration']))->calculateCredit()->decreaseCredit();
        } else {
            $driver->inputSecond((int) round((float) ($result['duration_seconds'] ?? ($result['duration'] * 60))))->calculateCredit()->decreaseCredit();
        }

        // Persist as VideoEditorMedia so the audio appears in the library
        $fileUrl = $result['file_url'];
        $media = new VideoEditorMedia([
            'video_editor_project_id' => $request->input('project_id'),
            'type'                    => 'audio',
            'filename'                => basename($fileUrl),
            'original_filename'       => 'AI Music - ' . Str::limit($request->input('prompt'), 30),
            'path'                    => $fileUrl,
            'mime_type'               => 'audio/mpeg',
            'file_size'               => 0,
            'source'                  => 'ai_generated',
        ]);
        $media->user_id = Auth::id();
        $media->save();

        return response()->json([
            'status'  => 'success',
            'message' => __('Music generated successfully.'),
            'media'   => $media,
        ]);
    }

    private function generateMusicWithElevenlabs(Request $request): array
    {
        $prompt = (string) $request->input('prompt', '');

        if ($request->filled('style')) {
            $prompt .= ', in the style of ' . $request->input('style');
        }

        $param = [
            'prompt'   => $prompt,
            'model_id' => 'music_v1',
        ];

        if ($request->filled('duration')) {
            $param['music_length_ms'] = (int) $request->input('duration') * 1000;
        }

        $elevenlabsService = new PackageElevenlabsService(ApiHelper::setElevenlabsKey());
        $response = $elevenlabsService->AiMusicModel()->submit($param);
        $fileUrl = $response->getData(true)['file_url'] ?? null;

        if (empty($fileUrl)) {
            throw new RuntimeException(__('Failed to generate music. Please try again.'));
        }

        $durationMinutes = $request->filled('duration')
            ? $request->input('duration') / 60
            : 0.5;

        return [
            'file_url'         => $fileUrl,
            'duration'         => $durationMinutes,
            'duration_seconds' => $durationMinutes * 60,
        ];
    }

    private function generateMusicWithLyria(Request $request, string $model): array
    {
        $prompt = (string) $request->input('prompt', '');

        if ($request->filled('style')) {
            $prompt .= ', in the style of ' . $request->input('style');
        }

        if ($request->filled('lyrics')) {
            $prompt .= "\n\n" . $request->input('lyrics');
        }

        $images = $request->file('images', []);
        $outputFormat = $request->input('output_format', 'mp3');

        $service = new LyriaMusicService;
        $result = $service->generate($model, $prompt, $images, $outputFormat);

        // LyriaMusicService returns ['file_url' => ..., 'duration' => minutes, ...]
        $result['duration_seconds'] = ($result['duration'] ?? 0) * 60;

        return $result;
    }
}
