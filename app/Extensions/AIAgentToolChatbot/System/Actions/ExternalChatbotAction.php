<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolChatbot\System\Actions\Tools\CloseChatbotTicketAction;
use App\Extensions\AIAgentToolChatbot\System\Actions\Tools\GetChatbotAnalyticsAction;
use App\Extensions\AIAgentToolChatbot\System\Actions\Tools\GetChatbotConversationAction;
use App\Extensions\AIAgentToolChatbot\System\Actions\Tools\GetChatbotConversationsAction;
use App\Extensions\AIAgentToolChatbot\System\Actions\Tools\ListChatbotConversationsAction;
use App\Extensions\AIAgentToolChatbot\System\Actions\Tools\ListChatbotsAction;
use Exception;

class ExternalChatbotAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly ListChatbotsAction $listChatbotsAction,
        private readonly GetChatbotAnalyticsAction $getAnalyticsAction,
        private readonly ListChatbotConversationsAction $listConversationsAction,
        private readonly GetChatbotConversationAction $getConversationAction,
        private readonly CloseChatbotTicketAction $closeTicketAction,
        private readonly GetChatbotConversationsAction $getConversationsAction,
    ) {}

    /**
     * Config keys:
     *   - action_event    (string) : sub-operation to perform (required)
     *   - store_output_as (string) : context key to store results under
     *
     * Per action_event additional keys:
     *   list_chatbots:              active_only (bool), limit (int)
     *   get_chatbot_analytics:      chatbot_id (int, optional)
     *   list_chatbot_conversations:  chatbot_id (int), channel (string), ticket_status (string), limit (int)
     *   get_chatbot_conversation:    conversation_id (int, required)
     *   get_chatbot_conversations:   limit (int), message_sender (user|admin|all)
     *   close_chatbot_ticket:        conversation_id (int, required)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $actionEvent = $config['action_event'] ?? '';

        return match ($actionEvent) {
            'list_chatbots'              => $this->listChatbotsAction->execute($config, $context, $workflow, $run),
            'get_chatbot_analytics'      => $this->getAnalyticsAction->execute($config, $context, $workflow, $run),
            'list_chatbot_conversations' => $this->listConversationsAction->execute($config, $context, $workflow, $run),
            'get_chatbot_conversation'   => $this->getConversationAction->execute($config, $context, $workflow, $run),
            'get_chatbot_conversations'  => $this->getConversationsAction->execute($config, $context, $workflow, $run),
            'close_chatbot_ticket'       => $this->closeTicketAction->execute($config, $context, $workflow, $run),
            default                      => throw new Exception('ExternalChatbotAction: unknown action_event "' . $actionEvent . '".'),
        };
    }

    public function getCategory(): string
    {
        return 'agents';
    }

    public function getLabel(): string
    {
        return 'External Chatbot';
    }

    public function getDescription(): string
    {
        return 'Interact with chatbots — list, get analytics, manage conversations, and close tickets.';
    }

    public function getIcon(): string
    {
        return 'tabler-message-chatbot';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['action_event'],
            'properties' => [
                'action_event' => [
                    'type' => 'string',
                    'enum' => [
                        'list_chatbots',
                        'get_chatbot_analytics',
                        'list_chatbot_conversations',
                        'get_chatbot_conversation',
                        'get_chatbot_conversations',
                        'close_chatbot_ticket',
                    ],
                    'description' => 'The chatbot operation to perform.',
                ],
                'active_only'     => ['type' => 'boolean', 'description' => 'Only return active chatbots (list_chatbots).'],
                'limit'           => ['type' => 'integer', 'description' => 'Max results (list_chatbots, list_chatbot_conversations).'],
                'chatbot_id'      => ['type' => 'integer', 'description' => 'Scope to a single chatbot ID.'],
                'channel'         => ['type' => 'string', 'description' => 'Filter by channel (list_chatbot_conversations).'],
                'ticket_status'   => ['type' => 'string', 'enum' => ['new', 'closed'], 'description' => 'Filter by ticket status.'],
                'conversation_id' => ['type' => 'integer', 'description' => 'Conversation ID (get_chatbot_conversation, close_chatbot_ticket).'],
                'message_sender'  => ['type' => 'string', 'enum' => ['user', 'admin', 'all'], 'description' => 'Filter messages by sender (get_chatbot_conversations).'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ];
    }
}
