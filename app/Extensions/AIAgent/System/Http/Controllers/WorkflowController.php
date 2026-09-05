<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AIAgent\System\Engine\AIAgentActionRegistry;
use App\Extensions\AIAgent\System\Enums\ChannelEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Exceptions\PlanLimitExceededException;
use App\Extensions\AIAgent\System\Http\Controllers\Concerns\ResolvesTemplateAvatars;
use App\Extensions\AIAgent\System\Http\Requests\StoreWorkflowRequest;
use App\Extensions\AIAgent\System\Models\AIAgentAvatar;
use App\Extensions\AIAgent\System\Models\AIAgentChannel;
use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowCopilotMessage;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgent\System\Services\AIAgentChatService;
use App\Extensions\AIAgent\System\Services\AIAgentPlanService;
use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;
use Throwable;

class WorkflowController extends Controller
{
    use ResolvesTemplateAvatars;

    public function __construct(
        private readonly AIAgentPlanService $planService,
        private readonly AIAgentChatService $chatService,
    ) {}

    public function index(): View
    {
        $workflows = AIAgentWorkflow::query()
            ->with('channel')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        return view('ai-agent::workflows.index', [
            'title'     => __('Agents'),
            'workflows' => $workflows,
            'templates' => collect(config('ai-agent.workflow_templates', []))
                ->map(fn ($t, $k) => array_merge(['key' => $k, 'avatar' => $this->templateAvatar($k), 'trigger' => $t['config']['trigger_type'] ?? 'schedule'], $t))
                ->values()
                ->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $templateKey = $request->query('template');
        $templateData = $templateKey
            ? config("ai-agent.workflow_templates.{$templateKey}.config")
            : null;
        $templateAvatar = $templateKey
            ? $this->templateAvatar($templateKey)
            : null;
        $userName = explode(' ', Auth::user()->name ?? '')[0];

        return view('ai-agent::workflows.builder.index', [
            'title'          => __('New Agent'),
            'workflow'       => null,
            'username'		     => $userName,
            'channels'       => $this->channelData(),
            'connectors'     => $this->connectorData(),
            'memories'       => AIAgentMemory::query()->where('user_id', Auth::id())->latest()->get(['id', 'memory']),
            'avatarPresets'  => $this->avatarPresets(),
            'templateData'   => $templateData,
            'templateAvatar' => $templateAvatar,
            'copilotInit'    => $request->query('copilot_init'),
        ]);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse|RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        try {
            $this->planService->checkWorkflowLimit(Auth::user());
        } catch (PlanLimitExceededException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['status'] = WorkflowStatusEnum::Active->value;

        if (! empty($data['steps'])) {
            $data['steps'] = $this->ensureStepIds($data['steps']);
        }

        Log::info('[AIAgent] Storing workflow', [
            'user_id'      => Auth::id(),
            'name'         => $data['name'] ?? null,
            'trigger_type' => $data['trigger_type'] ?? null,
            'step_count'   => count($data['steps'] ?? $data['actions'] ?? []),
        ]);

        $workflow = AIAgentWorkflow::query()->create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => __('Agent created.'),
                'data'    => ['id' => $workflow->id],
            ]);
        }

        return redirect()
            ->route('dashboard.user.ai-agent.workflows.index')
            ->with(['type' => 'success', 'message' => __('Agent created successfully.')]);
    }

    public function availableActions(): JsonResponse
    {
        $registry = app(AIAgentActionRegistry::class);

        $actions = $registry->all();

        return response()->json(['data' => $actions, 'failed' => $registry->failed()]);
    }

    public function availableSocialMediaAgents(): JsonResponse
    {
        $agents = SocialMediaAgent::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return response()->json(['data' => $agents]);
    }

    public function availableSocialMediaPlatforms(): JsonResponse
    {
        $platforms = SocialMediaPlatform::query()
            ->where('user_id', Auth::id())
            ->orderBy('platform')
            ->get(['id', 'platform', 'credentials'])
            ->map(fn ($p) => [
                'id'       => $p->id,
                'platform' => $p->platform,
                'name'     => $p->credentials['name'] ?? $p->credentials['username'] ?? ucfirst($p->platform),
            ]);

        return response()->json(['data' => $platforms]);
    }

    public function availableCrmRecords(): JsonResponse
    {
        $contactModel = CrmContact::class;
        $dealModel = CrmDeal::class;
        $companyModel = CrmCompany::class;
        $stageModel = CrmDealStage::class;

        if (! class_exists($contactModel) || ! class_exists($dealModel) || ! class_exists($companyModel) || ! class_exists($stageModel)) {
            return response()->json(['data' => ['contacts' => [], 'deals' => [], 'companies' => [], 'stages' => []]]);
        }

        $contacts = $contactModel::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn ($c): array => [
                'id'    => $c->id,
                'label' => trim($c->first_name . ' ' . $c->last_name) ?: ($c->email ?? 'Contact #' . $c->id),
            ]);

        $deals = $dealModel::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'title'])
            ->map(fn ($d): array => [
                'id'    => $d->id,
                'label' => $d->title ?: 'Deal #' . $d->id,
            ]);

        $companies = $companyModel::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn ($c): array => [
                'id'    => $c->id,
                'label' => $c->name ?: 'Company #' . $c->id,
            ]);

        $stageModel::createDefaultsForUser((int) Auth::id());

        $stages = $stageModel::query()
            ->where('user_id', Auth::id())
            ->orderBy('order')
            ->get(['id', 'name'])
            ->map(fn ($s): array => [
                'id'    => $s->id,
                'label' => $s->name ?: 'Stage #' . $s->id,
            ]);

        return response()->json(['data' => ['contacts' => $contacts, 'deals' => $deals, 'companies' => $companies, 'stages' => $stages]]);
    }

    public function availableModels(): JsonResponse
    {
        // Only engines that AIAgentChatService can actually use for text generation
        $supportedEngines = [
            EngineEnum::OPEN_AI,
            EngineEnum::ANTHROPIC,
            EngineEnum::GEMINI,
            EngineEnum::DEEP_SEEK,
            EngineEnum::X_AI,
        ];

        $groups = collect($supportedEngines)
            ->map(function (EngineEnum $engine): array {
                $models = $engine->getEnabledModels()
                    ->filter(fn ($entity) => $entity->key->isChatModel())
                    ->filter(function ($entity) {
                        $driver = Entity::driver($entity->key);

                        return $driver->isUnlimitedCredit() || $driver->creditBalance() > 0;
                    })
                    ->map(fn ($entity) => [
                        'value' => $entity->key->value,
                        'label' => $entity->title,
                    ])
                    ->values()
                    ->all();

                return [
                    'engine' => $engine->value,
                    'label'  => $engine->label(),
                    'models' => $models,
                ];
            })
            ->filter(fn (array $group) => ! empty($group['models']))
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function generateFromPrompt(Request $request): JsonResponse
    {
        set_time_limit(120);

        $request->validate([
            'prompt'   => ['required', 'string', 'max:2000'],
            'channels' => ['nullable', 'array'],
        ]);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $channels = collect($request->input('channels', []))
            ->map(fn ($ch) => "  - id={$ch['id']}, name=\"{$ch['name']}\", type={$ch['type']}")
            ->join("\n");

        $channelsHint = $channels
            ? "Available channels (use the id value for channel_id fields):\n{$channels}"
            : 'No channels configured yet — use null for channel_id fields.';

        $systemPrompt = <<<PROMPT
You are a workflow configuration assistant. Return ONLY a raw JSON object — no markdown, no prose, no code fences.

Shape:
{
  "name": "Short Descriptive Name (Title Case, spaces not underscores, max 6 words)",
  "trigger_type": "schedule | channel_message | webhook",
  "trigger_config": {},
  "steps": []
}

trigger_config keys by trigger_type:
- schedule: { "cron": "5-field cron e.g. 0 9 * * *" }
- channel_message: { "channel_id": <integer id from available channels, or null for any>, "keyword_pattern": "" }
- webhook: {}

steps array items (each has "type", "app", "action", "config", "order"):
- type and action MUST be one of: "ai_call" | "send_message" | "generate_report"
- ai_call: app="AI", config={ "system_prompt": "", "user_message": "{message_text}", "store_output_as": "ai_response" }
  Available variables: {sender_id}, {ai_response}, {report_text}
- send_message: app="Channels", config={ "channel_id": <integer or null>, "recipient_id": "{sender_id}", "message": "" }
- generate_report: app="Utilities", config={ "date_range": "today|yesterday|this_week", "sections": ["messages","workflows"], "store_output_as": "report_text" }
  Valid sections: messages, workflows, chatbot, marketing, blogpilot, social_media, social_media_agent, ai_usage, creative_suite, ai_images, marketing_contacts

{$channelsHint}

Output ONLY the JSON object. Any extra text breaks the parser.
PROMPT;

        try {
            set_time_limit(120);

            $this->chatService->forUser(Auth::user());

            $raw = $this->chatService->chat($systemPrompt, [
                ['role' => 'user', 'content' => $request->input('prompt')],
            ]);

            $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
            $raw = preg_replace('/\s*```$/', '', $raw);

            $decoded = json_decode(trim($raw), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('[AIAgent] generateFromPrompt: invalid JSON from AI', ['user_id' => Auth::id()]);

            return response()->json([
                'status'  => 'error',
                'message' => __('The AI returned an unexpected response. Please try again or rephrase your description.'),
            ], 422);
        } catch (Throwable $e) {
            Log::error('[AIAgent] generateFromPrompt: AI call failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'message' => __('AI generation failed. Please try again.')], 500);
        }

        $allowed = ['schedule', 'channel_message', 'webhook'];

        if (empty($decoded['name']) || ! in_array($decoded['trigger_type'] ?? '', $allowed, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('The AI returned a workflow structure that could not be parsed. Please try again.'),
            ], 422);
        }

        $steps = $this->ensureStepIds($decoded['steps'] ?? []);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'name'           => (string) ($decoded['name'] ?? ''),
                'trigger_type'   => $decoded['trigger_type'],
                'trigger_config' => $decoded['trigger_config'] ?? [],
                'steps'          => $steps,
            ],
        ]);
    }

    public function edit(AIAgentWorkflow $workflow): View
    {
        $this->authorize('update', $workflow);

        $copilotMessages = AIAgentWorkflowCopilotMessage::query()
            ->where('workflow_id', $workflow->id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'tool_calls', 'metadata'])
            ->map(fn (AIAgentWorkflowCopilotMessage $m) => [
                'id'        => $m->id,
                'role'      => $m->role,
                'content'   => $m->content ?? '',
                'toolCalls' => $m->tool_calls ?? [],
                'metadata'  => $m->metadata ?? null,
            ])
            ->values()
            ->all();
        $userName = explode(' ', Auth::user()->name ?? '')[0];

        return view('ai-agent::workflows.builder.index', [
            'title'           => __('Edit Agent'),
            'workflow'        => $workflow,
            'username'		      => $userName,
            'channels'        => $this->channelData(),
            'connectors'      => $this->connectorData(),
            'memories'        => AIAgentMemory::query()->where('user_id', Auth::id())->latest()->get(['id', 'memory']),
            'avatarPresets'   => $this->avatarPresets(),
            'copilotMessages' => $copilotMessages,
        ]);
    }

    public function update(StoreWorkflowRequest $request, AIAgentWorkflow $workflow): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $workflow);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $data = $request->validated();

        if (! empty($data['steps'])) {
            $data['steps'] = $this->ensureStepIds($data['steps']);
        }

        $workflow->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'status'  => 'success',
                'message' => __('Agent updated.'),
                'data'    => ['id' => $workflow->id, 'status' => $workflow->fresh()->status->value],
            ]);
        }

        return redirect()
            ->route('dashboard.user.ai-agent.workflows.index')
            ->with(['type' => 'success', 'message' => __('Agent updated.')]);
    }

    public function uploadAvatar(Request $request, AIAgentWorkflow $workflow): JsonResponse
    {
        $this->authorize('update', $workflow);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        // Preset selection — store the public URL directly
        if ($request->has('avatar_url')) {
            $request->validate(['avatar_url' => ['required', 'string']]);
            $avatarUrl = $request->input('avatar_url');

            // Delete previously uploaded custom avatar if it was a storage path
            if ($workflow->avatar && ! str_starts_with($workflow->avatar, 'http') && ! str_starts_with($workflow->avatar, '/')) {
                Storage::disk('public')->delete($workflow->avatar);
            }

            $workflow->update(['avatar' => $avatarUrl]);

            return response()->json(['status' => 'success', 'url' => $avatarUrl]);
        }

        // Custom file upload
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);

        if ($workflow->avatar && ! str_starts_with($workflow->avatar, 'http') && ! str_starts_with($workflow->avatar, '/')) {
            Storage::disk('public')->delete($workflow->avatar);
        }

        $path = $request->file('avatar')->store('ai-agent/avatars', 'public');
        $workflow->update(['avatar' => $path]);

        return response()->json([
            'status' => 'success',
            'url'    => '/uploads/' . $path,
        ]);
    }

    public function toggleStatus(AIAgentWorkflow $workflow): JsonResponse
    {
        $this->authorize('update', $workflow);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $newStatus = $workflow->status === WorkflowStatusEnum::Active
            ? WorkflowStatusEnum::Paused
            : WorkflowStatusEnum::Active;

        $workflow->update(['status' => $newStatus->value]);

        return response()->json(['status' => 'success', 'data' => ['status' => $newStatus->value]]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function channelData(): Collection
    {
        return AIAgentChannel::query()
            ->where('user_id', Auth::id())
            ->where('type', '!=', ChannelEnum::MagicAi->value)
            ->latest()
            ->get()
            ->map(function (AIAgentChannel $channel): array {
                $webhookUrl = null;
                $refreshWebhookUrl = null;

                try {
                    if ($channel->type === ChannelEnum::Telegram) {
                        $refreshWebhookUrl = route('dashboard.user.ai-agent.channels.refresh-webhook', $channel);
                    } elseif ($channel->type === ChannelEnum::Whatsapp) {
                        $webhookUrl = route('api.ai-agent.whatsapp.webhook', ['channel' => $channel->id]);
                    } elseif ($channel->type === ChannelEnum::Slack) {
                        $webhookUrl = route('api.ai-agent.slack.webhook', ['channel' => $channel->id]);
                    }
                } catch (Throwable) {
                }

                return [
                    'id'                  => $channel->id,
                    'name'                => $channel->name,
                    'type'                => $channel->type->value,
                    'type_label'          => $channel->type->label(),
                    'webhook_url'         => $webhookUrl,
                    'refresh_webhook_url' => $refreshWebhookUrl,
                    'delete_url'          => route('dashboard.user.ai-agent.channels.destroy', $channel),
                ];
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function connectorData(): array
    {
        if (! class_exists(AIAgentConnector::class)) {
            return [];
        }

        return AIAgentConnector::query()
            ->forUser(Auth::user())
            ->get()
            ->map(function ($connector): array {
                $reconnectUrl = null;

                try {
                    $reconnectUrl = match ($connector->type) {
                        'gmail'   => route('dashboard.user.ai-agent.connectors.gmail.redirect'),
                        'outlook' => route('dashboard.user.ai-agent.connectors.outlook.redirect'),
                        default   => null,
                    };
                } catch (Throwable) {
                }

                return [
                    'id'            => $connector->id,
                    'type'          => $connector->type,
                    'name'          => $connector->getCredential('name'),
                    'email'         => $connector->getCredential('email'),
                    'picture'       => $connector->getCredential('picture'),
                    'is_active'     => $connector->is_active && ($connector->expires_at?->isFuture() ?? true),
                    'reconnect_url' => $reconnectUrl,
                    'delete_url'    => route('dashboard.user.ai-agent.connectors.destroy', $connector),
                ];
            })
            ->all();
    }

    private function avatarPresets(): array
    {
        return AIAgentAvatar::query()
            ->whereNull('user_id')
            ->get()
            ->map(fn (AIAgentAvatar $a) => ['id' => $a->id, 'url' => $a->avatar_url])
            ->values()
            ->all();
    }

    /**
     * Ensure every step in the array has a unique UUID id.
     *
     * @param  array<int, array<string, mixed>>  $steps
     *
     * @return array<int, array<string, mixed>>
     */
    private function ensureStepIds(array $steps): array
    {
        return array_values(array_map(function (array $step, int $index): array {
            if (empty($step['id'])) {
                $step['id'] = Str::uuid()->toString();
            }

            if (! isset($step['order'])) {
                $step['order'] = $index;
            }

            return $step;
        }, $steps, array_keys($steps)));
    }

    public function runs(AIAgentWorkflow $workflow): JsonResponse
    {
        $this->authorize('view', $workflow);

        $runs = AIAgentWorkflowRun::query()
            ->where('workflow_id', $workflow->id)
            ->orderByDesc('started_at')
            ->limit(50)
            ->get(['id', 'status', 'error_message', 'steps_output', 'started_at', 'completed_at'])
            ->map(fn (AIAgentWorkflowRun $run) => [
                'id'            => $run->id,
                'status'        => $run->status->value,
                'error_message' => $run->error_message,
                'steps_output'  => $run->steps_output,
                'started_at'    => $run->started_at?->toIso8601String(),
                'completed_at'  => $run->completed_at?->toIso8601String(),
                'duration_ms'   => $run->started_at && $run->completed_at
                    ? $run->started_at->diffInMilliseconds($run->completed_at)
                    : null,
            ]);

        return response()->json(['data' => $runs]);
    }

    public function destroy(AIAgentWorkflow $workflow): RedirectResponse
    {
        $this->authorize('delete', $workflow);

        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $workflow->delete();

        return redirect()
            ->route('dashboard.user.ai-agent.workflows.index')
            ->with(['type' => 'success', 'message' => __('Agent deleted.')]);
    }
}
