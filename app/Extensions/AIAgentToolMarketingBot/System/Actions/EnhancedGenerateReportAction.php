<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolMarketingBot\System\Actions;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\MarketingMessageHistory;
use App\Extensions\MarketingBot\System\Models\Whatsapp\Contact;
use Carbon\Carbon;

/*
 * Dynamically choose the parent class so that this enhancement chains on top
 * of AIAgentToolSocialMediaAgent's EnhancedGenerateReportAction when that
 * sub-extension is also installed, rather than replacing it.
 *
 * Resolution order (both installed):
 *   MarketingEnhanced → SocialMediaEnhanced → GenerateReportAction
 *
 * Resolution order (only this sub-extension installed):
 *   MarketingEnhanced → GenerateReportAction
 */
if (! class_exists(__NAMESPACE__ . '\MarketingReportBase')) {
    class_alias(
        class_exists(\App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction::class)
            ? \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction::class
            : \App\Extensions\AIAgent\System\Actions\GenerateReportAction::class,
        __NAMESPACE__ . '\MarketingReportBase',
    );
}

/**
 * Extends the parent report action to append detailed marketing analytics
 * when the "marketing" or "marketing_contacts" sections are requested.
 *
 * Adds a `marketing_analytics` key to the context containing:
 *   - campaigns: breakdown by status and type, upcoming and recent campaigns
 *   - contacts:  new contacts, conversation counts, message totals
 */
class EnhancedGenerateReportAction extends MarketingReportBase
{
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        // Run the full parent report (base + any prior sub-extension enhancements).
        $context = parent::execute($config, $context, $workflow, $run);

        $sections = $config['sections'] ?? ['messages', 'workflows'];
        $sections = is_array($sections)
            ? $sections
            : array_filter(array_map('trim', explode(',', (string) $sections)));

        $hasMarketing = in_array('marketing', $sections, true);
        $hasContacts  = in_array('marketing_contacts', $sections, true);

        if (! $hasMarketing && ! $hasContacts) {
            return $context;
        }

        if (! class_exists(MarketingCampaign::class)) {
            return $context;
        }

        [$start, $end] = $this->resolvePeriod($config['date_range'] ?? 'today');

        $analytics = [];

        if ($hasMarketing) {
            $analytics['campaigns'] = $this->buildCampaignAnalytics($workflow->user_id, $start, $end);
        }

        if ($hasContacts) {
            $analytics['contacts'] = $this->buildContactAnalytics($workflow->user_id, $start, $end);
        }

        $context['marketing_analytics'] = $analytics;

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCampaignAnalytics(int $userId, Carbon $start, Carbon $end): array
    {
        $campaigns = MarketingCampaign::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'name', 'type', 'status', 'scheduled_at', 'finished_at']);

        $byStatus = $campaigns->groupBy(fn ($c) => $c->status?->value ?? 'unknown')
            ->map->count()
            ->all();

        $byType = $campaigns->groupBy(fn ($c) => $c->type?->value ?? 'unknown')
            ->map->count()
            ->all();

        $upcoming = $campaigns
            ->filter(fn ($c) => $c->status?->value === 'scheduled' && $c->scheduled_at?->isFuture())
            ->sortBy('scheduled_at')
            ->take(5)
            ->map(fn ($c) => [
                'name'         => $c->name,
                'type'         => $c->type?->value,
                'scheduled_at' => $c->scheduled_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentlyPublished = $campaigns
            ->filter(fn ($c) => $c->status?->value === 'published')
            ->sortByDesc('finished_at')
            ->take(5)
            ->map(fn ($c) => [
                'name'        => $c->name,
                'type'        => $c->type?->value,
                'finished_at' => $c->finished_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'total'              => $campaigns->count(),
            'by_status'          => $byStatus,
            'by_type'            => $byType,
            'upcoming_scheduled' => $upcoming,
            'recently_published' => $recentlyPublished,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactAnalytics(int $userId, Carbon $start, Carbon $end): array
    {
        $newContacts = class_exists(Contact::class)
            ? Contact::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->count()
            : 0;

        $conversations = MarketingConversation::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'type']);

        $byChannel = $conversations->groupBy('type')->map->count()->all();

        $conversationIds = $conversations->pluck('id');

        $totalMessages = class_exists(MarketingMessageHistory::class) && $conversationIds->isNotEmpty()
            ? MarketingMessageHistory::query()
                ->whereIn('conversation_id', $conversationIds)
                ->count()
            : 0;

        return [
            'new_contacts'      => $newContacts,
            'new_conversations' => $conversations->count(),
            'by_channel'        => $byChannel,
            'total_messages'    => $totalMessages,
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolvePeriod(string $dateRange): array
    {
        $end = now()->endOfDay();

        $start = match ($dateRange) {
            'yesterday' => now()->subDay()->startOfDay(),
            'this_week' => now()->startOfWeek(),
            default     => now()->startOfDay(),
        };

        return [$start, $end];
    }
}
