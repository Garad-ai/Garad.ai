<?php

namespace App\Extensions\AiChatProImageChat\System\Jobs;

use App\Extensions\AiChatProImageChat\System\Models\AiChatProImageModel;
use App\Services\Ai\AiCompletionService;
use App\Services\Ai\AIImageClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateAIChatProImageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected array $payload;

    protected $driver;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, $driver)
    {
        $this->payload = $payload;
        $this->driver = $driver;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $record = AiChatProImageModel::find($this->payload['record_id'] ?? null);

        if (! $record) {
            Log::warning('AI image generation record not found', [
                'record_id' => $this->payload['record_id'] ?? null,
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
                    dispatch(new PollChatImageGenerationJob($record->id, $result['request_id']))->delay(now()->addSeconds(5));
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

            $this->generateSuggestions($record);
        } catch (Throwable $e) {
            $record->markAsFailed($e->getMessage());
            $this->driver?->increaseCredit($this->driver?->calculate());
            Log::error(__('AI image generation failed'), [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'record_id' => $record->id,
            ]);
        }
    }

    private function storeImage($imageData, $record): string
    {
        $name = uniqid('img_', true) . '.png';
        $directory = $record->user_id
            ? "media/images/u-{$record->user_id}"
            : 'guest';

        $filename = "{$directory}/{$name}";
        Storage::disk('public')->put($filename, $imageData[0]);

        return "/uploads/{$filename}";
    }

    private function generateSuggestions(AiChatProImageModel $record): void
    {
        $systemPrompt = <<<'PROMPT'
        You are an expert image editing consultant.

        Based on the user's original image generation prompt, generate:
        1. A short friendly brief (1 sentence).
        2. An array of 5 short, actionable image edit suggestions.

        STRICT OUTPUT RULES:
        - Return ONLY valid JSON
        - No markdown
        - No explanations
        - No extra text
        - No additional keys

        JSON FORMAT (must match exactly):
        {
          "brief": "short friendly sentence",
          "suggestions": ["Suggestion 1","Suggestion 2","Suggestion 3","Suggestion 4","Suggestion 5"]
        }

        Suggestions rules:
        - 1–3 words if possible
        - Visual changes only
        - Concrete and actionable
        - Creative but realistic

        Example:
        {
          "brief": "Nice — I generated that image for you. Want to tweak anything?",
          "suggestions": ["Create a variation","Make it dramatic","Remove rain","Change lighting","Simplify background"]
        }
        PROMPT;

        try {
            $responseText = app(AiCompletionService::class)->complete(
                $systemPrompt,
                "Original prompt: {$record->prompt}"
            );

            $payload = json_decode($responseText, true, 512, JSON_THROW_ON_ERROR);

            if (
                ! isset($payload['brief'], $payload['suggestions']) ||
                ! is_array($payload['suggestions']) ||
                count($payload['suggestions']) !== 5
            ) {
                throw new RuntimeException('Invalid AI response structure.');
            }

        } catch (Throwable $e) {
            $payload = [
                'brief'       => 'Nice — I generated that image for you. Want to make any changes?',
                'suggestions' => [
                    'Create a variation',
                    'Make it dramatic',
                    'Remove background',
                    'Adjust lighting',
                    'Change colors',
                ],
            ];
        }

        $record->update([
            'suggestions_response' => $payload,
        ]);
    }
}
