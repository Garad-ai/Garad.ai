<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Connectors;

use App\Extensions\AIAgent\System\Connectors\ValueObjects\IncomingMessage;
use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentConversation;
use App\Extensions\AIAgent\System\Models\AIAgentMessage;

class IncomingMessageHandler
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    /**
     * Persist the inbound message and fire ChannelMessage triggers.
     */
    public function handle(IncomingMessage $message, AIAgentChannel $channel): void
    {
        $conversation = AIAgentConversation::query()->firstOrCreate(
            ['channel_id' => $channel->id, 'sender_id' => $message->senderId],
        );

        $message = new IncomingMessage(
            channel: $message->channel,
            senderId: $message->senderId,
            text: $message->text,
            attachments: $message->attachments,
            rawPayload: $message->rawPayload,
            conversationId: $conversation->id,
        );

        AIAgentMessage::query()->create([
            'channel_id'      => $channel->id,
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'sender_id'       => $message->senderId,
            'content'         => $message->text,
            'raw_payload'     => $message->rawPayload,
            'status'          => 'received',
        ]);

        $conversation->update(['last_message_at' => now()]);
        $channel->update(['last_message_at' => now()]);

        $this->engine->fireChannelMessageTrigger($channel, $message);
    }
}
