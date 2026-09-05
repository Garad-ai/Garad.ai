<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentWhatsappChannel\System\Http\Controllers\Webhook;

use App\Extensions\AIAgent\System\Connectors\IncomingMessageHandler;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappWebhookController extends Controller
{
    private const GRAPH_API_URL = 'https://graph.facebook.com/v20.0';

    /** Supported media types and their default MIME types */
    private const MEDIA_TYPES = [
        'image'    => 'image/jpeg',
        'sticker'  => 'image/webp',
        'document' => 'application/octet-stream',
    ];

    public function __construct(private readonly IncomingMessageHandler $handler) {}

    /**
     * Meta webhook verification (GET).
     */
    public function verify(int $channel, Request $request): Response
    {
        $aiAgentChannel = AIAgentChannel::query()->find($channel);

        if ($aiAgentChannel === null) {
            return response('Channel not found.', 404);
        }

        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge', '');

        $storedToken = $aiAgentChannel->getCredential('whatsapp_verify_token');

        if ($mode === 'subscribe' && $verifyToken === $storedToken) {
            return response((string) $challenge, 200);
        }

        return response('Verification failed.', 403);
    }

    /**
     * Incoming WhatsApp message (POST).
     * Meta Cloud API payload structure:
     * entry[].changes[].value.messages[].{from, type, text.body, image, document, sticker}
     */
    public function handle(int $channel, Request $request): array
    {
        $aiAgentChannel = AIAgentChannel::query()->find($channel);

        if ($aiAgentChannel === null) {
            return ['ok' => false];
        }

        $accessToken = $aiAgentChannel->getCredential('whatsapp_access_token');
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $messageData) {
                    $senderId = (string) ($messageData['from'] ?? '');

                    if (empty($senderId)) {
                        continue;
                    }

                    $type = $messageData['type'] ?? '';
                    $text = '';
                    $attachments = [];

                    if ($type === 'text') {
                        $text = (string) ($messageData['text']['body'] ?? '');
                    } elseif (isset(self::MEDIA_TYPES[$type]) && $accessToken) {
                        $mediaData = $messageData[$type] ?? [];
                        $text = (string) ($mediaData['caption'] ?? '');
                        $attachment = $this->downloadWhatsappMedia($accessToken, $mediaData, $type);

                        if ($attachment !== null) {
                            $attachments[] = $attachment;
                        }
                    }

                    if (empty($text) && empty($attachments)) {
                        continue;
                    }

                    $incomingMessage = new IncomingMessage(
                        channel: ChannelEnum::Whatsapp,
                        senderId: $senderId,
                        text: $text,
                        attachments: $attachments,
                        rawPayload: $request->all(),
                    );

                    $this->handler->handle($incomingMessage, $aiAgentChannel);
                }
            }
        }

        return ['ok' => true];
    }

    /**
     * Download a WhatsApp media file via the Meta Graph API and return it as a base64 attachment.
     * Step 1: GET /{media_id} → retrieve the temporary download URL.
     * Step 2: GET that URL → download the binary content.
     *
     * @param  array<string, mixed>  $mediaData
     *
     * @return array{type: string, mime_type: string, base64: string, filename: string}|null
     */
    private function downloadWhatsappMedia(string $accessToken, array $mediaData, string $messageType): ?array
    {
        $mediaId = $mediaData['id'] ?? null;

        if (empty($mediaId)) {
            return null;
        }

        try {
            // Step 1: resolve the temporary download URL
            $metaResponse = Http::withToken($accessToken)
                ->timeout(10)
                ->get(self::GRAPH_API_URL . "/{$mediaId}");

            if ($metaResponse->failed()) {
                Log::warning('[AIAgentWhatsappChannel] Failed to resolve media URL', ['media_id' => $mediaId]);

                return null;
            }

            $downloadUrl = $metaResponse->json('url');

            if (empty($downloadUrl)) {
                return null;
            }

            // Step 2: download the file content
            $fileResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->get($downloadUrl);

            if ($fileResponse->failed()) {
                Log::warning('[AIAgentWhatsappChannel] Failed to download media', ['media_id' => $mediaId]);

                return null;
            }

            $mimeType = $mediaData['mime_type']
                ?? $metaResponse->json('mime_type')
                ?? self::MEDIA_TYPES[$messageType]
                ?? 'application/octet-stream';

            $filename = $mediaData['filename'] ?? ($mediaId . '.' . $this->extensionFromMime($mimeType));

            // Only forward images and PDFs; skip unsupported types
            if (! str_starts_with($mimeType, 'image/') && $mimeType !== 'application/pdf') {
                return null;
            }

            return [
                'type'      => str_starts_with($mimeType, 'image/') ? 'image' : 'document',
                'mime_type' => $mimeType,
                'base64'    => base64_encode($fileResponse->body()),
                'filename'  => $filename,
            ];
        } catch (Throwable $e) {
            Log::warning('[AIAgentWhatsappChannel] Media download exception', ['media_id' => $mediaId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
            default           => 'bin',
        };
    }
}
