<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Controllers;

use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use App\Http\Controllers\Controller;
use App\Services\ElevenlabsService;
use Illuminate\Http\JsonResponse;
use Throwable;

class UGCFactoryVoiceController extends Controller
{
    public function index(): JsonResponse
    {
        $provider = UGCFactoryRegistry::getVoiceoverProvider();
        $data = require dirname(__DIR__, 2) . '/Data/voices.php';

        if ($provider === 'elevenlabs') {
            return response()->json([
                'provider'  => 'elevenlabs',
                'languages' => [],
                'voices'    => $this->elevenlabsVoices(),
            ]);
        }

        return response()->json([
            'provider'  => 'openai',
            'languages' => $data['openai_languages'],
            'voices'    => $data['openai_voices'],
        ]);
    }

    private function elevenlabsVoices(): array
    {
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
    }
}
