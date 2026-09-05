<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Domains\Engine\Services\FalAIService;
use App\Domains\Entity\Enums\EntityEnum;
use App\Enums\AiImageStatusEnum;
use App\Extensions\AiChatProImageChat\System\Jobs\PollChatImageGenerationJob;
use App\Extensions\AiChatProImageChat\System\Models\AiChatProImageModel;
use App\Extensions\AiChatProImageChat\System\Services\AIChatImageService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\OpenAIGenerator;
use App\Models\UserOpenai;
use App\Services\Ai\OpenAI\Image\CreateImageEditService;
use App\Services\Stream\StreamService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EditImageStreamController extends Controller
{
    public function buildStreamedOutput(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'prompt'              => 'nullable|string|max:5000',
            'edit_tab'            => 'required|string|in:smart_edit,restyle,remove_background,replace_background,reimagine',
            'edit_mode'           => 'required|string|in:visual,text',
            'edit_has_highlights' => 'nullable',
            'chat_id'             => 'nullable|integer',
            'image_reference'     => 'required|file|image|mimes:jpeg,jpg,png,webp|max:25600',
            'mask_image'          => 'nullable|file|image|mimes:png|max:25600',
            'source'              => 'nullable|string|max:50',
        ]);

        $editTab = $validated['edit_tab'];
        $editMode = $validated['edit_mode'];
        $hasHighlights = (bool) ($validated['edit_has_highlights'] ?? false);

        // Upload images
        $imageUrl = $this->saveUploadedFile($request->file('image_reference'));
        $maskUrl = $request->hasFile('mask_image')
            ? $this->saveUploadedFile($request->file('mask_image'), true)
            : null;

        // Build prompt based on edit_tab
        $prompt = AIChatImageService::buildEditPrompt(
            $validated['prompt'] ?? '',
            $editTab,
            $editMode,
            $hasHighlights
        );

        // Determine model from AI Image Pro setting
        $entity = $this->resolveEditModelEntity();

        // Create tracking record
        $record = AiChatProImageModel::create([
            'user_id' => auth()->id(),
            'model'   => $entity->value,
            'engine'  => $entity->engine()->value,
            'prompt'  => $prompt,
            'params'  => [
                'edit_tab'        => $editTab,
                'edit_mode'       => $editMode,
                'has_highlights'  => $hasHighlights,
                'image_reference' => $imageUrl,
                'mask_image'      => $maskUrl,
                'image_count'     => 1,
                'source'          => $validated['source'] ?? null,
            ],
            'guest_ip' => $request->header('cf-connecting-ip') ?? $request->ip(),
            'status'   => AiImageStatusEnum::PENDING,
        ]);

        $streamService = app(StreamService::class);

        return response()->stream(function () use ($record, $entity, $prompt, $imageUrl, $maskUrl, $editTab, $streamService) {
            $streamService->prepareStreamEnvironment();

            // Send record_id so frontend can start polling
            echo "event: image_record\n";
            echo 'data: ' . $record->id . "\n\n";
            $streamService->safeFlush();

            try {
                $record->markAsStarted();

                $isOpenAI = in_array($entity, [EntityEnum::GPT_IMAGE_1, EntityEnum::GPT_IMAGE_1_5, EntityEnum::GPT_IMAGE_2], true);

                if ($isOpenAI) {
                    $this->processWithOpenAI($record, $entity, $prompt, $imageUrl, $maskUrl, $editTab);
                } else {
                    $this->processWithFalAI($record, $entity, $prompt, $imageUrl, $maskUrl);
                }
            } catch (Throwable $e) {
                Log::error('EditImageStream generation failed', [
                    'record_id' => $record->id,
                    'error'     => $e->getMessage(),
                ]);
                $record->markAsFailed($e->getMessage());
            }

            echo "event: stop\n";
            echo "data: [DONE]\n\n";
            $streamService->safeFlush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    private function resolveEditModelEntity(): EntityEnum
    {
        $model = (string) setting('ai_image_pro_edit_model', 'gpt-image-1.5');

        return match ($model) {
            'nano-banana',
            EntityEnum::NANO_BANANA->value,
            EntityEnum::NANO_BANANA_EDIT->value => EntityEnum::NANO_BANANA_EDIT,
            'nano-banana-pro',
            EntityEnum::NANO_BANANA_PRO->value,
            EntityEnum::NANO_BANANA_PRO_EDIT->value => EntityEnum::NANO_BANANA_PRO_EDIT,
            'nano-banana-2',
            EntityEnum::NANO_BANANA_2->value,
            EntityEnum::NANO_BANANA_2_EDIT->value => EntityEnum::NANO_BANANA_2_EDIT,
            EntityEnum::GROK_IMAGINE_IMAGE->value, EntityEnum::GROK_IMAGINE_IMAGE_EDIT->value => EntityEnum::GROK_IMAGINE_IMAGE_EDIT,
            EntityEnum::GPT_IMAGE_2->value => EntityEnum::GPT_IMAGE_2,
            default                        => EntityEnum::GPT_IMAGE_1_5,
        };
    }

    /**
     * Process image edit with OpenAI gpt-image-1, gpt-image-1.5, or gpt-image-2.
     */
    public function processWithOpenAI(AiChatProImageModel $record, EntityEnum $entity, string $prompt, string $imageUrl, ?string $maskUrl, string $editTab = 'smart_edit'): void
    {
        Helper::setOpenAiKey();

        $service = (new CreateImageEditService)
            ->setModel($entity->value)
            ->setPrompt($prompt)
            ->setSize('auto')
            ->setImages([$imageUrl]);

        if ($maskUrl) {
            $service->setMask($maskUrl);
        }

        // gpt-image-2 does not support transparent backgrounds (docs); normalizer
        // would coerce it anyway, but skip the call so the intent is explicit.
        if ($editTab === 'remove_background' && $entity !== EntityEnum::GPT_IMAGE_2) {
            $service->setBackground('transparent');
        }

        $result = $service->generate();

        if ($result['status']) {
            $record->markAsCompleted([$result['path']]);
            $this->saveToContentManager($prompt, $result['path'], $entity, $record);
        } else {
            $record->markAsFailed($result['message'] ?? __('Image generation failed'));
        }
    }

    /**
     * Process image edit with fal.ai models.
     */
    public function processWithFalAI(AiChatProImageModel $record, EntityEnum $entity, string $prompt, string $imageUrl, ?string $maskUrl = null): void
    {
        // Build image references: original image + mask if available
        $imageReference = [$imageUrl];
        if ($maskUrl) {
            $imageReference[] = $maskUrl;
        }

        if (FalAIService::isGrokModel($entity)) {
            $this->processGrokSyncFalAI($record, $entity, $prompt, $imageReference);

            return;
        }

        // FalAIService::generate() auto-switches to edit variants when image_reference is provided.
        $requestId = FalAIService::generate($prompt, $entity, [
            'image_reference' => $imageReference,
            'aspect_ratio'    => 'auto',
        ]);

        $record->update([
            'metadata' => array_merge($record->metadata ?? [], [
                'requests' => [$requestId],
            ]),
        ]);

        dispatch(new PollChatImageGenerationJob($record->id, $requestId))->delay(now()->addSeconds(5));
    }

    private function processGrokSyncFalAI(AiChatProImageModel $record, EntityEnum $entity, string $prompt, array $imageReference): void
    {
        $result = FalAIService::generateGrokSync($prompt, $entity, [
            'image_reference' => $imageReference,
            'aspect_ratio'    => 'auto',
        ]);

        $imageUrl = (string) data_get($result, 'images.0.url', '');

        if ($imageUrl === '') {
            $record->markAsFailed(__('Image generation failed.'));

            return;
        }

        $response = Http::timeout(120)->get($imageUrl);
        if (! $response->successful()) {
            $record->markAsFailed(__('Failed to download generated image.'));

            return;
        }

        $extension = pathinfo((string) parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        $name = uniqid('img_', true) . '.' . $extension;
        $directory = $record->user_id
            ? "media/images/u-{$record->user_id}"
            : 'guest';

        $relativePath = "{$directory}/{$name}";
        Storage::disk('public')->put($relativePath, $response->body());

        $record->markAsCompleted(['/uploads/' . $relativePath]);
        $this->saveToContentManager($prompt, '/uploads/' . $relativePath, $entity, $record);
    }

    /**
     * Save generated image to UserOpenai so it appears in Content Manager
     * (or in the originating extension's gallery, when a source is supplied).
     */
    private function saveToContentManager(string $prompt, string $outputPath, EntityEnum $entity, ?AiChatProImageModel $record = null): void
    {
        try {
            $user = Auth::user();
            $imageGenerator = OpenAIGenerator::query()->where('slug', 'ai_image_generator')->first();

            if (! $imageGenerator || ! $user) {
                return;
            }

            $source = $record?->params['source'] ?? null;

            $payload = [
                'title'     => __('AI Image Edit'),
                'slug'      => Str::random(7) . Str::slug($user->fullName()) . '-workbook',
                'user_id'   => $user->id,
                'team_id'   => $user->team_id,
                'openai_id' => $imageGenerator->id,
                'input'     => $prompt,
                'response'  => 'EDITED_IMAGE',
                'output'    => $outputPath,
                'hash'      => Str::random(256),
                'credits'   => 1,
                'words'     => 0,
                'storage'   => UserOpenai::STORAGE_LOCAL,
                'engine'    => $entity->engine()->value,
                'model'     => $entity->value,
            ];

            if ($source === 'ai-photoshoot') {
                $payload['response'] = 'APS';
            }

            $userOpenai = UserOpenai::create($payload);

            if ($source === 'ai-photoshoot') {
                $userOpenai->is_ai_photo_studio = true;
                $userOpenai->save();
            }
        } catch (Throwable $e) {
            Log::error('Failed to save edited image to content manager', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save an uploaded file and return its full URL.
     */
    private function saveUploadedFile($file, $mask = false): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        try {

            if ($mask) {
                $basePath = 'images';
            } else {
                $basePath = auth()->check()
                    ? 'media/images/u-' . auth()->id()
                    : 'media/images/guest';
            }

            $path = processSecureFileUpload($file, $basePath);

            if (! $path) {
                return null;
            }

            return url($path);
        } catch (Exception $e) {
            Log::error('Failed to save uploaded image for edit', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Download a remote image and save it locally.
     */
    private function downloadAndSaveImage(string $remoteUrl): ?string
    {
        try {
            $contents = file_get_contents($remoteUrl);

            if ($contents === false) {
                return null;
            }

            $extension = pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $basePath = auth()->check()
                ? 'media/images/u-' . auth()->id()
                : 'media/images/guest';

            $fileName = $basePath . '/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($fileName, $contents);

            return '/uploads/' . $fileName;
        } catch (Throwable $e) {
            Log::error('Failed to download and save fal.ai image', [
                'url'   => $remoteUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
