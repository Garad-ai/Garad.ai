<?php

namespace App\Extensions\AIImagePro\System\Jobs;

use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Models\SettingTwo;
use App\Services\Ai\AIImageClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PollImageGenerationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 30; // Maximum attempts

    public int $backoff = 15; // Seconds between retries

    public function __construct(
        private int $recordId,
        private readonly string $requestId
    ) {}

    public function handle(): void
    {
        $record = AiImageProModel::find($this->recordId);
        if (! $record) {
            return;
        }

        $result = AIImageClient::checkStatus($this->requestId, $record->model);

        if (data_get($result, 'image.url') || data_get($result, 'images.0.url')) {
            $this->handleCompleted($record, $result);

            return;
        }

        if (isset($result['status']) && $result['status'] === 'FAILED') {
            $error = is_array($result['error']) ? collect($result['error'])->pluck('msg')->join('; ') : ($result['error'] ?? null);

            if ($error !== 'Request is still in progress') {
                $record->markAsFailed($error ?? __('Image generation failed.'));

                return;
            }
        }

        if ($this->attempts() >= $this->tries) {
            $record->markAsFailed(__('Image generation timed out. Please try again later.'));

            return;
        }

        $this->release($this->backoff);
    }

    private function handleCompleted(AiImageProModel $record, array $result): void
    {
        $imageUrl = (string) (data_get($result, 'image.url') ?: data_get($result, 'images.0.url', ''));

        if ($imageUrl === '') {
            $record->markAsFailed(__('Failed to download generated image.'));

            return;
        }

        $imagePayload = $this->resolveImagePayload(
            $imageUrl,
            (string) (data_get($result, 'image.content_type') ?: data_get($result, 'images.0.content_type', ''))
        );

        if (! $imagePayload) {
            $record->markAsFailed(__('Failed to download generated image.'));

            return;
        }

        $imageData = $imagePayload['binary'];
        $mimeType = $imagePayload['mime'] ?: 'image/png';
        $extension = mimeToExtension($mimeType);
        if (! $extension) {
            $extension = pathinfo((string) parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        }

        $name = uniqid('img_', true) . '.' . $extension;
        $directory = $record->user_id
            ? "media/images/u-{$record->user_id}"
            : 'media/images/guest';

        $relativePath = "{$directory}/{$name}";
        $imageStorage = SettingTwo::getCache()?->getAttribute('ai_image_storage');
        $storedPath = '/uploads/' . $relativePath;

        if ($imageStorage === 'r2') {
            Storage::disk('r2')->put($relativePath, $imageData);
            $storedPath = Storage::disk('r2')->url($relativePath);
        } elseif ($imageStorage === 's3') {
            Storage::disk('s3')->put($relativePath, $imageData);
            $storedPath = Storage::disk('s3')->url($relativePath);
        } else {
            Storage::disk('public')->put($relativePath, $imageData);
            $record->saveDimensions($relativePath);
        }

        $record->markAsCompleted(
            [$storedPath],
            [
                'model'  => $record->model,
                'count'  => $record->params['image_count'] ?? 1,
                'params' => $record->params,
            ]
        );
    }

    private function resolveImagePayload(string $imageUrl, string $fallbackMimeType = ''): ?array
    {
        if (str_starts_with($imageUrl, 'data:image')) {
            if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $imageUrl, $matches)) {
                return null;
            }

            $binary = base64_decode(str_replace(' ', '+', $matches[2]), true);
            if ($binary === false) {
                return null;
            }

            return [
                'binary' => $binary,
                'mime'   => $matches[1],
            ];
        }

        $response = Http::timeout(120)->get($imageUrl);
        if (! $response->successful()) {
            return null;
        }

        $mimeType = $response->header('Content-Type');
        if (is_string($mimeType)) {
            $mimeType = trim(strtok($mimeType, ';'));
        } else {
            $mimeType = '';
        }

        return [
            'binary' => $response->body(),
            'mime'   => $mimeType ?: $fallbackMimeType,
        ];
    }
}
