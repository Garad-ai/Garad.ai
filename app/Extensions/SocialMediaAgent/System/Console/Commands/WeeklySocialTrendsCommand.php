<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AgentInsightsService;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AnalysisRecorder;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\OpenAiAnalysisClient;

class WeeklySocialTrendsCommand extends AbstractAgentAnalysisCommand
{
    protected $signature = 'social-media-agent:weekly-social-trends
                            {--agent= : Limit the analysis to a single agent ID}';

    protected $description = 'Summarize weekly social media trends and opportunities per agent.';

    public function __construct(
        OpenAiAnalysisClient $analysisClient,
        AnalysisRecorder $analysisRecorder,
        protected AgentInsightsService $insightsService
    ) {
        parent::__construct($analysisClient, $analysisRecorder);
    }

    protected function analysisType(): string
    {
        return 'weekly_social_trends';
    }

    protected function temperature(): float
    {
        return 0.55;
    }

    protected function buildMessages(SocialMediaAgent $agent): array
    {
        $profile = $this->insightsService->buildProfileSummary($agent);
        $snapshot = $this->insightsService->buildMetricsSnapshot($agent, 7);

        $context = [
            'analysis'         => 'weekly_trend_digest',
            'agent'            => [
                'id'       => $agent->id,
                'name'     => $agent->name,
                'language' => $this->responseLanguage($agent),
            ],
            'profile_summary'  => $profile,
            'recent_metrics'   => [
                'has_data'             => $snapshot['data_available'] ?? false,
                'recent_post_count'    => $snapshot['recent_post_count'] ?? 0,
                'trend_direction'      => $snapshot['trend_direction'] ?? null,
                'platform_breakdown'   => $snapshot['platform_breakdown'] ?? [],
            ],
        ];

        $content = <<<PROMPT
			You are a strategist writing a weekly social media bulletin. Task: produce a short digest in {$this->responseLanguage($agent)} that covers
			- Two macro trends relevant to the agent
			- One platform-specific opportunity or warning (e.g., algorithm, ads, feature)
			- Two content ideas with recommended publish time/format

			Respect the data and industry context—no fabrications. Keep the response to max four bullets plus a mini summary.

			Context:
			{$this->encodeContext($context)}
		PROMPT;

        return [
            [
                'role'    => 'system',
                'content' => 'You are a plugged-in social media strategist who tracks cross-platform trends and translates them into quick wins.',
            ],
            [
                'role'    => 'user',
                'content' => $content,
            ],
        ];
    }
}
