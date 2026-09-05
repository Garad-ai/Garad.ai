<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;

class ListMarketingConversationsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - channel         (string|null) : telegram | whatsapp
     *   - limit           (int)         : max results, default 10, capped at 50
     *   - store_output_as (string)      : context key (default: marketing_conversations_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'marketing_conversations_list';
        $channel       = $config['channel'] ?? null;
        $limit         = min((int) ($config['limit'] ?? 10), 50);

        $query = MarketingConversation::query()
            ->withCount('histories')
            ->where('user_id', $workflow->user_id)
            ->orderByDesc('last_activity_at');

        if ($channel !== null) {
            $query->where('type', $channel);
        }

        $conversations = $query->limit($limit)->get()
            ->map(fn (MarketingConversation $c): array => [
                'id'               => $c->id,
                'name'             => $c->conversation_name ?? 'Unnamed',
                'channel'          => $c->type,
                'message_count'    => $c->histories_count,
                'last_activity_at' => $c->last_activity_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return array_merge($context, [$storeOutputAs => $conversations]);
    }
}
