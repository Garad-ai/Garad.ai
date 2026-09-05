<?php

namespace App\Extensions\AiVideoPro\System\Http\Controllers;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiVideoPro\System\Http\Requests\StoreAiVideoProRequest;
use App\Extensions\AiVideoPro\System\Jobs\DownloadVideoToLocalJob;
use App\Extensions\AiVideoPro\System\Models\UserFall;
use App\Extensions\AiVideoPro\System\Services\ModelConfigurationService;
use App\Extensions\AiVideoPro\System\Services\SoraService;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Helpers\Classes\ApiHelper;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Packages\FalAI\FalAIService as PackageFalAIService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class AiVideoProController extends Controller
{
    private const UPLOAD_DISK = 'public';

    private bool $isAjax = false;

    private const RANDOM_STRING_LENGTH = 12;

    private PackageFalAIService $falAIService;

    public function __construct()
    {
        $this->falAIService = new PackageFalAIService(ApiHelper::setFalAIKey());
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $videos = UserFall::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        $todayVideos = $videos->filter(fn ($v) => $v->created_at->isToday())->values();
        $previousVideos = $videos->filter(fn ($v) => ! $v->created_at->isToday())->values();

        $inProgress = $videos->filter(fn ($v) => in_array($v->status, ['IN_QUEUE', 'queued']))->pluck('id')->toArray();

        $completedVideos = $videos->filter(fn ($v) => $v->status === 'complete')->values();
        $videosJson = $completedVideos->map(fn ($v) => [
            'id'                 => $v->id,
            'prompt'             => $v->prompt,
            'video_url'          => $v->video_url,
            'model'              => $v->model,
            'duration_seconds'   => $v->duration_seconds,
            'created_at'         => $v->created_at->toIso8601String(),
            'formatted_duration' => $v->formatted_duration,
            'resolution'         => $v->resolution,
            'aspect_ratio'       => $v->aspect_ratio,
        ])->values();

        $models = ModelConfigurationService::getConfig();

        // Keep $list for backwards compatibility with video-item partial
        $list = $videos->toArray();

        $socialMediaRegistered = MarketplaceHelper::isRegistered('social-media');
        $connectedVideoPlatforms = collect();

        if ($socialMediaRegistered && class_exists(SocialMediaPlatform::class)) {
            $connectedVideoPlatforms = SocialMediaPlatform::query()
                ->where('user_id', auth()->id())
                ->connected()
                ->whereIn('platform', ['tiktok', 'youtube', 'youtube-shorts'])
                ->get();
        }

        return view('ai-video-pro::index', compact(
            'list', 'todayVideos', 'previousVideos', 'inProgress', 'models', 'videosJson',
            'socialMediaRegistered', 'connectedVideoPlatforms'
        ));
    }

    public function delete(string $id): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['message' => 'This feature is disabled in demo mode.', 'type' => 'error']);
        }

        $model = UserFall::query()->findOrFail($id);

        $model->delete();

        return back()->with(['message' => 'Deleted Successfully.', 'type' => 'success']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['message' => __('This feature is disabled in demo mode.')], 403);
        }

        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $deleted = UserFall::query()
            ->where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json([
            'message' => __('Deleted :count video(s) successfully.', ['count' => $deleted]),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAiVideoProRequest $request): RedirectResponse|JsonResponse
    {
        $this->isAjax = $request->expectsJson() || $request->ajax();

        if (Helper::appIsDemo()) {
            return $this->errorResponse(__('This feature is disabled in demo mode.'));
        }

        if (! ApiHelper::setFalAIKey()) {
            return $this->errorResponse(__('Please set FAL AI key.'));
        }

        $validated = $request->validated();
        $entityEnum = EntityEnum::fromSlug($validated['feature']);

        try {
            $driver = $this->calculateCredits($validated['action'], $entityEnum, $validated);
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            Log::error('Credit calculation failed', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return $this->errorResponse($e->getMessage());
        }

        try {
            return $this->processGeneration($validated, $entityEnum, $driver);
        } catch (Exception $e) {
            Log::error('Video generation failed', [
                'error'   => $e->getMessage(),
                'action'  => $validated['action'],
                'feature' => $validated['feature'],
                'user_id' => auth()->id(),
            ]);

            return $this->errorResponse(__('Generation failed: ') . $e->getMessage());
        }
    }

    private function processGeneration(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $handlers = [
            'sora'                => 'handleSora',
            'veo'                 => 'handleVeo',
            'kling'               => 'handleKling',
            'luma-dream-machine'  => 'handleLumaDreamMachine',
            'minimax'             => 'handleMinimax',
            'grok-imagine-video'  => 'handleGrokImagineVideo',
            'seedance'            => 'handleSeedance',
            'gemini-omni-flash'   => 'handleGeminiOmniFlash',
            'background-removal'  => 'handleBackgroundRemoval',
        ];

        $action = $validated['action'];

        if (! isset($handlers[$action])) {
            return $this->errorResponse(__('Invalid AI model selected.'));
        }

        return $this->{$handlers[$action]}($validated, $entityEnum, $driver);
    }

    private function calculateCredits(string $action, EntityEnum $entityEnum, array $validated)
    {
        if ($action === 'sora') {
            $seconds = (int) ($validated['sora_seconds'] ?? 4);

            return Entity::driver($entityEnum)->inputSecond($seconds)->calculateCredit();
        }

        $seconds = $this->extractDurationSeconds($action, $validated);

        if ($seconds !== null) {
            return Entity::driver($entityEnum)->inputVideoCount($seconds)->calculateCredit();
        }

        return Entity::driver($entityEnum)->inputVideoCount(1)->calculateCredit();
    }

    private function extractDurationSeconds(string $action, array $validated): ?int
    {
        return match ($action) {
            'veo'                => isset($validated['duration']) ? (int) $validated['duration'] : null,
            'gemini-omni-flash'  => isset($validated['duration']) ? (int) $validated['duration'] : null,
            'grok-imagine-video' => isset($validated['grok_video_duration']) ? (int) $validated['grok_video_duration'] : null,
            'kling'              => $this->extractKlingDurationSeconds($validated),
            default              => null,
        };
    }

    private function extractKlingDurationSeconds(array $validated): ?int
    {
        return match (true) {
            isset($validated['kling25turbo_duration']) => (int) $validated['kling25turbo_duration'],
            isset($validated['kling26pro_duration'])   => (int) $validated['kling26pro_duration'],
            isset($validated['kling_v3_duration'])     => (int) $validated['kling_v3_duration'],
            default                                    => null,
        };
    }

    private function handleSora(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        // Don't upload yet - pass the UploadedFile object directly
        $imageFile = $validated['image_url'] ?? null;

        $params = [
            'prompt'    => $validated['prompt'],
            'model'     => $entityEnum->value,
            'seconds'   => (int) ($validated['sora_seconds'] ?? 4),
            'size'      => $validated['sora_size'] ?? '720x1280',
            'image_url' => $imageFile, // Pass the UploadedFile object, not the uploaded path
        ];

        $response = SoraService::generate($params);

        if ($this->isErrorResponse($response)) {
            return $this->errorResponse(
                $response['error']['message'] ?? $response['message'] ?? __('Generation Failed')
            );
        }

        // Only upload for database record after successful generation
        $uploadedImageUrl = $imageFile ? $this->uploadSingleFile($validated, 'image_url', true) : null;

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $entityEnum->value,
            $response,
            $uploadedImageUrl,
            [
                'duration_seconds' => (int) ($validated['sora_seconds'] ?? 4),
                'resolution'       => $validated['sora_size'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Created Successfully.'), ['video_id' => $entry->id]);
    }

    private function handleVeo(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $legacyFeatures = ['veo2'];

        if (in_array($validated['feature'], $legacyFeatures, true)) {
            return $this->handleLegacyVeo($validated, $entityEnum, $driver);
        }

        $payload = $this->buildVeoPayload($validated);
        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('VEO generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            (array) $resData->resData,
            null,
            [
                'duration_seconds' => isset($validated['duration']) ? (int) rtrim($validated['duration'], 's') : null,
                'resolution'       => $validated['resolution'] ?? null,
                'aspect_ratio'     => $validated['aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleLegacyVeo(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildVeoPayload($validated);
        $response = FalAIService::veo2Generate($payload['prompt']);
        if ($response->failed()) {
            return $this->errorResponse(
                $response->status() . ' ' . $response->reason() . ': ' .
                $response->json('detail', __('Unknown error occurred'))
            );
        }
        $jsonRes = $response->json();
        if (isset($jsonRes['status']) && $jsonRes['status'] === 'error') {
            return $this->errorResponse($jsonRes['message']);
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            (array) $jsonRes,
            null,
            [
                'duration_seconds' => isset($validated['duration']) ? (int) rtrim($validated['duration'], 's') : null,
                'aspect_ratio'     => $validated['aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleKling(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $legacyFeatures = ['kling', 'klingImage', 'klingV21', 'kling-video'];
        $kling26ProFeatures = [
            EntityEnum::KLING_2_6_PRO_TTV->value,
            EntityEnum::KLING_2_6_PRO_ITV->value,
            EntityEnum::KLING_2_6_PRO_MOTION_CONTROL->value,
            EntityEnum::KLING_2_6_STANDARD_MOTION_CONTROL->value,
        ];
        $klingV3Features = [
            EntityEnum::KLING_3_PRO_TTV->value,
            EntityEnum::KLING_3_PRO_ITV->value,
            EntityEnum::KLING_3_STANDARD_TTV->value,
            EntityEnum::KLING_3_STANDARD_ITV->value,
        ];

        if (in_array($validated['feature'], $legacyFeatures, true)) {
            return $this->handleLegacyKling($validated, $entityEnum, $driver);
        }

        if (in_array($validated['feature'], $klingV3Features, true)) {
            return $this->handleKlingV3($validated, $entityEnum, $driver);
        }

        if (in_array($validated['feature'], $kling26ProFeatures, true)) {
            return $this->handleKling26Pro($validated, $entityEnum, $driver);
        }

        return $this->handleKling25($validated, $entityEnum, $driver);
    }

    private function handleLegacyKling(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $feature = $validated['feature'];
        $url = null;

        if (in_array($feature, ['klingImage', 'klingV21'], true)) {
            $url = $this->uploadSingleFile($validated, 'image_url', true);
        } elseif ($feature === 'kling-video') {
            $url = $this->uploadSingleFile($validated, 'video', true);
        }

        $method = $feature . 'Generate';
        if (! method_exists(FalAIService::class, $method)) {
            return $this->errorResponse(__('Invalid Kling method.'));
        }

        $response = FalAIService::$method($validated['prompt'], $url);

        if ($this->isErrorResponse($response)) {
            return $this->errorResponse($response['message'] ?? __('Generation Failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $feature,
            $response,
            $url
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Created Successfully.'), ['video_id' => $entry->id]);
    }

    private function handleKling25(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildKling25Payload($validated);

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Kling generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'] ?? null,
            $validated['feature'],
            (array) $resData->resData,
            $payload['image_url'] ?? null,
            [
                'duration_seconds' => (int) ($validated['kling25turbo_duration'] ?? 5),
                'aspect_ratio'     => $validated['kling25turbo_aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Kling video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleKling26Pro(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildKling26ProPayload($validated);

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Kling generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            (array) $resData->resData,
            $payload['image_url'] ?? null,
            [
                'duration_seconds' => (int) ($validated['kling26pro_duration'] ?? 5),
                'aspect_ratio'     => $validated['kling26pro_aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Kling video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleKlingV3(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildKlingV3Payload($validated);
        $promptForStorage = trim((string) ($validated['prompt'] ?? ''));
        if ($promptForStorage === '' && ! empty($validated['multi_prompt_json'])) {
            $promptForStorage = '[multi_prompt]';
        }

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Kling generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $promptForStorage,
            $validated['feature'],
            (array) $resData->resData,
            $payload['start_image_url'] ?? null,
            [
                'duration_seconds' => (int) ($validated['kling_v3_duration'] ?? 5),
                'aspect_ratio'     => $validated['kling_v3_aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Kling video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleLumaDreamMachine(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $response = FalAIService::minimaxGenerate($validated['prompt']);

        if ($this->isErrorResponse($response)) {
            return $this->errorResponse($response['message'] ?? __('Generation Failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            $response
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Created Successfully.'), ['video_id' => $entry->id]);
    }

    private function handleMinimax(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $response = FalAIService::minimaxGenerate($validated['prompt']);

        if ($this->isErrorResponse($response)) {
            return $this->errorResponse($response['message'] ?? __('Generation Failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            $response
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Created Successfully.'), ['video_id' => $entry->id]);
    }

    private function handleGrokImagineVideo(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildGrokImagineVideoPayload($validated);

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Grok Imagine Video generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            (array) $resData->resData,
            $payload['image_url'] ?? null,
            [
                'duration_seconds' => isset($validated['grok_video_duration']) ? (int) $validated['grok_video_duration'] : null,
                'resolution'       => $validated['grok_video_resolution'] ?? null,
                'aspect_ratio'     => $validated['aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Grok Imagine Video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleSeedance(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildSeedancePayload($validated);

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Seedance 2.0 video generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            (array) $resData->resData,
            $payload['image_url'] ?? null,
            [
                'duration_seconds' => isset($validated['seedance_duration']) && $validated['seedance_duration'] !== 'auto'
                    ? (int) $validated['seedance_duration']
                    : null,
                'resolution'       => $validated['seedance_resolution'] ?? null,
                'aspect_ratio'     => $validated['aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Seedance 2.0 video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleGeminiOmniFlash(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $payload = $this->buildGeminiOmniFlashPayload($validated);

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Gemini Omni Flash video generation failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            $validated['prompt'],
            $validated['feature'],
            (array) $resData->resData,
            $payload['image_url'] ?? null,
            [
                'duration_seconds' => isset($validated['duration']) ? (int) $validated['duration'] : null,
                'aspect_ratio'     => $validated['aspect_ratio'] ?? null,
            ]
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Gemini Omni Flash video generation started successfully.'), ['video_id' => $entry->id]);
    }

    private function handleBackgroundRemoval(array $validated, EntityEnum $entityEnum, $driver): RedirectResponse|JsonResponse
    {
        $videoUrl = $this->uploadSingleFile($validated, 'video_url', true);

        if (! $videoUrl) {
            return $this->errorResponse(__('Video file is required.'));
        }

        $payload = [
            'video_url'               => $videoUrl,
            'output_codec'            => $validated['output_codec'] ?? 'vp9',
            'refine_foreground_edges' => (bool) ($validated['refine_foreground_edges'] ?? true),
            'subject_is_person'       => (bool) ($validated['subject_is_person'] ?? true),
        ];

        $response = $this->falAIService->textToVideoModel($entityEnum)->submit($payload);
        $resData = $response->getData();

        if (isset($resData->status) && $resData->status === 'error') {
            return $this->errorResponse($resData->message ?? __('Video background removal failed'));
        }

        $entry = $this->createUserFall(
            auth()->id(),
            __('Video Background Removal'),
            $validated['feature'],
            (array) $resData->resData,
        );

        $driver->decreaseCredit();

        return $this->successResponse(__('Video background removal started successfully.'), ['video_id' => $entry->id]);
    }

    // ============== PAYLOAD BUILDERS ==============

    private function buildVeoPayload(array $validated): array
    {
        $payload = ['prompt' => $validated['prompt']];

        // Add simple fields
        $simpleFields = ['duration', 'resolution', 'aspect_ratio'];
        foreach ($simpleFields as $field) {
            if (isset($validated[$field])) {
                $payload[$field] = $validated[$field];
            }
        }

        if (isset($validated['feature']) && str_starts_with($validated['feature'], 'veo3.1/')) {
            $payload['mode'] = $validated['feature'];
        }

        // Handle boolean fields
        if (isset($validated['generate_audio'])) {
            $payload['generate_audio'] = filter_var($validated['generate_audio'], FILTER_VALIDATE_BOOLEAN);
        }

        // Handle file uploads
        $payload = $this->addVeoFileUploads($payload, $validated);

        // Add advanced options
        $payload = $this->addAdvancedOptions($payload, $validated);

        return $this->removeEmptyValues($payload);
    }

    private function addVeoFileUploads(array $payload, array $validated): array
    {
        $feature = $validated['feature'];

        if (str_contains($feature, 'image-to-video') && ! str_contains($feature, 'first-last-frame')) {
            $payload['image_url'] = $this->uploadSingleFile($validated, 'image_url', true);
        } elseif (str_contains($feature, 'first-last-frame')) {
            $payload['first_frame_url'] = $this->uploadSingleFile($validated, 'first_frame_url', true);
            $payload['last_frame_url'] = $this->uploadSingleFile($validated, 'last_frame_url', true);
        } elseif (str_contains($feature, 'reference-to-video')) {
            $payload['image_urls'] = $this->uploadMultipleFiles($validated, 'image_urls');
        }

        return $payload;
    }

    private function addAdvancedOptions(array $payload, array $validated): array
    {
        $booleanFields = ['enhance_prompt', 'auto_fix'];
        foreach ($booleanFields as $field) {
            if (isset($validated[$field])) {
                $payload[$field] = filter_var($validated[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (isset($validated['seed']) && $validated['seed'] !== '') {
            $payload['seed'] = (int) $validated['seed'];
        }

        if (! empty($validated['negative_prompt'])) {
            $payload['negative_prompt'] = $validated['negative_prompt'];
        }

        return $payload;
    }

    private function buildKling25Payload(array $validated): array
    {
        $payload = [
            'prompt'       => $validated['prompt'],
            'duration'     => (int) ($validated['kling25turbo_duration'] ?? 5),
            'aspect_ratio' => $validated['kling25turbo_aspect_ratio'] ?? '16:9',
        ];

        $feature = $validated['feature'];

        // Handle image upload for image-to-video modes
        if (isset($validated['image_url']) && str_contains($feature, 'image-to-video')) {
            $payload['image_url'] = $this->uploadSingleFile($validated, 'image_url', true);
        }

        // Add Pro TTV specific parameters
        if ($feature === EntityEnum::KLING_2_5_TURBO_PRO_TTV->value) {
            if (! empty($validated['camera_movement'])) {
                $payload['camera_movement'] = ['type' => $validated['camera_movement']];
            }
            if (isset($validated['cfg_scale'])) {
                $payload['cfg_scale'] = (float) $validated['cfg_scale'];
            }
        }

        // Add optional parameters
        if (isset($validated['seed']) && $validated['seed'] !== null) {
            $payload['seed'] = (int) $validated['seed'];
        }

        if (! empty($validated['negative_prompt'])) {
            $payload['negative_prompt'] = $validated['negative_prompt'];
        }

        // Add loop parameter for Pro modes
        if (isset($validated['loop']) && str_contains($feature, 'pro')) {
            $payload['loop'] = filter_var($validated['loop'], FILTER_VALIDATE_BOOLEAN);
        }

        return $this->removeEmptyValues($payload);
    }

    private function buildKling26ProPayload(array $validated): array
    {
        $feature = $validated['feature'];

        // Handle motion control separately - it has different parameters
        if (str_contains($feature, 'motion-control')) {
            $payload = [
                'image_url'              => $this->uploadSingleFile($validated, 'image_url', true),
                'video_url'              => $this->uploadSingleFile($validated, 'video_url', true),
                'character_orientation'  => $validated['character_orientation'] ?? 'image',
            ];

            // Prompt is optional for motion control
            if (! empty($validated['prompt'])) {
                $payload['prompt'] = $validated['prompt'];
            }

            $payload['keep_original_sound'] = filter_var($validated['keep_original_sound'] ?? true, FILTER_VALIDATE_BOOLEAN);

            return $this->removeEmptyValues($payload);
        }

        // Standard text-to-video and image-to-video handling
        $payload = [
            'prompt'       => $validated['prompt'],
            'duration'     => (int) ($validated['kling26pro_duration'] ?? 5),
            'aspect_ratio' => $validated['kling26pro_aspect_ratio'] ?? '16:9',
        ];

        // Handle image upload for image-to-video modes
        if (isset($validated['image_url']) && str_contains($feature, 'image-to-video')) {
            $payload['image_url'] = $this->uploadSingleFile($validated, 'image_url', true);
        }

        if (isset($validated['cfg_scale'])) {
            $payload['cfg_scale'] = (float) $validated['cfg_scale'];
        }

        if (! empty($validated['negative_prompt'])) {
            $payload['negative_prompt'] = $validated['negative_prompt'];
        }

        return $this->removeEmptyValues($payload);
    }

    private function buildKlingV3Payload(array $validated): array
    {
        $feature = $validated['feature'];
        $payload = [
            'duration'     => (int) ($validated['kling_v3_duration'] ?? 5),
            'aspect_ratio' => $validated['kling_v3_aspect_ratio'] ?? '16:9',
        ];

        $prompt = trim((string) ($validated['prompt'] ?? ''));
        if ($prompt !== '') {
            $payload['prompt'] = $prompt;
        }

        if (str_contains($feature, 'image-to-video')) {
            $payload['start_image_url'] = $this->uploadSingleFile($validated, 'start_image_url', true);

            if (isset($validated['end_image_url'])) {
                $payload['end_image_url'] = $this->uploadSingleFile($validated, 'end_image_url', true);
            }
        }

        $multiPrompt = $this->decodeJsonArrayField($validated, 'multi_prompt_json', 'Multi Prompt');
        if (! empty($multiPrompt)) {
            $payload['multi_prompt'] = $multiPrompt;
            unset($payload['prompt']); // API expects either prompt or multi_prompt

            if (! empty($validated['kling_v3_shot_type'])) {
                $payload['shot_type'] = $validated['kling_v3_shot_type'];
            }
        }

        $voiceIds = $this->decodeJsonArrayField($validated, 'voice_ids_json', 'Voice IDs');
        if (! empty($voiceIds)) {
            if (count($voiceIds) > 2) {
                throw new RuntimeException(__('Voice IDs JSON can contain at most 2 IDs.'));
            }

            $payload['voice_ids'] = array_values(array_filter($voiceIds, static fn ($id) => is_string($id) && trim($id) !== ''));
        }

        $elements = $this->decodeJsonArrayField($validated, 'elements_json', 'Elements');
        if (! empty($elements)) {
            $payload['elements'] = $elements;
        }

        if (isset($validated['generate_audio'])) {
            $payload['generate_audio'] = filter_var($validated['generate_audio'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($validated['kling_v3_cfg_scale'])) {
            $payload['cfg_scale'] = (float) $validated['kling_v3_cfg_scale'];
        }

        if (! empty($validated['negative_prompt'])) {
            $payload['negative_prompt'] = $validated['negative_prompt'];
        }

        if (! isset($payload['prompt']) && ! isset($payload['multi_prompt'])) {
            throw new RuntimeException(__('Please provide either a prompt or valid Multi Prompt JSON.'));
        }

        return $this->removeEmptyValues($payload);
    }

    private function buildGrokImagineVideoPayload(array $validated): array
    {
        $payload = ['prompt' => $validated['prompt']];

        // Image upload only for image-to-video
        if (str_contains($validated['feature'], 'image-to-video') && isset($validated['image_url'])) {
            $payload['image_url'] = $this->uploadSingleFile($validated, 'image_url', true);
        }

        if (isset($validated['grok_video_duration'])) {
            $payload['duration'] = (int) $validated['grok_video_duration'];
        }

        if (isset($validated['aspect_ratio'])) {
            $payload['aspect_ratio'] = $validated['aspect_ratio'];
        }

        if (isset($validated['grok_video_resolution'])) {
            $payload['resolution'] = $validated['grok_video_resolution'];
        }

        return $this->removeEmptyValues($payload);
    }

    private function buildSeedancePayload(array $validated): array
    {
        $payload = ['prompt' => $validated['prompt']];
        $feature = $validated['feature'];

        if (isset($validated['seedance_duration'])) {
            $payload['duration'] = $validated['seedance_duration'];
        }

        if (isset($validated['aspect_ratio'])) {
            $payload['aspect_ratio'] = $validated['aspect_ratio'];
        }

        if (isset($validated['seedance_resolution'])) {
            $payload['resolution'] = $validated['seedance_resolution'];
        }

        if (isset($validated['generate_audio'])) {
            $payload['generate_audio'] = filter_var($validated['generate_audio'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($validated['seed']) && $validated['seed'] !== '') {
            $payload['seed'] = (int) $validated['seed'];
        }

        // Image-to-Video: single image + optional end image
        if (str_contains($feature, 'image-to-video')) {
            $payload['image_url'] = $this->uploadSingleFile($validated, 'image_url', true);
            $endImageUrl = $this->uploadSingleFile($validated, 'end_image_url', true);
            if ($endImageUrl) {
                $payload['end_image_url'] = $endImageUrl;
            }
        }

        // Reference-to-Video: multiple images, videos, audio
        if (str_contains($feature, 'reference-to-video')) {
            $imageUrls = $this->uploadMultipleFiles($validated, 'image_urls');
            if (! empty($imageUrls)) {
                $payload['image_urls'] = $imageUrls;
            }
            $videoUrls = $this->uploadMultipleFiles($validated, 'video_urls');
            if (! empty($videoUrls)) {
                $payload['video_urls'] = $videoUrls;
            }
            $audioUrls = $this->uploadMultipleFiles($validated, 'audio_urls');
            if (! empty($audioUrls)) {
                $payload['audio_urls'] = $audioUrls;
            }
        }

        return $this->removeEmptyValues($payload);
    }

    private function buildGeminiOmniFlashPayload(array $validated): array
    {
        $payload = ['prompt' => $validated['prompt']];
        $feature = $validated['feature'];

        if (isset($validated['aspect_ratio'])) {
            $payload['aspect_ratio'] = $validated['aspect_ratio'];
        }

        if (isset($validated['duration'])) {
            $payload['duration'] = (int) $validated['duration'];
        }

        // Image-to-Video: single reference image
        if (str_contains($feature, 'image-to-video')) {
            $payload['image_url'] = $this->uploadSingleFile($validated, 'image_url', true);
        }

        // Reference-to-Video: multiple reference images
        if (str_contains($feature, 'reference-to-video')) {
            $imageUrls = $this->uploadMultipleFiles($validated, 'image_urls');
            if (! empty($imageUrls)) {
                $payload['image_urls'] = $imageUrls;
            }
        }

        return $this->removeEmptyValues($payload);
    }

    // ============== FILE UPLOAD HELPERS ==============

    private function uploadSingleFile(array $validated, string $fieldName, bool $returnUrl = false): ?string
    {
        if (! isset($validated[$fieldName]) || ! ($validated[$fieldName] instanceof UploadedFile)) {
            return null;
        }

        $file = $validated[$fieldName];

        try {
            $fileName = $this->generateUniqueFileName($file);
            $content = file_get_contents($file->getRealPath());

            Storage::disk(self::UPLOAD_DISK)->put($fileName, $content);

            if ($returnUrl) {
                return Helper::parseUrl(config('app.url') . '/uploads/' . $fileName);
            }

            return $fileName;
        } catch (Exception $e) {
            Log::error('File upload failed', [
                'field' => $fieldName,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(__('File upload failed: ') . $e->getMessage());
        }
    }

    private function uploadMultipleFiles(array $validated, string $fieldName): array
    {
        if (! isset($validated[$fieldName]) || ! is_array($validated[$fieldName])) {
            return [];
        }

        $urls = [];
        foreach ($validated[$fieldName] as $file) {
            if ($file instanceof UploadedFile) {
                try {
                    $fileName = $this->generateUniqueFileName($file);
                    $content = file_get_contents($file->getRealPath());

                    Storage::disk(self::UPLOAD_DISK)->put($fileName, $content);
                    $urls[] = Helper::parseUrl(config('app.url') . '/uploads/' . $fileName);
                } catch (Exception $e) {
                    Log::warning('Multiple file upload - single file failed', [
                        'field' => $fieldName,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }
        }

        return $urls;
    }

    private function generateUniqueFileName(UploadedFile $file): string
    {
        return Str::random(self::RANDOM_STRING_LENGTH) . '.' . $file->guessExtension();
    }

    private function isErrorResponse($response): bool
    {
        if (! is_array($response)) {
            return false;
        }

        return isset($response['error']) ||
            (isset($response['status']) && $response['status'] === 'error') ||
            (isset($response['status']) && $response['status'] === 'failed');
    }

    private function removeEmptyValues(array $data): array
    {
        return array_filter($data, static function ($value) {
            if (is_null($value) || $value === '') {
                return false;
            }
            if (is_array($value) && empty($value)) {
                return false;
            }

            return true;
        });
    }

    private function successResponse(string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($this->isAjax) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $extra));
        }

        return back()->with([
            'message' => $message,
            'type'    => 'success',
        ]);
    }

    private function errorResponse(string $message): RedirectResponse|JsonResponse
    {
        if ($this->isAjax) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->with([
            'message' => $message,
            'type'    => 'error',
        ]);
    }

    /**
     * @param  array{duration_seconds?: int|null, resolution?: string|null, aspect_ratio?: string|null}  $metadata
     */
    private function createUserFall(
        int $userId,
        ?string $prompt,
        string $action,
        array $response,
        ?string $imageUrl = null,
        array $metadata = []
    ): UserFall {
        return UserFall::create([
            'user_id'          => $userId,
            'prompt'           => $prompt,
            'prompt_image_url' => $imageUrl,
            'status'           => $response['status'] ?? 'IN_QUEUE',
            'request_id'       => $response['request_id'] ?? $response['id'] ?? null,
            'response_url'     => $response['response_url'] ?? null,
            'model'            => $action,
            'duration_seconds' => $metadata['duration_seconds'] ?? null,
            'resolution'       => $metadata['resolution'] ?? null,
            'aspect_ratio'     => $metadata['aspect_ratio'] ?? null,
        ]);
    }

    public function checkVideoStatus(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $ids = (array) $request->get('ids', []);

        $entries = UserFall::where('user_id', $userId)
            ->whereNotIn('status', ['complete', 'error'])
            ->whereIn('id', $ids)
            ->get();

        if ($entries->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $data = collect();

        foreach ($entries as $entry) {
            $result = match (true) {
                str_starts_with($entry->model ?? '', 'sora')                          => $this->handleSoraEntry($entry),
                str_starts_with($entry->model ?? '', 'veo3.1/')                       => $this->handleVeoEntry($entry),
                str_starts_with($entry->model ?? '', 'kling-2.5-turbo/')              => $this->handleKling25Entry($entry),
                str_starts_with($entry->model ?? '', 'kling-video/v2.6/pro/')         => $this->handleKling26ProEntry($entry),
                str_starts_with($entry->model ?? '', 'kling-video/v2.6/standard/')    => $this->handleKling26ProEntry($entry),
                str_starts_with($entry->model ?? '', 'kling-video/v3/pro/')           => $this->handleKlingV3Entry($entry),
                str_starts_with($entry->model ?? '', 'kling-video/v3/standard/')      => $this->handleKlingV3Entry($entry),
                str_starts_with($entry->model ?? '', 'xai/grok-imagine-video/')       => $this->handleGrokImagineVideoEntry($entry),
                str_starts_with($entry->model ?? '', 'bytedance/seedance-2.0/')       => $this->handleSeedanceEntry($entry),
                str_starts_with($entry->model ?? '', 'google/gemini-omni-flash')      => $this->handleGeminiOmniFlashEntry($entry),
                str_starts_with($entry->model ?? '', 'veed/')                         => $this->handleVeedEntry($entry),
                default                                                               => $this->handleFalEntry($entry),
            };

            if ($result) {
                $data->push($result);
            }
        }

        return response()->json(['data' => $data]);
    }

    private function handleSoraEntry($entry): ?array
    {
        $response = SoraService::getStatus($entry->request_id);
        $status = strtoupper((string) ($response['status'] ?? ''));

        if ($status === 'COMPLETED') {
            $vidUrl = SoraService::getVideo($entry->request_id);
            if ($vidUrl) {
                $entry->update(['status' => 'complete', 'video_url' => $vidUrl, 'error_message' => null]);

                return $this->renderVideoItem($entry, $vidUrl);
            }

            return $this->markEntryAsError($entry, __('Video generation completed but no video URL was returned.'));
        }

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($response, __('Sora generation failed.')));
        }

        return null;
    }

    private function handleVeoEntry($entry): ?array
    {
        // Extract mode from model string (e.g., "veo3.1/text-to-video-fast" -> "text-to-video-fast")
        $mode = str_replace('veo3.1/', '', $entry->model);
        $entity = $this->detectVeo31EntityEnum($mode);
        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('VEO generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'veo');
            }

            return $this->markEntryAsError($entry, __('VEO completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('VEO generation failed.')));
        }

        return null;
    }

    private function handleKling25Entry($entry): ?array
    {
        // Extract mode from model string (e.g., "kling-2.5-turbo/text-to-video" -> "text-to-video")
        $mode = str_replace('kling-2.5-turbo/', '', $entry->model);
        $entity = $this->detectKling25EntityEnum($mode);

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Kling generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'kling25');
            }

            return $this->markEntryAsError($entry, __('Kling completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Kling generation failed.')));
        }

        return null;
    }

    private function handleKling26ProEntry($entry): ?array
    {
        // Extract mode from model string
        // Pro: "kling-video/v2.6/pro/text-to-video" -> "text-to-video"
        // Standard: "kling-video/v2.6/standard/motion-control" -> "motion-control"
        $mode = str_replace(['kling-video/v2.6/pro/', 'kling-video/v2.6/standard/'], '', $entry->model);

        // Detect entity based on mode - handles both pro and standard
        if (str_contains($entry->model, 'standard/motion-control')) {
            $entity = EntityEnum::KLING_2_6_STANDARD_MOTION_CONTROL;
        } else {
            $entity = $this->detectKling26ProEntityEnum($mode);
        }

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Kling generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'kling26');
            }

            return $this->markEntryAsError($entry, __('Kling completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Kling generation failed.')));
        }

        return null;
    }

    private function handleKlingV3Entry($entry): ?array
    {
        $entity = $this->detectKlingV3EntityEnum($entry->model);

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Kling generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'kling3');
            }

            return $this->markEntryAsError($entry, __('Kling completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Kling generation failed.')));
        }

        return null;
    }

    private function handleGrokImagineVideoEntry($entry): ?array
    {
        $entity = EntityEnum::GROK_IMAGINE_VIDEO_ITV;

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Grok Imagine Video generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'grok');
            }

            return $this->markEntryAsError($entry, __('Grok Imagine Video completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Grok Imagine Video generation failed.')));
        }

        return null;
    }

    private function handleSeedanceEntry($entry): ?array
    {
        $entity = EntityEnum::SEEDANCE_2_TTV;

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Seedance 2.0 generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'seedance');
            }

            return $this->markEntryAsError($entry, __('Seedance 2.0 completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Seedance 2.0 generation failed.')));
        }

        return null;
    }

    private function handleGeminiOmniFlashEntry($entry): ?array
    {
        $entity = EntityEnum::GEMINI_OMNI_FLASH_TTV;

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Gemini Omni Flash generation failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videoUrl = $result->resData->video->url ?? null;
            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'gemini');
            }

            return $this->markEntryAsError($entry, __('Gemini Omni Flash completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Gemini Omni Flash generation failed.')));
        }

        return null;
    }

    private function handleVeedEntry($entry): ?array
    {
        $entity = EntityEnum::VIDEO_BACKGROUND_REMOVAL;

        $check = $this->falAIService->textToVideoModel($entity)->checkStatus($entry->request_id)->getData();
        $status = strtoupper((string) ($check->resData->status ?? ''));

        if ($this->isFailedStatus($status)) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($check, __('Video background removal failed.')));
        }

        if ($status !== 'COMPLETED') {
            return null;
        }

        $result = $this->falAIService->textToVideoModel($entity)->getResult($entry->request_id)->getData();

        if (($result->status ?? null) === 'success') {
            $videos = $result->resData->video ?? [];
            $videoUrl = is_array($videos) && ! empty($videos) ? ($videos[0]->url ?? null) : null;

            if ($videoUrl) {
                return $this->completeWithRemoteUrlAndQueueDownload($entry, $videoUrl, 'veed');
            }

            return $this->markEntryAsError($entry, __('Video background removal completed but no video URL was returned.'));
        }

        if (in_array(($result->status ?? null), ['failed', 'error'])) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($result, __('Video background removal failed.')));
        }

        return null;
    }

    private function handleFalEntry($entry): ?array
    {
        $response = FalAIService::getStatus($entry->response_url);

        if (! empty($response['video']['url'])) {
            return $this->completeWithRemoteUrlAndQueueDownload($entry, $response['video']['url'], 'fal');
        }

        $detail = $response['detail'] ?? null;
        $status = strtoupper((string) ($response['status'] ?? ''));

        // Handle failed or invalid responses
        if (
            $this->isFailedStatus($status) ||
            in_array($detail, [
                'Internal Server Error',
                'Luma API timed out',
                "Luma API failed: generation.state='failed' generation.failure_reason='400: prompt not allowed because advanced moderation failed'",
            ]) ||
            (isset($detail[0]['type']) && $detail[0]['type'] === 'image_load_error')
        ) {
            return $this->markEntryAsError($entry, $this->extractErrorMessage($response, __('Video generation failed.')));
        }

        return null;
    }

    private function completeWithRemoteUrlAndQueueDownload($entry, string $remoteUrl, string $prefix): array
    {
        $entry->update([
            'status'        => 'complete',
            'video_url'     => $remoteUrl,
            'error_message' => null,
        ]);

        DownloadVideoToLocalJob::dispatch($entry->id, $remoteUrl, $prefix);

        return $this->renderVideoItem($entry, $remoteUrl);
    }

    private function renderVideoItem($entry, string $url): array
    {
        $entry->video_url = $url;
        $entry->status = 'complete';
        $entry->error_message = null;

        return [
            'divId'     => "video-{$entry->id}",
            'html'      => view('ai-video-pro::partials.video-item', ['entry' => $entry])->render(),
            'videoData' => [
                'id'                 => $entry->id,
                'prompt'             => $entry->prompt,
                'video_url'          => $entry->video_url,
                'model'              => $entry->model,
                'duration_seconds'   => $entry->duration_seconds,
                'created_at'         => $entry->created_at?->toIso8601String(),
                'formatted_duration' => $entry->formatted_duration,
                'resolution'         => $entry->resolution,
                'aspect_ratio'       => $entry->aspect_ratio,
            ],
        ];
    }

    private function markEntryAsError($entry, string $message): array
    {
        $entry->update([
            'status'        => 'error',
            'error_message' => Str::limit($message, 1000),
        ]);

        $entry->status = 'error';
        $entry->error_message = Str::limit($message, 1000);

        return $this->renderVideoStatusItem($entry);
    }

    private function renderVideoStatusItem($entry): array
    {
        return [
            'divId' => "video-{$entry->id}",
            'html'  => view('ai-video-pro::partials.video-item', ['entry' => $entry])->render(),
        ];
    }

    private function isFailedStatus(?string $status): bool
    {
        return in_array(strtoupper((string) $status), ['FAILED', 'ERROR', 'CANCELLED', 'CANCELED', 'NOT_FOUND'], true);
    }

    private function extractErrorMessage(mixed $payload, string $fallback): string
    {
        $candidates = [
            data_get($payload, 'resData.detail.0.msg'),
            data_get($payload, 'resData.error.message'),
            data_get($payload, 'resData.message'),
            data_get($payload, 'message'),
            data_get($payload, 'detail.0.msg'),
            data_get($payload, 'detail'),
            data_get($payload, 'error.message'),
            data_get($payload, 'error'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return $fallback;
    }

    private function detectKling25EntityEnum(?string $mode): EntityEnum
    {
        return match ($mode) {
            'image-to-video'     => EntityEnum::KLING_2_5_TURBO_STANDARD_ITV,
            'image-to-video-pro' => EntityEnum::KLING_2_5_TURBO_PRO_ITV,
            default              => EntityEnum::KLING_2_5_TURBO_PRO_TTV, // text-to-video as default
        };
    }

    private function detectKling26ProEntityEnum(?string $mode): EntityEnum
    {
        return match ($mode) {
            'image-to-video'  => EntityEnum::KLING_2_6_PRO_ITV,
            'motion-control'  => EntityEnum::KLING_2_6_PRO_MOTION_CONTROL,
            default           => EntityEnum::KLING_2_6_PRO_TTV, // text-to-video as default
        };
    }

    private function detectKlingV3EntityEnum(?string $model): EntityEnum
    {
        return match ($model) {
            EntityEnum::KLING_3_PRO_ITV->value      => EntityEnum::KLING_3_PRO_ITV,
            EntityEnum::KLING_3_STANDARD_TTV->value => EntityEnum::KLING_3_STANDARD_TTV,
            EntityEnum::KLING_3_STANDARD_ITV->value => EntityEnum::KLING_3_STANDARD_ITV,
            default                                 => EntityEnum::KLING_3_PRO_TTV,
        };
    }

    private function decodeJsonArrayField(array $validated, string $field, string $label): ?array
    {
        if (empty($validated[$field])) {
            return null;
        }

        $decoded = json_decode((string) $validated[$field], true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new RuntimeException(__(':field JSON must be a valid JSON array.', ['field' => $label]));
        }

        return $decoded;
    }

    private function detectVeo31EntityEnum(?string $mode): EntityEnum
    {
        return match ($mode) {
            'image-to-video'                 => EntityEnum::VEO_3_1_IMAGE_TO_VIDEO,
            'image-to-video-fast'            => EntityEnum::VEO_3_1_IMAGE_TO_VIDEO_FAST,
            'first-last-frame-to-video'      => EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO,
            'first-last-frame-to-video-fast' => EntityEnum::VEO_3_1_FIRST_LAST_FRAME_TO_VIDEO_FAST,
            'reference-to-video'             => EntityEnum::VEO_3_1_REFERENCE_TO_VIDEO,
            'text-to-video-fast'             => EntityEnum::VEO_3_1_TEXT_TO_VIDEO_FAST,
            'lite'                           => EntityEnum::VEO_3_1_LITE_TEXT_TO_VIDEO,
            'lite/image-to-video'            => EntityEnum::VEO_3_1_LITE_IMAGE_TO_VIDEO,
            'lite/first-last-frame-to-video' => EntityEnum::VEO_3_1_LITE_FIRST_LAST_FRAME_TO_VIDEO,
            default                          => EntityEnum::VEO_3_1_TEXT_TO_VIDEO,
        };
    }

    public function enhancePrompt(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:5000',
        ]);

        try {
            Helper::setOpenAiKey();

            $setting = Setting::getCache();
            $model = EntityEnum::fromSlug($setting->openai_default_model ?: EntityEnum::GPT_4_O->value);

            $driver = Entity::driver($model);
            $driver->redirectIfNoCreditBalance();

            $completion = OpenAI::chat()->create([
                'model'    => $model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an expert at writing prompts for AI video generation. Enhance the given video description to be more detailed, cinematic, and descriptive while keeping the original intent. Add details about camera movements, lighting, mood, and visual style where appropriate. Keep the enhanced prompt concise (under 500 characters). Return only the enhanced prompt text, nothing else.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $request->input('prompt'),
                    ],
                ],
            ]);

            $result = $completion['choices'][0]['message']['content'];

            $driver->input($result)->calculateCredit()->decreaseCredit();

            return response()->json(['result' => $result]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
