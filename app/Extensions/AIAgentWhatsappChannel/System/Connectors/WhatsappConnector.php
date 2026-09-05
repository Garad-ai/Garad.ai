<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentWhatsappChannel\System\Connectors;

use App\Extensions\AIAgent\System\Connectors\Contracts\ConnectorInterface;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\OutgoingMessage;
use App\Extensions\AIAgent\System\Formatters\MessageFormatter;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappConnector implements ConnectorInterface
{
    private const GRAPH_API_URL = 'https://graph.facebook.com/v20.0';

    private AIAgentChannel $channel;

    public function __construct(private readonly MessageFormatter $formatter) {}

    public function setChannel(AIAgentChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function send(OutgoingMessage $message): void
    {
        $phoneNumberId = $this->channel->getCredential('whatsapp_phone_number_id');
        $accessToken = $this->channel->getCredential('whatsapp_access_token');

        if (empty($phoneNumberId) || empty($accessToken)) {
            throw new Exception('WhatsApp phone_number_id and access_token are required for channel: ' . $this->channel->name);
        }

        $formatted = $this->formatter->format($message->text, $message->channel);

        $url = self::GRAPH_API_URL . "/{$phoneNumberId}/messages";

        $response = Http::withToken($accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to'                => $message->recipientId,
            'type'              => 'text',
            'text'              => ['body' => $formatted],
        ]);

        if ($response->failed() || ! $response->json('messages')) {
            throw new Exception('WhatsApp send failed: ' . $response->json('error.message', $response->body()));
        }
    }

    /**
     * Meta webhooks must be registered manually in the Meta App Dashboard.
     * This method logs the webhook URL the user should register there.
     */
    public function registerWebhook(): void
    {
        $webhookUrl = route('api.ai-agent.whatsapp.webhook', ['channel' => $this->channel->id]);

        Log::info('[AIAgentWhatsappChannel] Webhook URL to register in Meta App Dashboard', [
            'channel_id'  => $this->channel->id,
            'webhook_url' => $webhookUrl,
        ]);
    }
}
