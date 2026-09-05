<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Services\Providers;

use App\Models\SettingTwo;
use Illuminate\Support\Facades\Http;

class ElevenLabsPhoneService
{
    private string $endpoint = 'https://api.elevenlabs.io/';

    private ?string $apiKey;

    public function __construct()
    {
        $settings = SettingTwo::getCache();
        $this->apiKey = $settings?->elevenlabs_api_key;
    }

    public function listPhoneNumbers(): array
    {
        $res = Http::withHeaders($this->getHeaders())
            ->get($this->endpoint . 'v1/convai/phone-numbers');

        if ($res->successful()) {
            return $res->json('phone_numbers', []);
        }

        return [];
    }

    /**
     * Import a phone number into the ElevenLabs workspace. The payload's `provider`
     * discriminator selects the source: `twilio`, `sip_trunk`, or `exotel`.
     *
     * @param  array<string, mixed>  $payload
     *
     * @return array{phone_number_id?: string, detail?: mixed}
     */
    public function importPhoneNumber(array $payload): array
    {
        $res = Http::withHeaders($this->getHeaders())
            ->post($this->endpoint . 'v1/convai/phone-numbers', $payload);

        return $res->json() ?? [];
    }

    /**
     * Assign an agent to an existing phone number.
     *
     * @return array<string, mixed>
     */
    public function assignAgent(string $phoneNumberId, string $agentId): array
    {
        $res = Http::withHeaders($this->getHeaders())
            ->patch($this->endpoint . 'v1/convai/phone-numbers/' . $phoneNumberId, [
                'agent_id' => $agentId,
            ]);

        return $res->json() ?? [];
    }

    public function removePhoneNumber(string $phoneNumberId): void
    {
        Http::withHeaders($this->getHeaders())
            ->delete($this->endpoint . 'v1/convai/phone-numbers/' . $phoneNumberId);
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'xi-api-key'   => $this->apiKey,
        ];
    }
}
