<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;

class ListChatbotConversationsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - chatbot_id      (int|null)    : filter by chatbot ID
     *   - channel         (string|null) : filter by chatbot_channel
     *   - ticket_status   (string|null) : new | closed
     *   - limit           (int)         : max results, default 10, capped at 50
     *   - store_output_as (string)      : context key (default: chatbot_conversations_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'chatbot_conversations_list';
        $chatbotId     = isset($config['chatbot_id']) ? (int) $config['chatbot_id'] : null;
        $channel       = $config['channel'] ?? null;
        $ticketStatus  = $config['ticket_status'] ?? null;
        $limit         = min((int) ($config['limit'] ?? 10), 50);

        // Resolve chatbot IDs owned by this user for ownership scoping.
        $chatbotQuery = Chatbot::query()->where('user_id', $workflow->user_id);

        if ($chatbotId !== null) {
            $chatbotQuery->where('id', $chatbotId);
        }

        $chatbotIds = $chatbotQuery->pluck('id');

        $query = ChatbotConversation::query()
            ->whereIn('chatbot_id', $chatbotIds)
            ->orderByDesc('last_activity_at');

        if ($channel !== null) {
            $query->where('chatbot_channel', $channel);
        }

        if ($ticketStatus !== null) {
            $query->where('ticket_status', $ticketStatus);
        }

        $conversations = $query->limit($limit)
            ->get(['id', 'chatbot_id', 'conversation_name', 'chatbot_channel', 'ticket_status', 'last_activity_at', 'connect_agent_at'])
            ->map(fn (ChatbotConversation $c): array => [
                'id'               => $c->id,
                'chatbot_id'       => $c->chatbot_id,
                'name'             => $c->conversation_name ?? 'Unnamed',
                'channel'          => $c->chatbot_channel,
                'ticket_status'    => $c->ticket_status,
                'has_human_agent'  => $c->connect_agent_at !== null,
                'last_activity_at' => $c->last_activity_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return array_merge($context, [$storeOutputAs => $conversations]);
    }
}
