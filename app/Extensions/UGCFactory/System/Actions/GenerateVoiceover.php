<?php

declare(strict_types=1);

namespace App\Extensions\UGCFactory\System\Actions;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use App\Models\SettingTwo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates a voiceover MP3 from a script using either OpenAI TTS or ElevenLabs.
 *
 * Mirrors the OpenAI / ElevenLabs branches of TTSController::processOpenAISpeech /
 * processElevenLabsSpeech. Writes the audio to the public disk and returns the
 * stored relative path (e.g. "ugc-factory/audio/<uuid>.mp3").
 */
class GenerateVoiceover
{
    /**
     * @return array{path: string, provider: string, voice_id: string}
     */
    public function __invoke(string $provider, string $voiceId, string $script, ?string $language = null): array
    {
        $audio = match ($provider) {
            'openai'     => $this->openai($voiceId, $script, $language),
            'elevenlabs' => $this->elevenlabs($voiceId, $script),
            default      => throw new RuntimeException(__('Unsupported voiceover provider.')),
        };

        $path = 'ugc-factory/audio/' . Str::uuid()->toString() . '.mp3';
        Storage::disk('public')->put($path, $audio);

        return [
            'path'     => $path,
            'provider' => $provider,
            'voice_id' => $voiceId,
        ];
    }

    private function openai(string $voiceId, string $script, ?string $language): string
    {
        $apiKey = ApiHelper::setOpenAiKey();

        $payload = [
            'model' => EntityEnum::TTS_1->value,
            'input' => $script,
            'voice' => $voiceId,
        ];

        if ($language) {
            $payload['language'] = $language;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/audio/speech', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(__('OpenAI TTS request failed: :detail', [
                'detail' => $response->json('error.message') ?? 'unknown',
            ]));
        }

        return $response->body();
    }

    private function elevenlabs(string $voiceId, string $script): string
    {
        $apiKey = SettingTwo::getCache()?->elevenlabs_api_key;

        if (! $apiKey) {
            throw new RuntimeException(__('ElevenLabs API key is not configured.'));
        }

        $response = Http::withHeaders([
            'xi-api-key'   => $apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'audio/mpeg',
        ])->timeout(120)->post('https://api.elevenlabs.io/v1/text-to-speech/' . $voiceId, [
            'text'           => $script,
            'model_id'       => 'eleven_multilingual_v2',
            'voice_settings' => [
                'similarity_boost'  => 0.75,
                'stability'         => 0.5,
                'use_speaker_boost' => true,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(__('ElevenLabs TTS request failed: :detail', [
                'detail' => $response->json('detail.message') ?? $response->json('detail') ?? 'unknown',
            ]));
        }

        return $response->body();
    }
}
