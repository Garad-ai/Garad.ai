<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers\Webhook;

use App\Extensions\AIAgent\System\Connectors\IncomingMessageHandler;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __construct(private readonly IncomingMessageHandler $handler) {}

    public function handle(int $channel, Request $request): array
    {
        // Validate minimal Telegram payload
        if (! $request->has('update_id') && ! $request->has('message')) {
            return ['ok' => false];
        }

        $aiAgentChannel = AIAgentChannel::query()->find($channel);

        if ($aiAgentChannel === null) {
            return ['ok' => false];
        }

        // Use chat.id so {sender_id} can be used directly as recipient for replies
        $senderId = (string) $request->input('message.chat.id', $request->input('message.from.id', ''));

        if (empty($senderId)) {
            return ['ok' => true];
        }

        // Caption is used as text for media messages
        $text = (string) $request->input('message.text', $request->input('message.caption', ''));

        $token = $aiAgentChannel->getCredential('telegram_token');
        $attachments = $token ? $this->extractAttachments($request, $token) : [];

        // Nothing to process if no text and no attachments
        if (empty($text) && empty($attachments)) {
            return ['ok' => true];
        }

        $incomingMessage = new IncomingMessage(
            channel: ChannelEnum::Telegram,
            senderId: $senderId,
            text: $text,
            attachments: $attachments,
            rawPayload: $request->all(),
        );

        $this->handler->handle($incomingMessage, $aiAgentChannel);

        return ['ok' => true];
    }

    /**
     * Extract downloadable attachments (photos, documents, stickers) from the Telegram message.
     *
     * @return array<int, array{type: string, mime_type: string, base64: string, filename: string}>
     */
    private function extractAttachments(Request $request, string $token): array
    {
        $message = $request->input('message', []);
        $attachments = [];

        // Photo — pick the largest size (last element)
        if (! empty($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']);
            $attachment = $this->downloadTelegramFile($token, $photo['file_id'] ?? '', 'image/jpeg');

            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        // Document — only images and PDFs under 5 MB
        if (! empty($message['document']) && is_array($message['document'])) {
            $doc = $message['document'];
            $mimeType = $doc['mime_type'] ?? 'application/octet-stream';
            $fileSize = (int) ($doc['file_size'] ?? 0);

            if (
                $fileSize < 5 * 1024 * 1024
                && (str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf')
            ) {
                $attachment = $this->downloadTelegramFile($token, $doc['file_id'] ?? '', $mimeType, $doc['file_name'] ?? null);

                if ($attachment !== null) {
                    $attachments[] = $attachment;
                }
            }
        }

        // Sticker (WebP image)
        if (! empty($message['sticker']) && is_array($message['sticker'])) {
            $attachment = $this->downloadTelegramFile($token, $message['sticker']['file_id'] ?? '', 'image/webp');

            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * Download a file from Telegram and return it as a base64-encoded attachment array.
     *
     * @return array{type: string, mime_type: string, base64: string, filename: string}|null
     */
    private function downloadTelegramFile(string $token, string $fileId, string $defaultMimeType = 'application/octet-stream', ?string $filename = null): ?array
    {
        if (empty($fileId)) {
            return null;
        }

        try {
            $fileResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId]);

            if ($fileResponse->failed() || $fileResponse->json('ok') !== true) {
                Log::warning('[AIAgent] Telegram getFile failed', ['file_id' => $fileId]);

                return null;
            }

            $filePath = $fileResponse->json('result.file_path');

            if (empty($filePath)) {
                return null;
            }

            $downloadResponse = Http::timeout(30)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

            if ($downloadResponse->failed()) {
                Log::warning('[AIAgent] Telegram file download failed', ['file_id' => $fileId, 'path' => $filePath]);

                return null;
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                'pdf'         => 'application/pdf',
                default       => $defaultMimeType,
            };

            return [
                'type'      => str_starts_with($mimeType, 'image/') ? 'image' : 'document',
                'mime_type' => $mimeType,
                'base64'    => base64_encode($downloadResponse->body()),
                'filename'  => $filename ?? basename($filePath),
            ];
        } catch (Throwable $e) {
            Log::warning('[AIAgent] Telegram file download exception', ['file_id' => $fileId, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
