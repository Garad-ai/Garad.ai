<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AgentInsightsService;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AnalysisRecorder;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\OpenAiAnalysisClient;

class PostMetricsAnalyzerCommand extends AbstractAgentAnalysisCommand
{
    protected $signature = 'social-media-agent:post-metrics-analyzer
                            {--agent= : Limit the analysis to a single agent ID}';

    protected $description = 'Analyze recent post metrics for every active agent and store an OpenAI summary.';

    public function __construct(
        OpenAiAnalysisClient $analysisClient,
        AnalysisRecorder $analysisRecorder,
        protected AgentInsightsService $insightsService
    ) {
        parent::__construct($analysisClient, $analysisRecorder);
    }

    protected function analysisType(): string
    {
        return 'post_metrics_analyzer';
    }

    protected function buildMessages(SocialMediaAgent $agent): array
    {
        $snapshot = $this->insightsService->buildMetricsSnapshot($agent);

        $context = [
            'analysis'         => 'post_metrics_overview',
            'agent'            => [
                'id'       => $agent->id,
                'name'     => $agent->name,
                'language' => $this->responseLanguage($agent),
            ],
            'generated_at'     => now()->toAtomString(),
            'metrics_snapshot' => $snapshot,
        ];

        $content = <<<PROMPT
			You are a social media performance analyst. Based on the following metrics snapshot:
			- Summarize overall performance (max two sentences)
			- Highlight the best and weakest posts with the reasons
			- Recommend two quick actions for the upcoming days

			Respond in {$this->responseLanguage($agent)} using bullet points.

			Dataset:
			{$this->encodeContext($context)}
		PROMPT;

        return [
            [
                'role'    => 'system',
                'content' => 'You are an experienced social media analyst who turns raw metrics into concise, actionable summaries.',
            ],
            [
                'role'    => 'user',
                'content' => $content,
            ],
        ];
    }
}
