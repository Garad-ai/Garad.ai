<?php

namespace App\Extensions\MarketingBot\System\Services\Whatsapp;

use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsappSenderService
{
    private const GRAPH_API_VERSION = 'v19.0';

    private const GRAPH_API_BASE = 'https://graph.facebook.com';

    public ?WhatsappChannel $whatsappChannel = null;

    public function setWhatsappChannel(WhatsappChannel $channel): self
    {
        $this->whatsappChannel = $channel;

        return $this;
    }

    /**
     * @return array{status: bool, message: string, properties?: array<string, mixed>}
     */
    public function sendText(string $receiver, ?string $message, ?string $mediaUrl = null): array
    {
        try {
            $to = $this->normalizePhone($receiver);

            if ($mediaUrl) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'image',
                    'image'             => ['link' => url($mediaUrl)],
                    'caption'           => $message,
                ];
            } else {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['body' => $message ?? ''],
                ];
            }

            Log::channel('stack')->info('[Meta API] Sending message', [
                'url'     => $this->messagesUrl(),
                'payload' => $payload,
            ]);

            $response = $this->request()->post($this->messagesUrl(), $payload);

            Log::channel('stack')->info('[Meta API] Response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if ($response->failed()) {
                return [
                    'status'  => false,
                    'message' => $response->json('error.message', 'Meta API request failed'),
                ];
            }

            return [
                'status'     => true,
                'message'    => trans('Message sent'),
                'properties' => $this->properties($response),
            ];
        } catch (Exception $exception) {
            Log::channel('stack')->error('[Meta API] Exception', [
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ]);

            return [
                'status'  => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, string>  $variables
     *
     * @return array{status: bool, message: string, properties?: array<string, mixed>}
     */
    public function sendTemplate(string $receiver, string $templateName, string $language, array $variables = []): array
    {
        try {
            $to = $this->normalizePhone($receiver);

            $components = [];

            if (! empty($variables)) {
                $components[] = [
                    'type'       => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $variables),
                ];
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $language],
                    'components' => $components,
                ],
            ];

            Log::channel('stack')->info('[Meta API] Sending template message', [
                'url'     => $this->messagesUrl(),
                'payload' => $payload,
            ]);

            $response = $this->request()->post($this->messagesUrl(), $payload);

            Log::channel('stack')->info('[Meta API] Template response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if ($response->failed()) {
                return [
                    'status'  => false,
                    'message' => $response->json('error.message', 'Meta API template request failed'),
                ];
            }

            return [
                'status'     => true,
                'message'    => trans('Message sent'),
                'properties' => $this->properties($response),
            ];
        } catch (Exception $exception) {
            Log::channel('stack')->error('[Meta API] Template exception', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'status'  => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function properties(Response $response): array
    {
        $body = $response->json();

        return [
            'messaging_product' => $body['messaging_product'] ?? null,
            'contacts'          => $body['contacts'] ?? [],
            'messages'          => $body['messages'] ?? [],
        ];
    }

    private function messagesUrl(): string
    {
        return sprintf(
            '%s/%s/%s/messages',
            self::GRAPH_API_BASE,
            self::GRAPH_API_VERSION,
            $this->whatsappChannel->meta_phone_number_id
        );
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->whatsappChannel->meta_access_token)
            ->acceptJson()
            ->asJson();
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[\s\-\+]+/', '', $phone);

        return $cleaned;
    }
}
