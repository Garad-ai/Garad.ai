<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Http\Controllers;

use App\Extensions\UGCFactory\System\Models\UGCFactoryActor;
use App\Extensions\UGCFactory\System\Services\UGCFactoryRegistry;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\ElevenlabsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class UGCFactoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $userActors = UGCFactoryActor::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [UGCFactoryActor::TYPE_UPLOAD, UGCFactoryActor::TYPE_GENERATED])
            ->latest()
            ->get()
            ->map(fn (UGCFactoryActor $actor) => [
                'id'         => $actor->id,
                'type'       => $actor->type,
                'name'       => $actor->name,
                'image_url'  => $actor->image_url,
                'status'     => $actor->status,
                'created_at' => $actor->created_at?->toIso8601String(),
            ])
            ->all();

        $provider = UGCFactoryRegistry::getVoiceoverProvider();
        $voiceData = $this->buildVoiceData($provider);

        return view('ugc-factory::index', [
            'userActors'     => $userActors,
            'isDemo'         => Helper::appIsDemo(),
            'voiceProvider'  => $provider,
            'voiceLanguages' => $voiceData['languages'],
            'voiceOptions'   => $voiceData['voices'],
            'presets'        => UGCFactoryRegistry::getEnabledPresets(),
        ]);
    }

    /**
     * @return array{languages: array<int, array{value: string, label: string}>, voices: array<int, array{value: string, label: string, preview_url?: string|null}>}
     */
    private function buildVoiceData(string $provider): array
    {
        $data = require dirname(__DIR__, 2) . '/Data/voices.php';

        if ($provider === 'elevenlabs') {
            return [
                'languages' => [],
                'voices'    => $this->cachedElevenlabsVoices(),
            ];
        }

        return [
            'languages' => $data['openai_languages'],
            'voices'    => $data['openai_voices'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, preview_url: string|null}>
     */
    private function cachedElevenlabsVoices(): array
    {
        return Cache::remember('ugc-factory:elevenlabs-voices', 300, function (): array {
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
