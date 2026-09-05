<?php

namespace App\Extensions\SocialMediaAgent\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPostDailyMetric;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Helpers\Classes\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAverageMetricsCommand extends Command
{
    protected $signature = 'social-media-agent:update-average-metrics {agent_id? : Limit the update to a single agent ID}';

    protected $description = 'Calculate and persist average impressions and engagements for every agent.';

    public function handle(): int
    {
        if (Helper::appIsDemo()) {
            return 1;
        }

        $agentId = $this->argument('agent_id');
        $updated = 0;
        $attempted = 0;

        Log::info('social-media-agent:update-average-metrics started', [
            'agent_option' => $agentId,
        ]);

        SocialMediaAgent::query()
            ->when($agentId, fn ($query) => $query->whereKey($agentId))
            ->orderBy('id')
            ->chunkById(50, function ($agents) use (&$updated, &$attempted) {
                foreach ($agents as $agent) {
                    $attempted++;
                    $averages = $this->calculateAverages($agent);

                    if ($averages === null) {
                        Log::info('social-media-agent:update-average-metrics skipped agent', [
                            'agent_id' => $agent->id,
                            'reason'   => 'no_metrics',
                        ]);

                        continue;
                    }

                    $agent->forceFill([
                        'average_impressions' => $averages['impressions'],
                        'average_engagement'  => $averages['engagement'],
                    ])->save();
                    $updated++;
                    Log::info('social-media-agent:update-average-metrics updated agent', [
                        'agent_id'           => $agent->id,
                        'impression_average' => $averages['impressions'],
                        'engagement_average' => $averages['engagement'],
                    ]);
                }
            });

        $this->info("Updated {$updated} agent(s).");
        Log::info('social-media-agent:update-average-metrics finished', [
            'agent_option'     => $agentId,
            'agents_attempted' => $attempted,
            'agents_updated'   => $updated,
        ]);

        return self::SUCCESS;
    }

    private function calculateAverages(SocialMediaAgent $agent): ?array
    {
        $metrics = SocialMediaPostDailyMetric::query()
            ->select(['id', 'agent_id', 'social_media_post_id', 'view_count', 'like_count', 'comment_count', 'share_count', 'last_totals'])
            ->where(function ($query) use ($agent) {
                $query->where('agent_id', $agent->id)
                    ->orWhere(function ($query) use ($agent) {
                        $query->whereNull('agent_id')
                            ->whereHas('post', function ($postQuery) use ($agent) {
                                $postQuery->where('agent_id', $agent->id)
                                    ->where('status', StatusEnum::published);
                            });
                    });
            })
            ->get();

        if ($metrics->isEmpty()) {
            return null;
        }

        $impressions = [];
        $engagements = [];

        foreach ($metrics as $metric) {
            $impression = $this->extractImpressions($metric);
            if ($impression !== null) {
                $impressions[] = $impression;
            }

            $engagement = $this->extractEngagement($metric);
            if ($engagement !== null) {
                $engagements[] = $engagement;
            }
        }

        if (empty($impressions) && empty($engagements)) {
            return null;
        }

        return [
            'impressions' => $this->average($impressions),
            'engagement'  => $this->average($engagements),
        ];
    }

    private function extractImpressions(SocialMediaPostDailyMetric $metric): ?float
    {
        $view = $metric->view_count;

        if (! is_numeric($view)) {
            $view = data_get($metric->last_totals ?? [], 'view_count')
                ?? data_get($metric->last_totals ?? [], 'play_count')
                ?? data_get($metric->last_totals ?? [], 'impression_count');
        }

        return is_numeric($view) ? (float) $view : null;
    }

    private function extractEngagement(SocialMediaPostDailyMetric $metric): ?float
    {
        $totals = $metric->last_totals ?? [];

        $parts = [
            $metric->like_count ?? data_get($totals, 'like_count'),
            $metric->comment_count ?? data_get($totals, 'comment_count'),
            $metric->share_count ?? data_get($totals, 'share_count', data_get($totals, 'forward_count')),
        ];

        $values = array_filter($parts, static fn ($value) => is_numeric($value));

        if (empty($values)) {
            return null;
        }

        return array_sum(array_map(static fn ($value) => (float) $value, $values));
    }

    private function average(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }
}
