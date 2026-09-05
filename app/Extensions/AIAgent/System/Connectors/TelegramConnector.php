<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors;

use App\Extensions\AIAgent\System\Connectors\Contracts\ConnectorInterface;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\OutgoingMessage;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramConnector implements ConnectorInterface
{
    private AIAgentChannel $channel;

    public function __construct(private readonly MessageFormatter $formatter) {}

    public function setChannel(AIAgentChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function send(OutgoingMessage $message): void
    {
        $token = $this->channel->getCredential('telegram_token');

        if (empty($token)) {
            throw new Exception('Telegram token not configured for channel: ' . $this->channel->name);
        }

        $formatted = $this->formatter->format($message->text, $message->channel);

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $response = Http::post($url, [
            'chat_id'    => $message->recipientId,
            'text'       => $formatted,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed() || ! $response->json('ok')) {
            throw new Exception('Telegram send failed: ' . $response->json('description', $response->body()));
        }
    }

    public function registerWebhook(): void
    {
        $token = $this->channel->getCredential('telegram_token');

        if (empty($token)) {
            throw new Exception('Telegram token not configured for channel: ' . $this->channel->name);
        }

        $webhookUrl = str_replace('http://', 'https://', route('api.ai-agent.telegram.webhook', ['channel' => $this->channel->id]));

        Log::info('[AIAgent] Registering Telegram webhook', [
            'channel_id'   => $this->channel->id,
            'channel_name' => $this->channel->name,
            'webhook_url'  => $webhookUrl,
        ]);

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
        ]);

        Log::info('[AIAgent] Telegram setWebhook response', [
            'status'      => $response->status(),
            'ok'          => $response->json('ok'),
            'result'      => $response->json('result'),
            'description' => $response->json('description'),
            'body'        => $response->body(),
        ]);

        if ($response->failed() || $response->json('ok') !== true) {
            throw new Exception('Telegram webhook registration failed: ' . $response->json('description', $response->body()));
        }
    }
}
