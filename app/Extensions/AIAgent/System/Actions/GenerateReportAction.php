<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentMessage;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Services\AIAgentChatService;
use App\Helpers\Classes\MarketplaceHelper;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class GenerateReportAction implements AIAgentActionInterface
{
    public function __construct(private readonly AIAgentChatService $chatService) {}

    /**
     * Config keys:
     *   - date_range (string)     : today | yesterday | this_week (default: today)
     *   - sections (array)        : subset of [messages, workflows, chatbot, marketing,
     *                               blogpilot, social_media, social_media_agent, ai_usage,
     *                               creative_suite, ai_images, marketing_contacts]
     *   - store_output_as (string): context key for the summarised report (default: report_text)
     */
    public function getCategory(): string
    {
        return 'utilities';
    }

    public function getLabel(): string
    {
        return 'Generate Report';
    }

    public function getDescription(): string
    {
        return 'Generate an AI-summarised activity report for a given date range.';
    }

    public function getIcon(): string
    {
        return 'tabler-report-analytics';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'date_range'      => ['type' => 'string', 'enum' => ['today', 'yesterday', 'this_week', 'this_month']],
                'sections'        => ['type' => 'array', 'items' => ['type' => 'string']],
                'system_prompt'   => ['type' => 'string'],
                'store_output_as' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $dateRange = $config['date_range'] ?? 'today';
        $rawSections = $config['sections'] ?? ['messages', 'workflows'];
        $sections = is_array($rawSections)
            ? $rawSections
            : array_filter(array_map('trim', explode(',', (string) $rawSections)));
        $storeOutputAs = $config['store_output_as'] ?? 'report_text';
        $systemPrompt = ! empty($config['system_prompt']) ? trim($config['system_prompt']) : null;

        [$start, $end] = $this->resolveDateRange($dateRange);

        $this->chatService->forUser($workflow->user_id ? User::find($workflow->user_id) : null);

        $data = [];

        $sectionMap = [
            'messages'           => fn () => $this->buildMessagesSection($workflow->user_id, $start, $end),
            'workflows'          => fn () => $this->buildWorkflowsSection($workflow->user_id, $start, $end),
            'chatbot'            => fn () => MarketplaceHelper::isRegistered('chatbot') ? $this->buildChatbotSection($workflow->user_id, $start, $end) : null,
            'marketing'          => fn () => MarketplaceHelper::isRegistered('marketing-bot') ? $this->buildMarketingSection($workflow->user_id, $start, $end) : null,
            'blogpilot'          => fn () => MarketplaceHelper::isRegistered('blogpilot') ? $this->buildBlogPilotSection($workflow->user_id, $start, $end) : null,
            'social_media'       => fn () => MarketplaceHelper::isRegistered('ai-social-media') ? $this->buildSocialMediaSection($workflow->user_id, $start, $end) : null,
            'social_media_agent' => fn () => MarketplaceHelper::isRegistered('social-media-agent') ? $this->buildSocialMediaAgentSection($workflow->user_id, $start, $end) : null,
            'crm'                => fn () => MarketplaceHelper::isRegistered('crm') ? $this->buildCrmSection($workflow->user_id, $start, $end) : null,
            'ai_usage'           => fn () => $this->buildAiUsageSection($workflow->user_id, $start, $end),
            'creative_suite'     => fn () => MarketplaceHelper::isRegistered('creative-suite') ? $this->buildCreativeSuiteSection($workflow->user_id, $start, $end) : null,
            'ai_images'          => fn () => MarketplaceHelper::isRegistered('ai-image-pro') ? $this->buildAiImagesSection($workflow->user_id, $start, $end) : null,
            'marketing_contacts' => fn () => MarketplaceHelper::isRegistered('marketing-bot') ? $this->buildMarketingContactsSection($workflow->user_id, $start, $end) : null,
        ];

        foreach ($sections as $section) {
            if (! isset($sectionMap[$section])) {
                continue;
            }

            $result = ($sectionMap[$section])();

            if ($result !== null) {
                $data[$section] = $result;
            }
        }

        $report = $this->summarise($data, $dateRange, $systemPrompt);

        return array_merge($context, [$storeOutputAs => $report]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function summarise(array $data, string $dateRange, ?string $systemPrompt = null): string
    {
        $systemPrompt ??= <<<'PROMPT'
You are a concise business analyst. You will receive platform activity data as JSON.
Write a short, friendly summary for the owner:
- Start with one sentence summarising the period overall.
- Highlight only metrics with non-zero values.
- Skip any metric that is 0 or empty.
- Use plain text. No markdown headings. Bullet points are fine for key metrics.
- Keep it under 300 words.
PROMPT;

        $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

        try {
            return $this->chatService->chat($systemPrompt, [
                ['role' => 'user', 'content' => "Date range: {$dateRange}\n\n{$json}"],
            ]);
        } catch (Exception) {
            // Fall back to raw JSON if the AI call fails
            return $json;
        }
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolveDateRange(string $dateRange): array
    {
        $end = now()->endOfDay();

        $start = match ($dateRange) {
            'yesterday'  => now()->subDay()->startOfDay(),
            'this_week'  => now()->startOfWeek(),
            'this_month' => now()->startOfMonth(),
            default      => now()->startOfDay(),
        };

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMessagesSection(int $userId, Carbon $start, Carbon $end): array
    {
        $channels = AIAgentChannel::query()
            ->where('user_id', $userId)
            ->get(['id', 'name', 'type']);

        $channelIds = $channels->pluck('id');

        $inboundTotal = AIAgentMessage::query()
            ->whereIn('channel_id', $channelIds)
            ->where('direction', 'inbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $outboundTotal = AIAgentMessage::query()
            ->whereIn('channel_id', $channelIds)
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $uniqueSenders = AIAgentMessage::query()
            ->whereIn('channel_id', $channelIds)
            ->where('direction', 'inbound')
            ->whereNotNull('sender_id')
            ->whereBetween('created_at', [$start, $end])
            ->distinct('sender_id')
            ->count('sender_id');

        $inboundByChannel = AIAgentMessage::query()
            ->whereIn('channel_id', $channelIds)
            ->where('direction', 'inbound')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('channel_id, count(*) as count')
            ->groupBy('channel_id')
            ->pluck('count', 'channel_id');

        $outboundByChannel = AIAgentMessage::query()
            ->whereIn('channel_id', $channelIds)
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('channel_id, count(*) as count')
            ->groupBy('channel_id')
            ->pluck('count', 'channel_id');

        $byChannel = $channels
            ->filter(fn ($c) => ($inboundByChannel[$c->id] ?? 0) > 0 || ($outboundByChannel[$c->id] ?? 0) > 0)
            ->map(fn ($c) => [
                'name'     => $c->name,
                'type'     => $c->type->value ?? 'channel',
                'received' => $inboundByChannel[$c->id] ?? 0,
                'sent'     => $outboundByChannel[$c->id] ?? 0,
            ])
            ->values()
            ->all();

        return [
            'total_received' => $inboundTotal,
            'total_sent'     => $outboundTotal,
            'unique_senders' => $uniqueSenders,
            'by_channel'     => $byChannel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorkflowsSection(int $userId, Carbon $start, Carbon $end): array
    {
        $workflows = AIAgentWorkflow::query()
            ->where('user_id', $userId)
            ->get(['id', 'name']);

        $workflowIds = $workflows->pluck('id');

        $completedByWorkflow = AIAgentWorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('workflow_id, count(*) as count')
            ->groupBy('workflow_id')
            ->pluck('count', 'workflow_id');

        $failedByWorkflow = AIAgentWorkflowRun::query()
            ->whereIn('workflow_id', $workflowIds)
            ->where('status', 'failed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('workflow_id, count(*) as count')
            ->groupBy('workflow_id')
            ->pluck('count', 'workflow_id');

        $byWorkflow = $workflows
            ->filter(fn ($w) => ($completedByWorkflow[$w->id] ?? 0) > 0 || ($failedByWorkflow[$w->id] ?? 0) > 0)
            ->map(fn ($w) => [
                'name'      => $w->name,
                'completed' => $completedByWorkflow[$w->id] ?? 0,
                'failed'    => $failedByWorkflow[$w->id] ?? 0,
            ])
            ->values()
            ->all();

        return [
            'total_completed' => (int) $completedByWorkflow->sum(),
            'total_failed'    => (int) $failedByWorkflow->sum(),
            'by_workflow'     => $byWorkflow,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildChatbotSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $chatbotClass */
        $chatbotClass = 'App\Extensions\Chatbot\System\Models\Chatbot';
        /** @var class-string $historyClass */
        $historyClass = 'App\Extensions\Chatbot\System\Models\ChatbotHistory';
        /** @var class-string $conversationClass */
        $conversationClass = 'App\Extensions\Chatbot\System\Models\ChatbotConversation';

        if (! class_exists($chatbotClass) || ! class_exists($historyClass)) {
            return null;
        }

        $chatbots = $chatbotClass::query()->where('user_id', $userId)->get(['id', 'title']);
        $chatbotIds = $chatbots->pluck('id');

        $messagesByChatbot = $historyClass::query()
            ->whereIn('chatbot_id', $chatbotIds)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('chatbot_id, count(*) as count')
            ->groupBy('chatbot_id')
            ->pluck('count', 'chatbot_id');

        $conversationsByChatbot = class_exists($conversationClass)
            ? $conversationClass::query()
                ->whereIn('chatbot_id', $chatbotIds)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('chatbot_id, count(*) as count')
                ->groupBy('chatbot_id')
                ->pluck('count', 'chatbot_id')
            : collect();

        $byChatbot = $chatbots
            ->filter(fn ($c) => ($messagesByChatbot[$c->id] ?? 0) > 0 || ($conversationsByChatbot[$c->id] ?? 0) > 0)
            ->map(fn ($c) => [
                'name'          => $c->title,
                'messages'      => $messagesByChatbot[$c->id] ?? 0,
                'conversations' => $conversationsByChatbot[$c->id] ?? 0,
            ])
            ->values()
            ->all();

        return [
            'total_messages'      => (int) $messagesByChatbot->sum(),
            'total_conversations' => (int) $conversationsByChatbot->sum(),
            'by_chatbot'          => $byChatbot,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildMarketingSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $model */
        $model = 'App\Extensions\MarketingBot\System\Models\MarketingCampaign';

        if (! class_exists($model)) {
            return null;
        }

        $campaigns = $model::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['name', 'status', 'started_at', 'finished_at']);

        return [
            'total'     => $campaigns->count(),
            'campaigns' => $campaigns->map(fn ($c) => [
                'name'   => $c->name,
                'status' => $c->status?->value ?? 'unknown',
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBlogPilotSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $model */
        $model = 'App\Extensions\BlogPilot\System\Models\BlogPilotPost';

        if (! class_exists($model)) {
            return null;
        }

        $scheduled = $model::query()
            ->where('user_id', $userId)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->get(['title', 'scheduled_at']);

        $published = $model::query()
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->whereBetween('published_at', [$start, $end])
            ->get(['title', 'published_at']);

        return [
            'scheduled' => $scheduled->map(fn ($p) => [
                'title' => $p->title,
                'time'  => $p->scheduled_at?->format('H:i'),
            ])->all(),
            'published' => $published->map(fn ($p) => ['title' => $p->title])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSocialMediaSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $model */
        $model = 'App\Extensions\AISocialMedia\System\Models\ScheduledPost';

        if (! class_exists($model)) {
            return null;
        }

        $posts = $model::query()
            ->where('user_id', $userId)
            ->whereBetween('posted_at', [$start, $end])
            ->get(['campaign_name', 'platform', 'posted_at']);

        return [
            'total' => $posts->count(),
            'posts' => $posts->map(fn ($p) => [
                'campaign' => $p->campaign_name ?? 'Untitled',
                'platform' => $p->platform,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSocialMediaAgentSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $agentClass */
        $agentClass = 'App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent';
        /** @var class-string $postClass */
        $postClass = 'App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost';

        if (! class_exists($agentClass) || ! class_exists($postClass)) {
            return null;
        }

        $agents = $agentClass::query()->where('user_id', $userId)->get(['id', 'name']);
        $agentIds = $agents->pluck('id');
        $agentNames = $agents->pluck('name', 'id');

        $publishedTotal = $postClass::query()
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'published')
            ->whereBetween('published_at', [$start, $end])
            ->count();

        $scheduledPosts = $postClass::query()
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$start, $end])
            ->get(['agent_id', 'content', 'scheduled_at']);

        $failedTotal = $postClass::query()
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'failed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return [
            'published' => $publishedTotal,
            'failed'    => $failedTotal,
            'scheduled' => $scheduledPosts->map(fn ($p) => [
                'agent'   => $agentNames[$p->agent_id] ?? 'Unknown',
                'preview' => mb_strimwidth(strip_tags($p->content ?? ''), 0, 50, '…'),
                'time'    => $p->scheduled_at?->format('H:i'),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCrmSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $dealClass */
        $dealClass = 'App\Extensions\Crm\System\Models\CrmDeal';
        /** @var class-string $contactClass */
        $contactClass = 'App\Extensions\Crm\System\Models\CrmContact';
        /** @var class-string $companyClass */
        $companyClass = 'App\Extensions\Crm\System\Models\CrmCompany';
        /** @var class-string $taskClass */
        $taskClass = 'App\Extensions\Crm\System\Models\CrmTask';

        if (! class_exists($dealClass) || ! class_exists($contactClass)) {
            return null;
        }

        $newDeals = $dealClass::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['value', 'currency']);

        $newContacts = $contactClass::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $newCompanies = class_exists($companyClass)
            ? $companyClass::query()->where('user_id', $userId)->whereBetween('created_at', [$start, $end])->count()
            : 0;

        $tasksCreated = class_exists($taskClass)
            ? $taskClass::query()->where('user_id', $userId)->whereBetween('created_at', [$start, $end])->count()
            : 0;

        $tasksCompleted = class_exists($taskClass)
            ? $taskClass::query()->where('user_id', $userId)->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end])->count()
            : 0;

        return [
            'new_deals'           => $newDeals->count(),
            'new_deals_value'     => (float) $newDeals->sum('value'),
            'new_contacts'        => $newContacts,
            'new_companies'       => $newCompanies,
            'tasks_created'       => $tasksCreated,
            'tasks_completed'     => $tasksCompleted,
            'total_open_value'    => (float) $dealClass::query()->where('user_id', $userId)->sum('value'),
            'total_deals'         => $dealClass::query()->where('user_id', $userId)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAiUsageSection(int $userId, Carbon $start, Carbon $end): array
    {
        /** @var class-string $chatClass */
        $chatClass = 'App\Models\UserOpenaiChat';
        /** @var class-string $messageClass */
        $messageClass = 'App\Models\UserOpenaiChatMessage';

        return [
            'chat_sessions'      => class_exists($chatClass)
                ? $chatClass::query()->where('user_id', $userId)->whereBetween('created_at', [$start, $end])->count()
                : 0,
            'messages_generated' => class_exists($messageClass)
                ? $messageClass::query()->where('user_id', $userId)->whereBetween('created_at', [$start, $end])->count()
                : 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCreativeSuiteSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $model */
        $model = 'App\Extensions\CreativeSuite\System\Models\CreativeSuiteDocument';

        if (! class_exists($model)) {
            return null;
        }

        $documents = $model::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['name', 'created_at']);

        return [
            'total'     => $documents->count(),
            'documents' => $documents->map(fn ($d) => ['name' => $d->name ?? 'Untitled'])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAiImagesSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $model */
        $model = 'App\Extensions\AIImagePro\System\Models\AiImageProModel';

        if (! class_exists($model)) {
            return null;
        }

        return [
            'generated'  => $model::query()->where('user_id', $userId)->where('status', 'completed')->whereBetween('created_at', [$start, $end])->count(),
            'processing' => $model::query()->where('user_id', $userId)->whereIn('status', ['pending', 'processing'])->whereBetween('created_at', [$start, $end])->count(),
            'failed'     => $model::query()->where('user_id', $userId)->where('status', 'failed')->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildMarketingContactsSection(int $userId, Carbon $start, Carbon $end): ?array
    {
        /** @var class-string $contactClass */
        $contactClass = 'App\Extensions\MarketingBot\System\Models\Whatsapp\Contact';
        /** @var class-string $conversationClass */
        $conversationClass = 'App\Extensions\MarketingBot\System\Models\MarketingConversation';

        $newContacts = class_exists($contactClass)
            ? $contactClass::query()->where('user_id', $userId)->whereBetween('created_at', [$start, $end])->count()
            : 0;

        $conversations = class_exists($conversationClass)
            ? $conversationClass::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->get(['conversation_name', 'type', 'created_at'])
            : collect();

        return [
            'new_contacts'      => $newContacts,
            'new_conversations' => $conversations->count(),
            'conversations'     => $conversations->map(fn ($c) => [
                'name' => $c->conversation_name ?? 'Unnamed',
                'type' => $c->type,
            ])->all(),
        ];
    }
}
