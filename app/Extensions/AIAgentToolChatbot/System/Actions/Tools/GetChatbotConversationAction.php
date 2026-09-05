<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use Exception;

class GetChatbotConversationAction implements ActionInterface
{
    /**
     * Config keys:
     *   - conversation_id (int)    : conversation to fetch (required)
     *   - output_format   (string) : 'json' (default) | 'plain'
     *   - store_output_as (string) : context key (default: chatbot_conversation)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'chatbot_conversation';
        $outputFormat = $config['output_format'] ?? 'json';
        $conversationId = isset($config['conversation_id']) ? (int) $config['conversation_id'] : null;

        if ($conversationId === null) {
            throw new Exception('get_chatbot_conversation requires conversation_id.');
        }

        $chatbotIds = Chatbot::query()
            ->where('user_id', $workflow->user_id)
            ->pluck('id');

        $conversation = ChatbotConversation::query()
            ->whereIn('chatbot_id', $chatbotIds)
            ->with(['histories' => fn ($q) => $q
                ->where('is_internal_note', false)
                ->orderBy('created_at')
                ->select(['id', 'conversation_id', 'role', 'message', 'created_at']),
            ])
            ->findOrFail($conversationId);

        if ($outputFormat === 'plain') {
            $lines = [];

            foreach ($conversation->histories as $h) {
                $label = $h->role === 'user' ? 'User' : 'Admin';
                $lines[] = "{$label}: {$h->message}";
            }

            $result = implode("\n", $lines);
        } else {
            $result = [
                'id'               => $conversation->id,
                'chatbot_id'       => $conversation->chatbot_id,
                'name'             => $conversation->conversation_name ?? 'Unnamed',
                'channel'          => $conversation->chatbot_channel,
                'ticket_status'    => $conversation->ticket_status,
                'last_activity_at' => $conversation->last_activity_at?->toIso8601String(),
                'messages'         => $conversation->histories
                    ->map(fn ($h): array => [
                        'role'       => $h->role,
                        'message'    => $h->message,
                        'created_at' => $h->created_at?->toIso8601String(),
                    ])
                    ->all(),
            ];
        }

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
