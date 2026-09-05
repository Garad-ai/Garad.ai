<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Services\AIAgentChatService;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AgentInsightsService;

/**
 * Extends the base GenerateReportAction to append detailed social media
 * analytics when the "social_media_agent" section is requested.
 *
 * The parent produces a concise AI-written text summary; this class adds a
 * structured `social_media_analytics` key to the context for downstream steps
 * (e.g. a follow-up ai_call that surfaces per-agent engagement trends).
 */
class EnhancedGenerateReportAction extends GenerateReportAction
{
    public function __construct(AIAgentChatService $chatService)
    {
        parent::__construct($chatService);
    }

    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        // Run the full standard report (includes the minimal social_media_agent summary).
        $context = parent::execute($config, $context, $workflow, $run);

        $sections = $config['sections'] ?? ['messages', 'workflows'];
        $sections = is_array($sections)
            ? $sections
            : array_filter(array_map('trim', explode(',', (string) $sections)));

        if (! in_array('social_media_agent', $sections, true)) {
            return $context;
        }

        if (! class_exists(AgentInsightsService::class)) {
            return $context;
        }

        $context['social_media_analytics'] = $this->buildDetailedAnalytics($workflow->user_id);

        return $context;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDetailedAnalytics(int $userId): array
    {
        $agents = SocialMediaAgent::query()
            ->where('user_id', $userId)
            ->get();

        /** @var AgentInsightsService $insightsService */
        $insightsService = app(AgentInsightsService::class);

        return $agents->map(function (SocialMediaAgent $agent) use ($insightsService): array {
            $metrics = $insightsService->buildMetricsSnapshot($agent);

            return [
                'agent_id'               => $agent->id,
                'agent_name'             => $agent->name,
                'is_active'              => $agent->is_active,
                'avg_engagement_rate'    => $metrics['avg_engagement_rate'],
                'avg_engagement_count'   => $metrics['avg_engagement_count'],
                'trend_direction'        => $metrics['trend_direction'],
                'trend_delta'            => $metrics['trend_delta'],
                'published_last_7_days'  => $metrics['published_last_7_days'],
                'recent_post_count'      => $metrics['recent_post_count'],
                'platform_breakdown'     => $metrics['platform_breakdown'],
                'top_post'               => $metrics['top_post'],
                'lowest_post'            => $metrics['lowest_post'],
                'recent_posts'           => $metrics['recent_posts'],
            ];
        })->values()->all();
    }
}
