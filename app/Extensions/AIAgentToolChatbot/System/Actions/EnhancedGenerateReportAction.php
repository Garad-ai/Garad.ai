<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolChatbot\System\Actions;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use App\Extensions\Chatbot\System\Services\ChatbotAnalyticsService; // resolved via app() to avoid constructor-chain conflicts
use Carbon\Carbon;

/*
 * Dynamically choose the parent class so that this enhancement chains on top of
 * previously-installed sub-extensions rather than replacing them.
 *
 * Resolution order (all three installed):
 *   ChatbotEnhanced → MarketingEnhanced → SocialMediaEnhanced → GenerateReportAction
 *
 * Falls back through each step gracefully when a sub-extension is absent.
 */
if (! class_exists(__NAMESPACE__ . '\ChatbotReportBase')) {
    class_alias(
        class_exists(\App\Extensions\AIAgentToolMarketingBot\System\Actions\EnhancedGenerateReportAction::class)
            ? \App\Extensions\AIAgentToolMarketingBot\System\Actions\EnhancedGenerateReportAction::class
            : (class_exists(\App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction::class)
                ? \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\EnhancedGenerateReportAction::class
                : \App\Extensions\AIAgent\System\Actions\GenerateReportAction::class),
        __NAMESPACE__ . '\ChatbotReportBase',
    );
}

/**
 * Extends the parent report action to append detailed chatbot analytics
 * when the "chatbot" section is requested.
 *
 * Adds a `chatbot_analytics` key to the context containing:
 *   - total_chatbots, active_chatbots
 *   - avg_rating, avg_response_time, avg_conversation_duration (via ChatbotAnalyticsService)
 *   - new_conversations, open_tickets, closed_tickets, human_handoffs, reviews_submitted
 *   - top_channels breakdown
 */
class EnhancedGenerateReportAction extends ChatbotReportBase
{
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        // Run the full parent report (base + any prior sub-extension enhancements).
        $context = parent::execute($config, $context, $workflow, $run);

        $sections = $config['sections'] ?? ['messages', 'workflows'];
        $sections = is_array($sections)
            ? $sections
            : array_filter(array_map('trim', explode(',', (string) $sections)));

        if (! in_array('chatbot', $sections, true)) {
            return $context;
        }

        if (! class_exists(Chatbot::class)) {
            return $context;
        }

        [$start, $end] = $this->resolvePeriod($config['date_range'] ?? 'today');

        $context['chatbot_analytics'] = $this->buildChatbotAnalytics($workflow->user_id, $start, $end);

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChatbotAnalytics(int $userId, Carbon $start, Carbon $end): array
    {
        /** @var ChatbotAnalyticsService $analyticsService */
        $analyticsService = app(ChatbotAnalyticsService::class);

        $chatbots    = Chatbot::query()->where('user_id', $userId)->get(['id', 'active']);
        $chatbotIds  = $chatbots->pluck('id')->all();

        $totalChatbots  = $chatbots->count();
        $activeChatbots = $chatbots->where('active', true)->count();

        $statCards  = $chatbotIds !== []
            ? $analyticsService->getStatCards($chatbotIds)
            : ['avg_rating' => 0.0, 'avg_response_time' => '0s', 'avg_conversation_duration' => '0s'];

        $topChannels = $chatbotIds !== []
            ? $analyticsService->getTopChannels($chatbotIds)->values()->all()
            : [];

        $newConversations = $chatbotIds !== []
            ? ChatbotConversation::query()
                ->whereIn('chatbot_id', $chatbotIds)
                ->whereBetween('created_at', [$start, $end])
                ->count()
            : 0;

        $openTickets = $chatbotIds !== []
            ? ChatbotConversation::query()
                ->whereIn('chatbot_id', $chatbotIds)
                ->where('ticket_status', 'new')
                ->count()
            : 0;

        $closedTickets = $chatbotIds !== []
            ? ChatbotConversation::query()
                ->whereIn('chatbot_id', $chatbotIds)
                ->where('ticket_status', 'closed')
                ->count()
            : 0;

        $humanHandoffs = $chatbotIds !== []
            ? ChatbotConversation::query()
                ->whereIn('chatbot_id', $chatbotIds)
                ->whereNotNull('connect_agent_at')
                ->whereBetween('connect_agent_at', [$start, $end])
                ->count()
            : 0;

        $reviewsSubmitted = $chatbotIds !== []
            ? ChatbotConversation::query()
                ->whereIn('chatbot_id', $chatbotIds)
                ->whereNotNull('review_submitted_at')
                ->whereBetween('review_submitted_at', [$start, $end])
                ->count()
            : 0;

        return [
            'total_chatbots'           => $totalChatbots,
            'active_chatbots'          => $activeChatbots,
            'avg_rating'               => $statCards['avg_rating'],
            'avg_response_time'        => $statCards['avg_response_time'],
            'avg_conversation_duration' => $statCards['avg_conversation_duration'],
            'new_conversations'        => $newConversations,
            'open_tickets'             => $openTickets,
            'closed_tickets'           => $closedTickets,
            'human_handoffs'           => $humanHandoffs,
            'reviews_submitted'        => $reviewsSubmitted,
            'top_channels'             => $topChannels,
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
