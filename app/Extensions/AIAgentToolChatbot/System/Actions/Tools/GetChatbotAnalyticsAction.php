<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Services\ChatbotAnalyticsService;
use Exception;

class GetChatbotAnalyticsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - chatbot_id      (int|null) : scope to a single chatbot; omit for all
     *   - store_output_as (string)   : context key (default: chatbot_analytics)
     */
    public function __construct(private readonly ChatbotAnalyticsService $analyticsService) {}

    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'chatbot_analytics';
        $chatbotId     = isset($config['chatbot_id']) ? (int) $config['chatbot_id'] : null;

        $query = Chatbot::query()->where('user_id', $workflow->user_id);

        if ($chatbotId !== null) {
            $query->where('id', $chatbotId);

            if (! $query->exists()) {
                throw new Exception("Chatbot #{$chatbotId} not found or does not belong to this user.");
            }
        }

        $chatbotIds = $query->pluck('id')->all();

        if ($chatbotIds === []) {
            $result = [
                'total_conversations'       => 0,
                'avg_rating'                => 0.0,
                'avg_response_time'         => '0s',
                'avg_conversation_duration' => '0s',
                'top_channels'              => [],
            ];

            return array_merge($context, [$storeOutputAs => $result]);
        }

        $statCards   = $this->analyticsService->getStatCards($chatbotIds);
        $topChannels = $this->analyticsService->getTopChannels($chatbotIds)->values()->all();

        $result = array_merge($statCards, ['top_channels' => $topChannels]);

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
