<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AgentInsightsService;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AnalysisRecorder;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\OpenAiAnalysisClient;

class PostPerformanceAdvisorCommand extends AbstractAgentAnalysisCommand
{
    protected $signature = 'social-media-agent:post-performance-advisor
                            {--agent= : Limit the analysis to a single agent ID}';

    protected $description = 'Diagnose overall performance trends and provide optimization advice.';

    public function __construct(
        OpenAiAnalysisClient $analysisClient,
        AnalysisRecorder $analysisRecorder,
        protected AgentInsightsService $insightsService
    ) {
        parent::__construct($analysisClient, $analysisRecorder);
    }

    protected function analysisType(): string
    {
        return 'post_performance_advisor';
    }

    protected function buildMessages(SocialMediaAgent $agent): array
    {
        $snapshot = $this->insightsService->buildMetricsSnapshot($agent, 21);
        $profile = $this->insightsService->buildProfileSummary($agent);

        $context = [
            'analysis'         => 'performance_diagnostics',
            'agent'            => [
                'id'       => $agent->id,
                'name'     => $agent->name,
                'language' => $this->responseLanguage($agent),
            ],
            'metrics_snapshot' => $snapshot,
            'profile_summary'  => $profile,
        ];

        $content = <<<PROMPT
			Evaluate the performance curve using the provided metrics and report in {$this->responseLanguage($agent)}:
			1. Explain the trend direction and why (rising/falling/flat).
			2. Share the two most impactful optimization ideas (content hook, CTA, posting time, etc.).
			3. Point out any metrics that signal risk or opportunity.

			Return a short title followed by bullet points.

			Dataset:
			{$this->encodeContext($context)}
		PROMPT;

        return [
            [
                'role'    => 'system',
                'content' => 'You are a no-fluff performance coach for social media teams. You provide data-backed diagnoses and actionable fixes.',
            ],
            [
                'role'    => 'user',
                'content' => $content,
            ],
        ];
    }
}
