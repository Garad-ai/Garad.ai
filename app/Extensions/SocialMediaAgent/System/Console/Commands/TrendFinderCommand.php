<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AgentInsightsService;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\AnalysisRecorder;
use App\Extensions\SocialMediaAgent\System\Services\Analysis\OpenAiAnalysisClient;

class TrendFinderCommand extends AbstractAgentAnalysisCommand
{
    protected $signature = 'social-media-agent:trend-finder
                            {--agent= : Limit the analysis to a single agent ID}';

    protected $description = 'Build a trend inspiration brief from the agent profile.';

    public function __construct(
        OpenAiAnalysisClient $analysisClient,
        AnalysisRecorder $analysisRecorder,
        protected AgentInsightsService $insightsService
    ) {
        parent::__construct($analysisClient, $analysisRecorder);
    }

    protected function analysisType(): string
    {
        return 'trend_finder';
    }

    protected function temperature(): float
    {
        return 0.65;
    }

    protected function buildMessages(SocialMediaAgent $agent): array
    {
        $profile = $this->insightsService->buildProfileSummary($agent);

        $context = [
            'analysis'        => 'trend_research',
            'agent'           => [
                'id'       => $agent->id,
                'name'     => $agent->name,
                'language' => $this->responseLanguage($agent),
            ],
            'profile_summary' => $profile,
        ];

        $content = <<<PROMPT
			Your job: review the agent profile data and produce 3-4 trend suggestions in {$this->responseLanguage($agent)}.

			Each trend suggestion must include:
			- Trend/topic title
			- Why it is relevant (audience & category fit)
			- Recommended content angle or CTA
			- 2-3 usable hashtags

			Write concise, bullet-ready ideas that can be executed immediately.

			Profile data:
			{$this->encodeContext($context)}
		PROMPT;

        return [
            [
                'role'    => 'system',
                'content' => 'You are an AI trend strategist. You study audience, tone, and goals to propose timely content angles.',
            ],
            [
                'role'    => 'user',
                'content' => $content,
            ],
        ];
    }
}
