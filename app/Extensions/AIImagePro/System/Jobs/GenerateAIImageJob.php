<?php

namespace App\Extensions\AIImagePro\System\Jobs;

use App\Extensions\AIImagePro\System\Models\AiImageProModel;
use App\Services\Ai\AIImageClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAIImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected array $payload;

    protected ?int $userId;

    protected $driver;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, ?int $userId, $driver)
    {
        $this->payload = $payload;
        $this->userId = $userId;
        $this->driver = $driver;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $record = AiImageProModel::find($this->payload['record_id'] ?? null);

        if (! $record) {
            Log::warning('AI image generation record not found', [
                'record_id' => $this->payload['record_id'] ?? null,
                'user_id'   => $this->userId,
            ]);

            $this->driver?->increaseCredit($this->driver?->calculate());

            return;
        }

        try {
            $record->markAsStarted();

            $imageCount = $record->params['image_count'] ?? 1;
            $paths = [];
            $allAsync = true;

            for ($i = 0; $i < $imageCount; $i++) {
                $result = AIImageClient::generate($this->payload);

                // Check if we got an immediate result or need to poll
                if (isset($result['status']) && $result['status'] === 'IN_QUEUE') {
                    // Store the request_id for later polling
                    $requests = $record->metadata['requests'] ?? [];
                    $requests[$i] = $result['request_id'];

                    $record->update([
                        'metadata' => array_merge($record->metadata ?? [], [
                            'requests' => $requests,
                        ]),
                    ]);

                    // Dispatch a job to poll for the result
                    dispatch(new PollImageGenerationJob($record->id, $result['request_id']))->delay(now()->addSeconds(5));
                } else {
                    $allAsync = false;
                    if (isset($result[0])) {
                        $paths[] = $this->storeImage($result, $record);
                    }
                }
            }

            if (! empty($paths) && ! $allAsync) {
                $record->markAsCompleted($paths, [
                    'model'         => $record->model,
                    'count'         => count($paths),
                    'params'        => $record->params,
                    'pending_async' => $imageCount - count($paths),
                ]);
            }
        } catch (Throwable $e) {
            $record->markAsFailed($e->getMessage());
            $this->driver?->increaseCredit($this->driver?->calculate());
            Log::error(__('AI image generation failed'), [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'user_id'   => $this->userId,
                'record_id' => $record->id,
            ]);
        }
    }

    private function storeImage($imageData, AiImageProModel $record): string
    {
        $name = uniqid('img_', true) . '.png';
        $directory = $record->user_id
            ? "media/images/u-{$record->user_id}"
            : 'guest';

        $filename = "{$directory}/{$name}";
        Storage::disk('public')->put($filename, $imageData[0]);

        $record->saveDimensions($filename);

        return "/uploads/{$filename}";
    }
}
