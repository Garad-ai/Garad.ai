<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;

class ListChatbotsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - active_only     (bool)   : only return active chatbots (default false)
     *   - limit           (int)    : max results, default 10, capped at 50
     *   - store_output_as (string) : context key (default: chatbots_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'chatbots_list';
        $activeOnly    = (bool) ($config['active_only'] ?? false);
        $limit         = min((int) ($config['limit'] ?? 10), 50);

        $query = Chatbot::query()
            ->withCount('conversations')
            ->where('user_id', $workflow->user_id)
            ->orderByDesc('created_at');

        if ($activeOnly) {
            $query->where('active', true);
        }

        $chatbots = $query->limit($limit)->get(['id', 'title', 'active', 'interaction_type'])
            ->map(fn (Chatbot $chatbot): array => [
                'id'                 => $chatbot->id,
                'title'              => $chatbot->title,
                'active'             => (bool) $chatbot->active,
                'interaction_type'   => $chatbot->interaction_type?->value,
                'conversations_count' => $chatbot->conversations_count,
            ])
            ->values()
            ->all();

        return array_merge($context, [$storeOutputAs => $chatbots]);
    }
}
