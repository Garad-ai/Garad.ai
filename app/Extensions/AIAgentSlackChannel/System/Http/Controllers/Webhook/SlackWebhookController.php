<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentSlackChannel\System\Http\Controllers\Webhook;

use App\Extensions\AIAgent\System\Connectors\IncomingMessageHandler;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SlackWebhookController extends Controller
{
    public function __construct(private readonly IncomingMessageHandler $handler) {}

    /**
     * Handles all Slack webhook events (POST):
     * - url_verification: returns the challenge token
     * - event_callback: processes incoming messages
     */
    public function handle(int $channel, Request $request): array
    {
        $aiAgentChannel = AIAgentChannel::query()->find($channel);

        if ($aiAgentChannel === null) {
            return ['ok' => false];
        }

        if (! $this->verifySignature($request, $aiAgentChannel)) {
            abort(403, 'Invalid Slack signature.');
        }

        $type = $request->input('type');

        if ($type === 'url_verification') {
            return ['challenge' => $request->input('challenge')];
        }

        if ($type === 'event_callback') {
            $this->processEvent($request, $aiAgentChannel);
        }

        return ['ok' => true];
    }

    /**
     * Verifies the Slack request signature using HMAC-SHA256.
     * Signature format: v0=HMAC-SHA256(signing_secret, "v0:{timestamp}:{raw_body}")
     */
    private function verifySignature(Request $request, AIAgentChannel $aiAgentChannel): bool
    {
        $signingSecret = $aiAgentChannel->getCredential('slack_signing_secret');

        if (empty($signingSecret)) {
            return false;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $slackSignature = $request->header('X-Slack-Signature');

        if (empty($timestamp) || empty($slackSignature)) {
            return false;
        }

        $baseString = "v0:{$timestamp}:" . $request->getContent();
        $expectedSignature = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

        return hash_equals($expectedSignature, $slackSignature);
    }

    /**
     * Processes an event_callback payload and dispatches an IncomingMessage.
     */
    private function processEvent(Request $request, AIAgentChannel $aiAgentChannel): void
    {
        $event = $request->input('event', []);

        if (($event['type'] ?? '') !== 'message') {
            return;
        }

        // Ignore bot messages
        if (! empty($event['bot_id']) || ($event['subtype'] ?? '') === 'bot_message') {
            return;
        }

        $senderId = (string) ($event['user'] ?? '');

        if (empty($senderId)) {
            return;
        }

        $text = (string) ($event['text'] ?? '');
        $attachments = [];

        $botToken = $aiAgentChannel->getCredential('slack_bot_token');

        if ($botToken && ! empty($event['files'])) {
            foreach ($event['files'] as $file) {
                $attachment = $this->downloadSlackFile($botToken, $file);

                if ($attachment !== null) {
                    $attachments[] = $attachment;
                }
            }
        }

        if (empty($text) && empty($attachments)) {
            return;
        }

        $incomingMessage = new IncomingMessage(
            channel: ChannelEnum::Slack,
            senderId: $senderId,
            text: $text,
            attachments: $attachments,
            rawPayload: $request->all(),
        );

        $this->handler->handle($incomingMessage, $aiAgentChannel);
    }

    /**
     * Downloads a Slack file via its private URL and returns it as a base64 attachment.
     *
     * @param  array<string, mixed>  $file
     *
     * @return array{type: string, mime_type: string, base64: string, filename: string}|null
     */
    private function downloadSlackFile(string $botToken, array $file): ?array
    {
        $downloadUrl = $file['url_private_download'] ?? null;

        if (empty($downloadUrl)) {
            return null;
        }

        $mimeType = (string) ($file['mimetype'] ?? 'application/octet-stream');

        if (! str_starts_with($mimeType, 'image/') && $mimeType !== 'application/pdf') {
            return null;
        }

        try {
            $response = Http::withToken($botToken)
                ->timeout(30)
                ->get($downloadUrl);

            if ($response->failed()) {
                Log::warning('[AIAgentSlackChannel] Failed to download file', ['url' => $downloadUrl]);

                return null;
            }

            $filename = (string) ($file['name'] ?? ('file.' . $this->extensionFromMime($mimeType)));

            return [
                'type'      => str_starts_with($mimeType, 'image/') ? 'image' : 'document',
                'mime_type' => $mimeType,
                'base64'    => base64_encode($response->body()),
                'filename'  => $filename,
            ];
        } catch (Throwable $e) {
            Log::warning('[AIAgentSlackChannel] File download exception', ['url' => $downloadUrl, 'error' => $e->getMessage()]);

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
