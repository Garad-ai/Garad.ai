<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Connectors\ConnectorRegistry;
use App\Extensions\AIAgent\System\Connectors\Contracts\ConnectorInterface;
use App\Extensions\AIAgent\System\Connectors\ValueObjects\OutgoingMessage;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentMessage;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use Exception;

class SendMessageAction implements AIAgentActionInterface
{
    public function __construct(private readonly ConnectorRegistry $connectorRegistry) {}

    /**
     * Config keys:
     *   - channel_id (int)      : ID of the AIAgentChannel to send through.
     *                             Falls back to the firing channel, then the workflow's trigger channel.
     *   - recipient_id (string) : Chat/user ID on the platform (can use {{context_key}} syntax)
     *   - message (string)      : Message template (can use {{context_key}} syntax)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $channelId = $this->resolveChannelId($config, $context, $workflow);
        $recipientId = $this->interpolate($config['recipient_id'] ?? '', $context);
        $messageText = $this->interpolate($config['message'] ?? '', $context);

        if (empty($channelId)) {
            throw new Exception('SendMessageAction: channel_id is required — pick a channel on this step, or on the workflow trigger.');
        }
        if (empty($recipientId)) {
            throw new Exception('SendMessageAction: recipient_id is required (got empty string — check your template references a valid context key).');
        }
        if (empty($messageText)) {
            throw new Exception('SendMessageAction: message resolved to an empty string — the referenced context variable (e.g. {ai_response}) may be empty or undefined.');
        }

        /** @var AIAgentChannel $channel */
        $channel = AIAgentChannel::query()->findOrFail((int) $channelId);

        $outgoing = new OutgoingMessage(
            channel: $channel->type,
            recipientId: $recipientId,
            text: $messageText,
        );

        if (! ($context['web_reply_mode'] ?? false)) {
            $connector = $this->resolveConnector($channel->type, $channel);
            $connector->send($outgoing);
        }

        $recordChannelId = ($context['web_reply_mode'] ?? false)
            ? ($context['web_channel_id'] ?? $channel->id)
            : $channel->id;

        AIAgentMessage::query()->create([
            'channel_id'      => $recordChannelId,
            'conversation_id' => $context['conversation_id'] ?? null,
            'workflow_run_id' => $run->id,
            'direction'       => 'outbound',
            'sender_id'       => $context['sender_id'] ?? null,
            'content'         => $messageText,
            'status'          => 'sent',
        ]);

        return array_merge($context, ['last_sent_message' => $messageText]);
    }

    /**
     * Resolve the channel to send through. Workflow templates ship without a channel_id on their
     * send_message steps, so fall back to the channel that fired the run and then to the channel
     * configured on the workflow trigger.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function resolveChannelId(array $config, array $context, AIAgentWorkflow $workflow): ?int
    {
        $candidates = [
            $config['channel_id'] ?? null,
            $context['web_reply_mode'] ?? false ? $context['web_channel_id'] ?? null : null,
            $context['channel_id'] ?? null,
            $workflow->trigger_config['channel_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! empty($candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }

    public function getCategory(): string
    {
        return 'channels';
    }

    public function getLabel(): string
    {
        return 'Send Message';
    }

    public function getDescription(): string
    {
        return 'Send a message to a connected channel (Telegram, WhatsApp, etc.).';
    }

    public function getIcon(): string
    {
        return 'tabler-send';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'channel_id'   => ['type' => 'integer', 'description' => 'ID of the channel to send through.'],
                'recipient_id' => ['type' => 'string', 'description' => 'Recipient chat ID (supports {sender_id}).'],
                'message'      => ['type' => 'string', 'description' => 'Message text (supports context variables).'],
            ],
        ];
    }

    /**
     * @throws Exception
     */
    private function resolveConnector(ChannelEnum $channel, AIAgentChannel $model): ConnectorInterface
    {
        return $this->connectorRegistry->resolve($channel, $model);
    }

    /**
     * Replace {key} placeholders in a string with context values.
     */
    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($context): string {
            $value = $context[$matches[1]] ?? $matches[0];

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: $matches[0];
            }

            return (string) $value;
        }, $template);
    }
}
