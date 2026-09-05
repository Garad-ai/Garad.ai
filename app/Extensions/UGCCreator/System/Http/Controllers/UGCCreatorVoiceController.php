<?php

declare(strict_types=1);

namespace App\Extensions\UGCCreator\System\Http\Controllers;

use App\Extensions\UGCCreator\System\Services\UGCCreatorRegistry;
use App\Http\Controllers\Controller;
use App\Services\ElevenlabsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class UGCCreatorVoiceController extends Controller
{
    public function index(): JsonResponse
    {
        $provider = UGCCreatorRegistry::getVoiceoverProvider();
        $data = require dirname(__DIR__, 2) . '/Data/voices.php';

        if ($provider === 'elevenlabs') {
            return response()->json([
                'data' => [
                    'provider'  => $provider,
                    'languages' => [],
                    'voices'    => $this->cachedElevenlabsVoices(),
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'provider'  => $provider,
                'languages' => $data['openai_languages'],
                'voices'    => $data['openai_voices'],
            ],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string, preview_url: string|null}>
     */
    private function cachedElevenlabsVoices(): array
    {
        return Cache::remember('ugc-creator:elevenlabs-voices', 300, function (): array {
            try {
                $voices = (new ElevenlabsService)->getVoices();
            } catch (Throwable) {
                return [];
            }

            return collect($voices)
                ->map(fn (array $voice) => [
                    'value'       => $voice['voice_id'],
                    'label'       => $voice['name'],
                    'preview_url' => $voice['preview_url'] ?? null,
                ])
                ->values()
                ->all();
        });
    }
}
