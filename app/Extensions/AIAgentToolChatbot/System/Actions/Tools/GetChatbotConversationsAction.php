<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;

class GetChatbotConversationsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - limit           (int)    : number of recent conversations to fetch (default 5, max 50)
     *   - message_sender  (string) : filter messages by sender — 'user' | 'admin' | 'all' (default: all)
     *   - output_format   (string) : 'json' (default) | 'plain'
     *   - store_output_as (string) : context key (default: chatbot_conversations)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'chatbot_conversations';
        $limit = min((int) ($config['limit'] ?? 5), 50);
        $messageSender = $config['message_sender'] ?? 'all';
        $outputFormat = $config['output_format'] ?? 'json';

        // Map UI sender values to history role column values
        $roleFilter = match ($messageSender) {
            'user'  => 'user',
            'admin' => 'assistant',
            default => null,
        };

        $chatbotIds = Chatbot::query()
            ->where('user_id', $workflow->user_id)
            ->pluck('id');

        $conversations = ChatbotConversation::query()
            ->whereIn('chatbot_id', $chatbotIds)
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->with(['histories' => function ($q) use ($roleFilter): void {
                $q->where('is_internal_note', false)
                    ->orderBy('created_at')
                    ->select(['id', 'conversation_id', 'role', 'message', 'created_at']);

                if ($roleFilter !== null) {
                    $q->where('role', $roleFilter);
                }
            }])
            ->get();

        if ($outputFormat === 'plain') {
            $blocks = [];

            foreach ($conversations as $c) {
                $lines   = [];
                $lines[] = "Conversation ID: {$c->id}";
                $lines[] = "User: " . ($c->conversation_name ?? 'Unnamed');
                $lines[] = '';

                foreach ($c->histories as $h) {
                    $lines[] = $h->message;
                }

                $blocks[] = implode("\n", $lines);
            }

            $result = implode("\n----\n", $blocks);
        } else {
            $result = $conversations
                ->map(fn (ChatbotConversation $c): array => [
                    'id'               => $c->id,
                    'chatbot_id'       => $c->chatbot_id,
                    'name'             => $c->conversation_name ?? 'Unnamed',
                    'channel'          => $c->chatbot_channel,
                    'ticket_status'    => $c->ticket_status,
                    'last_activity_at' => $c->last_activity_at?->toIso8601String(),
                    'messages'         => $c->histories
                        ->map(fn ($h): array => [
                            'role'       => $h->role,
                            'message'    => $h->message,
                            'created_at' => $h->created_at?->toIso8601String(),
                        ])
                        ->all(),
                ])
                ->values()
                ->all();
        }

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
