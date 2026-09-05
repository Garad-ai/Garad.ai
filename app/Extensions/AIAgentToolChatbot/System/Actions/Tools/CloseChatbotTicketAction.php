<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use Exception;

class CloseChatbotTicketAction implements ActionInterface
{
    /**
     * Config keys:
     *   - conversation_id (int)    : conversation to close (required)
     *   - store_output_as (string) : context key (default: closed_ticket)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs  = $config['store_output_as'] ?? 'closed_ticket';
        $conversationId = isset($config['conversation_id']) ? (int) $config['conversation_id'] : null;

        if ($conversationId === null) {
            throw new Exception('close_chatbot_ticket requires conversation_id.');
        }

        $chatbotIds = Chatbot::query()
            ->where('user_id', $workflow->user_id)
            ->pluck('id');

        $conversation = ChatbotConversation::query()
            ->whereIn('chatbot_id', $chatbotIds)
            ->findOrFail($conversationId);

        if (in_array($conversation->ticket_status, ['closed', 'deleted'], true)) {
            throw new Exception(
                "Conversation #{$conversationId} cannot be closed (current status: {$conversation->ticket_status})."
            );
        }

        $conversation->update(['ticket_status' => 'closed']);

        $result = [
            'conversation_id' => $conversation->id,
            'ticket_status'   => $conversation->ticket_status,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
