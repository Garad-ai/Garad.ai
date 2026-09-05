<?php

namespace App\Extensions\AiPersona\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AiPersona\System\Models\AiPersona;
use App\Extensions\AiPersona\System\Models\AiPersonaAvatar;
use App\Extensions\AiPersona\System\Services\AiPersonaService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AiPersonaController extends Controller
{
    public function __construct(
        public AiPersonaService $service
    ) {}

    private function videoDetailCacheKey(string $videoId): string
    {
        return "ai-persona.video.{$videoId}";
    }

    private function rememberVideoDetail(string $videoId, ?string $listStatus): array
    {
        $fetch = fn (): array => $this->service->retrieveVideo($videoId)['data'] ?? [];

        if (! in_array($listStatus, ['completed', 'failed'], true)) {
            return $fetch();
        }

        return Cache::remember($this->videoDetailCacheKey($videoId), now()->addDay(), $fetch);
    }

    private function cachedAvatars(): array
    {
        return Cache::remember(
            'ai-persona.avatars',
            now()->addHour(),
            fn () => $this->service->listAvatars()['data']['avatars'] ?? []
        );
    }

    private function cachedVoices(): array
    {
        return Cache::remember(
            'ai-persona.voices',
            now()->addHour(),
            fn () => $this->service->listVoices()['data']['voices'] ?? []
        );
    }

    private function cachedLocales(): array
    {
        return Cache::remember(
            'ai-persona.locales',
            now()->addHour(),
            fn () => $this->service->listLocales()['data']['locales'] ?? []
        );
    }

    private function normalizeVideo(array $raw, ?AiPersona $persona = null, ?array $voicesById = null, ?array $localesByCode = null): array
    {
        $duration = $raw['duration'] ?? null;

        $voiceLabel = null;

        if ($persona?->voice_id && isset($voicesById[$persona->voice_id])) {
            $voice = $voicesById[$persona->voice_id];
            $voiceLabel = trim(sprintf(
                '%s (%s - %s)',
                $voice['name'] ?? '',
                $voice['language'] ?? '',
                isset($voice['gender']) ? ucfirst((string) $voice['gender']) : ''
            ));
        }

        $localeLabel = null;

        if ($persona?->locale && isset($localesByCode[$persona->locale])) {
            $locale = $localesByCode[$persona->locale];
            $localeLabel = $locale['label'] ?? $locale['value'] ?? $persona->locale;
        }

        $dimensions = null;

        if ($persona?->dimension_width && $persona?->dimension_height) {
            $dimensions = $persona->dimension_width . ' × ' . $persona->dimension_height;
        }

        return [
            'id'                      => $raw['video_id'] ?? null,
            'status'                  => match ($raw['status'] ?? null) {
                'completed' => 'complete',
                'failed'    => 'error',
                default     => 'pending',
            },
            'input_text'              => $persona?->input_text ?? $raw['input_text'] ?? '',
            'video_url'               => $raw['video_url'] ?? '',
            'duration_seconds'        => $duration,
            'formatted_duration'      => $duration
                ? sprintf('%02d:%02d', intdiv((int) $duration, 60), (int) $duration % 60)
                : null,
            'model'                   => 'HeyGen',
            'error_message'           => $raw['error']['message'] ?? '',
            'created_at'              => isset($raw['created_at'])
                ? Carbon::createFromTimestamp((int) $raw['created_at'])
                : null,
            'avatar_style'            => $persona?->avatar_style,
            'matting'                 => $persona?->matting,
            'caption'                 => $persona?->caption,
            'circle_background_color' => $persona?->circle_background_color,
            'voice_type'              => $persona?->voice_type,
            'voice_id'                => $persona?->voice_id,
            'voice_label'             => $voiceLabel,
            'audio_asset_id'          => $persona?->audio_asset_id,
            'voice_speed'             => $persona?->voice_speed,
            'voice_pitch'             => $persona?->voice_pitch,
            'voice_emotion'           => $persona?->voice_emotion,
            'locale'                  => $persona?->locale,
            'locale_label'            => $localeLabel,
            'dimensions'              => $dimensions,
            'dimension_width'         => $persona?->dimension_width,
            'dimension_height'        => $persona?->dimension_height,
        ];
    }

    private function voicesById(): array
    {
        $map = [];

        foreach ($this->cachedVoices() as $voice) {
            if (! empty($voice['voice_id'])) {
                $map[$voice['voice_id']] = $voice;
            }
        }

        return $map;
    }

    private function localesByCode(): array
    {
        $map = [];

        foreach ($this->cachedLocales() as $locale) {
            $code = $locale['locale'] ?? $locale['language_code'] ?? null;
            if ($code) {
                $map[$code] = $locale;
            }
        }

        return $map;
    }

    public function index(): View
    {
        $customAvatars = AiPersonaAvatar::query()
            ->where('user_id', auth()->id())
            ->where('status', AiPersonaAvatar::STATUS_READY)
            ->whereNotNull('heygen_avatar_id')
            ->latest()
            ->get()
            ->map(fn (AiPersonaAvatar $avatar) => $this->customAvatarToPayload($avatar))
            ->all();

        $avatars = array_merge($customAvatars, $this->cachedAvatars());

        return view('ai-persona::index', [
            'avatars' => $avatars,
            'voices'  => $this->cachedVoices(),
            'locales' => $this->cachedLocales(),
        ]);
    }

    private function customAvatarToPayload(AiPersonaAvatar $avatar): array
    {
        return [
            'avatar_id'         => $avatar->heygen_avatar_id,
            'avatar_name'       => $avatar->name,
            'gender'            => $avatar->gender ?: 'Custom',
            'type'              => 'custom',
            'preview_image_url' => $avatar->preview_image_url,
            'preview_video_url' => null,
            'default_voice_id'  => null,
            'tags'              => [],
            'is_custom'         => true,
        ];
    }

    private function avatarRecordToPayload(AiPersonaAvatar $avatar): array
    {
        return [
            'id'                => $avatar->id,
            'name'              => $avatar->name,
            'source'            => $avatar->source,
            'status'            => $avatar->status,
            'preview_image_url' => $avatar->preview_image_url,
            'heygen_avatar_id'  => $avatar->heygen_avatar_id,
            'error_message'     => $avatar->error_message,
            'created_at'        => $avatar->created_at?->toIso8601String(),
        ];
    }

    public function enhanceScript(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'success' => false,
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $request->validate([
            'script' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $enhanced = AiPersonaService::enhanceScript($request->input('script'));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to enhance script: ') . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success'         => true,
            'enhanced_script' => $enhanced,
        ]);
    }

    public function videos(): JsonResponse
    {
        $personas = AiPersona::query()
            ->where('user_id', auth()->id())
            ->get()
            ->keyBy('avatar_id');

        $voicesById = $this->voicesById();
        $localesByCode = $this->localesByCode();

        $videos = collect($this->service->listVideos()['data']['videos'] ?? [])
            ->filter(fn (array $video) => isset($video['video_id']) && $personas->has($video['video_id']))
            ->map(function (array $video) use ($personas, $voicesById, $localesByCode): array {
                $detail = $this->rememberVideoDetail($video['video_id'], $video['status'] ?? null);

                return $this->normalizeVideo(array_merge($video, $detail), $personas->get($video['video_id']), $voicesById, $localesByCode);
            })
            ->values();

        [$todayVideos, $previousVideos] = $videos
            ->partition(fn (array $video) => $video['created_at']?->isToday() ?? false)
            ->map(fn (Collection $bucket) => $bucket->values())
            ->all();

        $pendingIds = $videos->where('status', 'pending')->pluck('id')->all();

        $modalVideos = $todayVideos
            ->concat($previousVideos)
            ->where('status', 'complete')
            ->values()
            ->all();

        return response()->json([
            'html'       => view('ai-persona::videos-section', compact('todayVideos', 'previousVideos'))->render(),
            'pendingIds' => $pendingIds,
            'hasVideos'  => $todayVideos->isNotEmpty() || $previousVideos->isNotEmpty(),
            'videos'     => $modalVideos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $voiceType = $request->input('voice_type', 'text');

        $rules = [
            'avatar_id'               => ['required', 'string'],
            'avatar_style'            => ['required', 'string', 'in:normal,circle,closeUp'],
            'voice_type'              => ['required', 'string', 'in:text,audio'],
            'circle_background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'matting'                 => ['sometimes', 'boolean'],
            'caption'                 => ['sometimes', 'boolean'],
            'dimension_width'         => ['required', 'integer', 'min:1'],
            'dimension_height'        => ['required', 'integer', 'min:1'],
        ];

        if ($voiceType === 'audio') {
            $rules['audio_file'] = ['required', 'file', 'mimes:mp3,mpga,wav,webm,mpeg', 'max:25600'];
        } else {
            $rules['voice_id'] = ['required', 'string'];
            $rules['input_text'] = ['required', 'string'];
            $rules['voice_speed'] = ['nullable', 'numeric', 'between:0.5,1.5'];
            $rules['voice_pitch'] = ['nullable', 'integer', 'between:-50,50'];
            $rules['voice_emotion'] = ['nullable', 'string', 'in:Excited,Friendly,Serious,Soothing,Broadcaster'];
            $rules['locale'] = ['nullable', 'string', 'max:20'];
        }

        $request->validate($rules);

        $avatarStyle = $request->get('avatar_style');

        $character = [
            'type'         => 'avatar',
            'avatar_id'    => $request->get('avatar_id'),
            'avatar_style' => $avatarStyle,
            'matting'      => $request->boolean('matting'),
        ];

        if ($avatarStyle === 'circle' && $request->filled('circle_background_color')) {
            $character['circle_background_color'] = $request->get('circle_background_color');
        }

        $audioAssetId = null;

        if ($voiceType === 'audio') {
            $file = $request->file('audio_file');
            $upload = $this->service->uploadAsset(
                file_get_contents($file->getRealPath()),
                $file->getMimeType() ?: 'audio/mpeg',
            );

            if (! empty($upload['error'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $this->extractErrorMessage($upload) ?? __('Failed to upload audio.'),
                ], 422);
            }

            $audioAssetId = $upload['data']['id'] ?? null;

            if (! $audioAssetId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('Failed to upload audio.'),
                ], 422);
            }

            $voice = [
                'type'           => 'audio',
                'audio_asset_id' => $audioAssetId,
            ];
        } else {
            $voice = [
                'type'       => 'text',
                'input_text' => $request->get('input_text'),
                'voice_id'   => $request->get('voice_id'),
            ];

            if ($request->filled('voice_speed')) {
                $voice['speed'] = (float) $request->get('voice_speed');
            }

            if ($request->filled('voice_pitch')) {
                $voice['pitch'] = (int) $request->get('voice_pitch');
            }

            if ($request->filled('voice_emotion')) {
                $voice['emotion'] = $request->get('voice_emotion');
            }

            if ($request->filled('locale')) {
                $voice['locale'] = $request->get('locale');
            }
        }

        $body = [
            'video_inputs' => [[
                'character' => $character,
                'voice'     => $voice,
            ]],
            'caption'   => true,
            'dimension' => [
                'width'  => (int) $request->get('dimension_width'),
                'height' => (int) $request->get('dimension_height'),
            ],
        ];

        $driver = Entity::driver(EntityEnum::HEYGEN)->inputVideoCount(1)->calculateCredit();

        try {
            $driver->redirectIfNoCreditBalance();
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => __('You have no credits left. Please consider upgrading your plan.'),
            ], 402);
        }

        $response = $this->service->createVideo($body);

        if (! empty($response['error'])) {
            $message = is_array($response['error'])
                ? ($response['error']['message'] ?? __('Video generation failed.'))
                : (string) $response['error'];

            return response()->json([
                'status'  => 'error',
                'message' => $message,
            ], 422);
        }

        $videoId = $response['data']['video_id'] ?? null;

        if (! $videoId) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Video generation failed.'),
            ], 422);
        }

        AiPersona::query()->create([
            'user_id'                 => auth()->id(),
            'avatar_id'               => $videoId,
            'status'                  => 'in_progress',
            'input_text'              => $voiceType === 'text' ? $request->get('input_text') : null,
            'voice_type'              => $voiceType,
            'avatar_style'            => $avatarStyle,
            'matting'                 => $character['matting'],
            'caption'                 => $body['caption'],
            'circle_background_color' => $character['circle_background_color'] ?? null,
            'voice_id'                => $voice['voice_id'] ?? null,
            'audio_asset_id'          => $audioAssetId,
            'voice_speed'             => $voice['speed'] ?? null,
            'voice_pitch'             => $voice['pitch'] ?? null,
            'voice_emotion'           => $voice['emotion'] ?? null,
            'locale'                  => $voice['locale'] ?? null,
            'dimension_width'         => $body['dimension']['width'],
            'dimension_height'        => $body['dimension']['height'],
        ]);

        $driver->decreaseCredit();

        return response()->json([
            'status'   => 'success',
            'message'  => __('Video Created Successfully'),
            'video_id' => $videoId,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(string $id)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('This feature is disabled in demo mode.'),
            ]);
        }

        $model = $this->service->deleteVideo($id);

        if ($model->getStatusCode() === 200 || $model->getStatusCode() === 204) {

            $builder = AiPersona::query()->where('avatar_id', $id)->first();

            $builder?->delete();

            Cache::forget($this->videoDetailCacheKey($id));

            return back()->with(['message' => __('Deleted Successfully'), 'type' => 'success']);
        }

        return back()->with(['message' => __('Delete Failed'), 'type' => 'danger']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $ids = array_values(array_unique($request->get('ids')));

        $userAvatarIds = AiPersona::query()
            ->where('user_id', auth()->id())
            ->whereIn('avatar_id', $ids)
            ->pluck('avatar_id')
            ->all();

        $deleted = [];
        $failed = [];

        foreach ($userAvatarIds as $id) {
            try {
                $response = $this->service->deleteVideo($id);
                $status = $response->getStatusCode();
            } catch (Exception $e) {
                $failed[] = $id;

                continue;
            }

            if ($status === 200 || $status === 204) {
                AiPersona::query()->where('avatar_id', $id)->delete();
                Cache::forget($this->videoDetailCacheKey($id));
                $deleted[] = $id;
            } else {
                $failed[] = $id;
            }
        }

        return response()->json([
            'status'  => empty($failed) ? 'success' : 'partial',
            'deleted' => $deleted,
            'failed'  => $failed,
            'message' => empty($failed)
                ? __('Deleted Successfully')
                : __(':count item(s) could not be deleted.', ['count' => count($failed)]),
        ]);
    }

    public function storeAvatar(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $source = $request->input('source');

        if (! in_array($source, [AiPersonaAvatar::SOURCE_AI, AiPersonaAvatar::SOURCE_UPLOAD], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Invalid avatar source.'),
            ], 422);
        }

        return $source === AiPersonaAvatar::SOURCE_AI
            ? $this->storeAiAvatar($request)
            : $this->storeUploadedAvatar($request);
    }

    private function storeAiAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'age'         => ['required', 'string', 'in:Young Adult,Early Middle Age,Late Middle Age,Senior,Unspecified'],
            'gender'      => ['required', 'string', 'in:Woman,Man,Unspecified'],
            'ethnicity'   => ['required', 'string', 'in:White,Black,Asian American,East Asian,South East Asian,South Asian,Middle Eastern,Pacific,Hispanic,Unspecified'],
            'orientation' => ['required', 'string', 'in:square,horizontal,vertical'],
            'pose'        => ['required', 'string', 'in:half_body,close_up,full_body'],
            'style'       => ['required', 'string', 'in:Realistic,Pixar,Cinematic,Vintage,Noir,Cyberpunk,Unspecified'],
            'appearance'  => ['required', 'string', 'max:1000'],
        ]);

        $response = $this->service->generatePhotoAvatar($validated);

        if (! empty($response['error'])) {
            return response()->json([
                'status'  => 'error',
                'message' => $this->extractErrorMessage($response) ?? __('Avatar generation failed.'),
            ], 422);
        }

        $generationId = $response['data']['generation_id'] ?? null;

        if (! $generationId) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Avatar generation failed.'),
            ], 422);
        }

        $avatar = AiPersonaAvatar::query()->create([
            'user_id'       => auth()->id(),
            'name'          => $validated['name'],
            'source'        => AiPersonaAvatar::SOURCE_AI,
            'status'        => AiPersonaAvatar::STATUS_GENERATING,
            'generation_id' => $generationId,
            'age'           => $validated['age'],
            'gender'        => $validated['gender'],
            'ethnicity'     => $validated['ethnicity'],
            'orientation'   => $validated['orientation'],
            'pose'          => $validated['pose'],
            'style'         => $validated['style'],
            'appearance'    => $validated['appearance'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => __('Avatar is being generated. It will appear in your avatars once ready.'),
            'avatar'  => $this->avatarRecordToPayload($avatar),
        ]);
    }

    private function storeUploadedAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $validated['file'];
        $binary = file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?? 'image/jpeg';

        $response = $this->service->uploadAsset($binary, $mime);

        if (! empty($response['error'])) {
            return response()->json([
                'status'  => 'error',
                'message' => $this->extractErrorMessage($response) ?? __('Avatar upload failed.'),
            ], 422);
        }

        $imageKey = $response['data']['image_key'] ?? $response['image_key'] ?? null;

        if (! $imageKey) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Avatar upload failed.'),
            ], 422);
        }

        $avatar = AiPersonaAvatar::query()->create([
            'user_id'   => auth()->id(),
            'name'      => $validated['name'],
            'source'    => AiPersonaAvatar::SOURCE_UPLOAD,
            'status'    => AiPersonaAvatar::STATUS_GENERATED,
            'image_key' => $imageKey,
        ]);

        $this->advanceAvatar($avatar);

        return response()->json([
            'status'  => 'success',
            'message' => __('Avatar uploaded. It will appear in your avatars once training completes.'),
            'avatar'  => $this->avatarRecordToPayload($avatar->fresh()),
        ]);
    }

    public function listUserAvatars(): JsonResponse
    {
        $avatars = AiPersonaAvatar::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(fn (AiPersonaAvatar $avatar) => $this->avatarRecordToPayload($avatar))
            ->all();

        return response()->json(['avatars' => $avatars]);
    }

    public function checkAvatars(Request $request): JsonResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));

        $query = AiPersonaAvatar::query()->where('user_id', auth()->id());

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereNotIn('status', [AiPersonaAvatar::STATUS_READY, AiPersonaAvatar::STATUS_FAILED]);
        }

        $avatars = $query->get();

        $updated = [];

        foreach ($avatars as $avatar) {
            $this->advanceAvatar($avatar);
            $updated[] = $this->avatarRecordToPayload($avatar->fresh());
        }

        return response()->json(['avatars' => $updated]);
    }

    public function deleteAvatar(int $id): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 403);
        }

        $avatar = AiPersonaAvatar::query()
            ->where('user_id', auth()->id())
            ->where('id', $id)
            ->first();

        if (! $avatar) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Avatar not found.'),
            ], 404);
        }

        $avatar->delete();

        return response()->json([
            'status'  => 'success',
            'message' => __('Avatar deleted.'),
        ]);
    }

    private function advanceAvatar(AiPersonaAvatar $avatar): void
    {
        try {
            match ($avatar->status) {
                AiPersonaAvatar::STATUS_GENERATING => $this->advanceGeneratingAvatar($avatar),
                AiPersonaAvatar::STATUS_GENERATED  => $this->advanceGeneratedAvatar($avatar),
                AiPersonaAvatar::STATUS_GROUPING   => $this->advanceGroupingAvatar($avatar),
                AiPersonaAvatar::STATUS_TRAINING   => $this->advanceTrainingAvatar($avatar),
                default                            => null,
            };
        } catch (Exception $e) {
            $avatar->update([
                'status'        => AiPersonaAvatar::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function advanceGeneratingAvatar(AiPersonaAvatar $avatar): void
    {
        if (! $avatar->generation_id) {
            $avatar->update(['status' => AiPersonaAvatar::STATUS_FAILED, 'error_message' => 'Missing generation id']);

            return;
        }

        $response = $this->service->checkPhotoGeneration($avatar->generation_id);

        if (! empty($response['error'])) {
            return;
        }

        $data = $response['data'] ?? [];
        $status = strtolower((string) ($data['status'] ?? ''));

        if (in_array($status, ['failed', 'error'], true)) {
            $avatar->update([
                'status'        => AiPersonaAvatar::STATUS_FAILED,
                'error_message' => $data['msg'] ?? $data['error'] ?? 'Generation failed',
            ]);

            return;
        }

        if (! in_array($status, ['success', 'completed', 'ready'], true)) {
            return;
        }

        $imageKey = $data['image_key_list'][0] ?? $data['image_url_list'][0]['image_key'] ?? $data['image_key'] ?? null;
        $previewUrl = $data['image_url_list'][0]['url'] ?? $data['image_url_list'][0] ?? null;

        if (! $imageKey) {
            return;
        }

        $avatar->update([
            'status'            => AiPersonaAvatar::STATUS_GENERATED,
            'image_key'         => $imageKey,
            'preview_image_url' => $previewUrl ?: $avatar->preview_image_url,
        ]);

        $this->advanceAvatar($avatar->fresh());
    }

    private function advanceGeneratedAvatar(AiPersonaAvatar $avatar): void
    {
        if (! $avatar->image_key) {
            $avatar->update(['status' => AiPersonaAvatar::STATUS_FAILED, 'error_message' => 'Missing image key']);

            return;
        }

        $body = [
            'name'      => $avatar->name,
            'image_key' => $avatar->image_key,
        ];

        if ($avatar->generation_id) {
            $body['generation_id'] = $avatar->generation_id;
        }

        $response = $this->service->createAvatarGroup($body);

        if (! empty($response['error'])) {
            $avatar->update([
                'status'        => AiPersonaAvatar::STATUS_FAILED,
                'error_message' => $this->extractErrorMessage($response) ?? 'Failed to create avatar group',
            ]);

            return;
        }

        $groupId = $response['data']['group_id'] ?? $response['data']['id'] ?? null;
        $previewUrl = $response['data']['image_url'] ?? null;

        if (! $groupId) {
            $avatar->update([
                'status'        => AiPersonaAvatar::STATUS_FAILED,
                'error_message' => 'No group id returned',
            ]);

            return;
        }

        $avatar->update([
            'status'            => AiPersonaAvatar::STATUS_GROUPING,
            'group_id'          => $groupId,
            'preview_image_url' => $previewUrl ?: $avatar->preview_image_url,
        ]);
    }

    private function advanceGroupingAvatar(AiPersonaAvatar $avatar): void
    {
        if (! $avatar->group_id) {
            $avatar->update(['status' => AiPersonaAvatar::STATUS_FAILED, 'error_message' => 'Missing group id']);

            return;
        }

        if (! $this->groupHasPhotos($avatar->group_id)) {
            return;
        }

        $response = $this->service->trainAvatarGroup($avatar->group_id);

        if (! empty($response['error'])) {
            $message = $this->extractErrorMessage($response) ?? 'Failed to start training';

            if (stripos($message, 'no valid image') !== false) {
                return;
            }

            $avatar->update([
                'status'        => AiPersonaAvatar::STATUS_FAILED,
                'error_message' => $message,
            ]);

            return;
        }

        $flowId = $response['data']['data']['flow_id']
            ?? $response['data']['flow_id']
            ?? null;

        $avatar->update([
            'status'  => AiPersonaAvatar::STATUS_TRAINING,
            'flow_id' => $flowId,
        ]);
    }

    private function groupHasPhotos(string $groupId): bool
    {
        $response = $this->service->listAvatarsInGroup($groupId);

        if (! empty($response['error'])) {
            return false;
        }

        $data = $response['data'] ?? [];
        $photos = $data['avatar_list']
            ?? $data['photo_avatar_list']
            ?? $data['avatars']
            ?? $data['list']
            ?? [];

        return ! empty($photos);
    }

    private function advanceTrainingAvatar(AiPersonaAvatar $avatar): void
    {
        if (! $avatar->group_id) {
            $avatar->update(['status' => AiPersonaAvatar::STATUS_FAILED, 'error_message' => 'Missing group id']);

            return;
        }

        $response = $this->service->checkTrainingStatus($avatar->group_id);

        if (! empty($response['error'])) {
            return;
        }

        $data = $response['data'] ?? [];
        $status = strtolower((string) ($data['status'] ?? ''));

        if (in_array($status, ['failed', 'error'], true)) {
            $avatar->update([
                'status'        => AiPersonaAvatar::STATUS_FAILED,
                'error_message' => $data['error_msg'] ?? $data['msg'] ?? 'Training failed',
            ]);

            return;
        }

        if ($status !== 'ready') {
            return;
        }

        $list = $this->service->listAvatarsInGroup($avatar->group_id);

        if (! empty($list['error'])) {
            return;
        }

        $avatars = $list['data']['avatar_list'] ?? [];

        $readyAvatar = collect($avatars)
            ->first(fn (array $item) => ($item['status'] ?? null) === 'completed');

        $readyAvatar = $readyAvatar ?: ($avatars[0] ?? null);

        if (! $readyAvatar || empty($readyAvatar['id'])) {
            return;
        }

        $avatar->update([
            'status'            => AiPersonaAvatar::STATUS_READY,
            'heygen_avatar_id'  => $readyAvatar['id'],
            'preview_image_url' => $readyAvatar['image_url'] ?? $avatar->preview_image_url,
        ]);
    }

    private function extractErrorMessage(array $response): ?string
    {
        $error = $response['error'] ?? null;

        if (is_string($error)) {
            return $error;
        }

        if (is_array($error)) {
            return $error['message'] ?? $error['msg'] ?? null;
        }

        return $response['message'] ?? null;
    }

    public function checkVideoStatus(Request $request): JsonResponse
    {
        $ids = (array) $request->get('ids', []);

        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        $personas = AiPersona::query()
            ->where('user_id', auth()->id())
            ->whereIn('avatar_id', $ids)
            ->get()
            ->keyBy('avatar_id');

        $voicesById = $this->voicesById();
        $localesByCode = $this->localesByCode();

        $items = collect($this->service->listVideos()['data']['videos'] ?? [])
            ->filter(fn (array $entry) => in_array($entry['video_id'] ?? null, $ids, true) && ($entry['status'] ?? null) === 'completed')
            ->map(function (array $entry) use ($personas, $voicesById, $localesByCode): array {
                $detail = $this->service->retrieveVideo($entry['video_id'])['data'] ?? [];
                $normalized = $this->normalizeVideo(array_merge($detail, $entry), $personas->get($entry['video_id']), $voicesById, $localesByCode);

                AiPersona::query()
                    ->where('avatar_id', $entry['video_id'])
                    ->update(['status' => 'completed']);

                return [
                    'divId' => 'video-' . $entry['video_id'],
                    'html'  => view('ai-persona::video-item', ['entry' => $normalized])->render(),
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $items]);
    }
}
