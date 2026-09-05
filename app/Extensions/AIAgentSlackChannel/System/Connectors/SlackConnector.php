<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentSlackChannel\System\Connectors;

use App\Extensions\AIAgent\System\Connectors\Contracts\ConnectorInterface;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\OutgoingMessage;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackConnector implements ConnectorInterface
{
    private const SLACK_API_URL = 'https://slack.com/api';

    private AIAgentChannel $channel;

    public function __construct(private readonly MessageFormatter $formatter) {}

    public function setChannel(AIAgentChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function send(OutgoingMessage $message): void
    {
        $botToken = $this->channel->getCredential('slack_bot_token');

        if (empty($botToken)) {
            throw new Exception('Slack bot_token is required for channel: ' . $this->channel->name);
        }

        $formatted = $this->formatter->format($message->text, $message->channel);

        $response = Http::withToken($botToken)
            ->post(self::SLACK_API_URL . '/chat.postMessage', [
                'channel' => $message->recipientId,
                'text'    => $formatted,
            ]);

        if ($response->failed() || ! $response->json('ok')) {
            throw new Exception('Slack send failed: ' . $response->json('error', $response->body()));
        }
    }

    /**
     * Slack webhooks must be registered manually in the Slack App > Event Subscriptions dashboard.
     * This method logs the webhook URL the user should register there.
     */
    public function registerWebhook(): void
    {
        $webhookUrl = route('api.ai-agent.slack.webhook', ['channel' => $this->channel->id]);

        Log::info('[AIAgentSlackChannel] Webhook URL to register in Slack App Event Subscriptions', [
            'channel_id'  => $this->channel->id,
            'webhook_url' => $webhookUrl,
        ]);
    }
}
