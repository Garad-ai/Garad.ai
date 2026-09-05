<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowCopilotMessage;
use Illuminate\Support\Facades\Auth;

class CopilotService
{
    public function __construct(
        private readonly AIAgentChatService $chatService,
    ) {}

    /**
     * Send a message to the copilot and stream the response as SSE.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<int, array<string, mixed>>  $connectors
     * @param  array<string, mixed>  $triggerConfig
     */
    public function streamChat(
        AIAgentWorkflow $workflow,
        array $steps,
        array $channels,
        array $connectors,
        ?string $triggerType,
        array $triggerConfig,
        array $history,
        string $userMessage,
        callable $emit,
    ): void {
        $systemPrompt = $this->buildSystemPrompt($workflow, $steps, $channels, $connectors, $triggerType, $triggerConfig);

        $messages = array_merge($history, [['role' => 'user', 'content' => $userMessage]]);

        $modelSlug = setting('ai-agent-copilot-model', '') ?? '';
        $model = ($modelSlug !== '' ? EntityEnum::tryFrom($modelSlug) : null) ?? EntityEnum::GPT_5_4_MINI;

        $this->chatService->forUser(Auth::user());

        $this->chatService->streamCopilot(
            systemPrompt: $systemPrompt,
            messages: $messages,
            model: $model,
            onChunk: fn (string $type, array $payload) => $emit(['type' => $type, 'payload' => $payload]),
            tools: $this->buildTools(),
        );
    }

    /**
     * Build the system prompt with workflow context.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<int, array<string, mixed>>  $connectors
     * @param  array<string, mixed>  $triggerConfig
     */
    private function buildSystemPrompt(
        AIAgentWorkflow $workflow,
        array $steps,
        array $channels,
        array $connectors,
        ?string $triggerType,
        array $triggerConfig,
    ): string {
        $stepsJson = json_encode($steps, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

        $channelLines = empty($channels)
            ? 'No channels configured yet.'
            : implode("\n", array_map(
                fn (array $ch) => "  - type={$ch['type']}, id={$ch['id']}, name={$ch['name']}",
                $channels,
            ));

        $connectorLines = empty($connectors)
            ? 'No connectors configured yet.'
            : implode("\n", array_map(
                fn (array $c) => "  - ID: {$c['id']}, Type: {$c['type']}, Email: " . ($c['email'] ?? 'N/A') . ', Active: ' . ($c['is_active'] ? 'yes' : 'no'),
                $connectors,
            ));

        $currentTrigger = $triggerType
            ? "Type: {$triggerType}, Config: " . json_encode($triggerConfig)
            : 'Not set';

        return <<<PROMPT
You are a Workflow Copilot — an AI assistant that helps users build and modify automation workflows via tool calls.

**Step config reference** (keys to use in add_step/update_step config):
- ai_call: { system_prompt (required, default "You are a helpful assistant."), user_message (required, default "{message_text}"), store_output_as (required, default "ai_response") }
- send_message: { channel_id (required — use trigger channel_id), recipient_id (required, default "{sender_id}"), message (required — use the store_output_as var of the preceding ai_call step, e.g. "{ai_response}") }
- generate_report: { date_range, sections, system_prompt, store_output_as (default "report_output") }
- path: { paths: [{ id, name, ruleGroups: [{ rules: [{ field, operator, value }] }], steps: [] }] } — operators: contains|not_contains|equals|not_equals|is_empty|is_not_empty|starts_with|ends_with|greater_than|less_than. Rules in a ruleGroup=AND, multiple groups=OR, empty ruleGroups=fallback path.
- external_chatbot: { action_event: list_chatbots|get_chatbot_analytics|list_chatbot_conversations|get_chatbot_conversation|close_chatbot_ticket, store_output_as (required), ...params }
- marketing_bot: { action_event: list_marketing_campaigns|get_marketing_stats|list_marketing_conversations|create_marketing_campaign|schedule_marketing_campaign, store_output_as (required), ...params }
- social_media_agent: { action_event: create_social_media_agent|list_social_media_agents|list_social_posts|create_social_post|get_social_post_analytics|approve_social_post, store_output_as (required), ...params }
- crm_agent: { action_event: list_contacts|create_contact|list_companies|create_company|list_deals|create_deal|move_deal_stage|create_task|add_note|get_crm_analytics, store_output_as (required), output_format (json|plain, for list_* and get_crm_analytics), ...params } — create_contact needs first_name; create_company needs name; create_deal needs title; move_deal_stage needs deal_id+stage_id; add_note needs notable (contact|deal)+notable_id+content
- gmail: { action_event: send_email|reply_to_email|create_draft|create_draft_reply|find_email|add_label_to_email|remove_label_from_email|remove_label_from_conversation|archive_email|delete_email|create_label|get_attachment_by_filename, store_output_as (required), ...params } — type and action must both be "gmail"
- outlook: { action_event: send_email|create_draft_email|send_draft_email|create_draft_reply|reply_to_email|forward_email|copy_email|move_email_to_folder|delete_email|mark_email_as_read_unread|flag_unflag_email|set_email_importance|set_categories_on_email|remove_categories_from_email|find_emails|create_folder|create_event|update_calendar_event|add_attendees_to_calendar_event|find_calendar_events|create_contact|update_contact|delete_contact|find_contacts, store_output_as (required), ...params } — type and action must both be "outlook"

Context variables usable in string config fields: {message_text}, {sender_id}, {ai_response}, {found_emails}, and any other store_output_as key from prior steps.

**Schedule cron presets** (use these exact expressions — the UI highlights them automatically):
  "0 9 * * 1-5" → weekdays 9 AM | "0 9 * * *" → daily 9 AM | "0 9 * * 1" → Mondays 9 AM
  "0 0 1 * *" → 1st of month midnight | "0 * * * *" → hourly | "*/30 * * * *" → every 30 min
  All times UTC. For ambiguous schedules (e.g. "every morning"), default to 9 AM and confirm.

**Rules:**
- Always generate unique UUIDs for new step IDs.
- The `action` field of every step must equal its `type` exactly.
- For ai_call: ALWAYS include `system_prompt` and `user_message` in the config — never omit them, even if the user didn't specify values. Use sensible defaults: system_prompt default = "You are a helpful assistant.", user_message default = "{message_text}". Never use key names `prompt` or `message_text` for these fields.
- Always set `store_output_as` for output-producing steps (ai_call, generate_report, external_chatbot, marketing_bot, social_media_agent, crm_agent, gmail, outlook). Use a descriptive snake_case key (e.g. "ai_response", "report_output", "found_emails"). This key becomes the context variable `{key}` available in later steps.
- For send_message: ALWAYS include channel_id (use trigger channel_id), recipient_id (default "{sender_id}"), and message (use the store_output_as var of the preceding ai_call, e.g. "{ai_response}"). Never omit these fields.
- NEVER add ai_call alongside social_media_agent steps — social_media_agent handles AI generation internally.
- Ask before creating steps with unknown required fields (tone, language, platform, etc.) — one question at a time.
- Before adding gmail/outlook steps, check connectors. If missing or inactive → call emit_action_buttons with open_connectors_modal.
- If a channel is mentioned but not in the channels list → call emit_action_buttons with open_channel_modal.
- After modifying the workflow, briefly confirm what changed in your text response.
- When workflow looks complete (steps + trigger configured), offer to test via emit_action_buttons with open_test_panel.
- Ask one targeted question when intent is ambiguous — never guess and silently proceed.

**When to call emit_action_buttons:**
- Channel platform mentioned (e.g. "telegram", "slack", "whatsapp") AND no channel with matching `type` exists in Available channels list → open_channel_modal with channel_type, icon: channel. If a channel with matching type DOES exist, use it directly — do NOT open modal.
- User asks to add/connect a channel with no type given → open_channel_modal, icon: channel
- User wants to configure or change the trigger → open_trigger_panel, icon: bolt
- User asks to test/run workflow → open_test_panel, icon: play
- User asks to open settings for a specific step → open_step_settings with step_id, icon: settings
- User asks to rename workflow or change model → open_workflow_settings, icon: adjustments
- Gmail/Outlook connector missing or inactive → open_connectors_modal with connector_type, icon: plug

Current workflow: "{$workflow->name}"
Current trigger: {$currentTrigger}
Current steps:
{$stepsJson}

Available channels:
{$channelLines}

Connected connectors:
{$connectorLines}
PROMPT;
    }

    /**
     * Build OpenAI tool definitions for native function calling.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTools(): array
    {
        $stepTypes = ['ai_call', 'send_message', 'generate_report', 'path', 'external_chatbot', 'marketing_bot', 'social_media_agent', 'crm_agent', 'gmail', 'outlook'];

        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'add_step',
                    'description' => 'Add a new step to the workflow.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'id'     => ['type' => 'string', 'description' => 'Unique UUID for the step'],
                            'type'   => ['type' => 'string', 'enum' => $stepTypes],
                            'app'    => ['type' => 'string'],
                            'action' => ['type' => 'string', 'description' => 'Must equal type exactly', 'enum' => $stepTypes],
                            'config' => [
                                'type'                 => 'object',
                                'additionalProperties' => true,
                                'description'          => implode(' ', [
                                    'Step configuration. Required keys by type:',
                                    'ai_call: system_prompt (string, REQUIRED — default "You are a helpful assistant." if user did not specify), user_message (string, REQUIRED — default "{message_text}" if user did not specify), store_output_as (string, REQUIRED);',
                                    'send_message: channel_id (string);',
                                    'generate_report: system_prompt (string), date_range (string), sections (array of strings), store_output_as (string);',
                                    'path: paths (array of {id, name, ruleGroups, steps});',
                                    'external_chatbot: store_output_as (string, required);',
                                    'marketing_bot: store_output_as (string, required);',
                                    'social_media_agent: store_output_as (string, required);',
                                    'crm_agent: action_event (string, required) + store_output_as (string, required) + per-event params (create_contact: first_name; create_company: name; create_deal: title; move_deal_stage: deal_id+stage_id; add_note: notable+notable_id+content);',
                                    'gmail: store_output_as (string, required);',
                                    'outlook: store_output_as (string, required).',
                                ]),
                            ],
                            'order'  => ['type' => 'integer'],
                        ],
                        'required' => ['id', 'type', 'action', 'config', 'order'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'update_step',
                    'description' => 'Update configuration of an existing step.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'id'     => ['type' => 'string', 'description' => 'UUID of the step to update'],
                            'config' => ['type' => 'object', 'description' => 'Config keys to update', 'additionalProperties' => true],
                        ],
                        'required' => ['id', 'config'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'delete_step',
                    'description' => 'Remove a step from the workflow.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'UUID of the step to delete'],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'reorder_steps',
                    'description' => 'Reorder workflow steps by providing an ordered list of step UUIDs.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Step UUIDs in desired order'],
                        ],
                        'required' => ['ids'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'set_trigger',
                    'description' => 'Set the workflow trigger type and configuration.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'trigger_type'   => ['type' => 'string', 'enum' => ['schedule', 'channel_message', 'webhook']],
                            'trigger_config' => [
                                'type'       => 'object',
                                'properties' => [
                                    'cron'            => ['type' => 'string', 'description' => '5-field cron expression (UTC). Required for schedule triggers.'],
                                    'channel_id'      => ['type' => ['integer', 'null']],
                                    'keyword_pattern' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'required' => ['trigger_type', 'trigger_config'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'emit_action_buttons',
                    'description' => 'Render clickable UI action cards in the chat for manual actions the user must take (e.g. connecting a channel, OAuth setup, opening settings, testing the workflow).',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'buttons' => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'label'       => ['type' => 'string', 'description' => 'Bold title for the card'],
                                        'description' => ['type' => 'string', 'description' => 'Short call-to-action sub-label'],
                                        'action'      => ['type' => 'string', 'enum' => ['open_channel_modal', 'open_connectors_modal', 'open_trigger_panel', 'open_step_settings', 'open_test_panel', 'open_workflow_settings']],
                                        'args'        => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Optional: channel_type, connector_type, or step_id'],
                                        'icon'        => ['type' => 'string', 'enum' => ['channel', 'bolt', 'settings', 'play', 'adjustments', 'plug']],
                                    ],
                                    'required' => ['label', 'description', 'action', 'icon'],
                                ],
                            ],
                        ],
                        'required' => ['buttons'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Load recent copilot history for a workflow.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function loadHistory(AIAgentWorkflow $workflow, int $turns = 20): array
    {
        return AIAgentWorkflowCopilotMessage::query()
            ->where('workflow_id', $workflow->id)
            ->latest()
            ->limit($turns)
            ->get()
            ->reverse()
            ->map(fn (AIAgentWorkflowCopilotMessage $m) => [
                'role'    => $m->role,
                'content' => $m->content ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Persist a copilot message.
     *
     * @param  array<int, array{name: string, args: array<string, mixed>}>|null  $toolCalls
     * @param  array<string, mixed>|null  $metadata  Extra data such as thinking { content, duration_ms }
     */
    public function saveMessage(AIAgentWorkflow $workflow, string $role, string $content, ?array $toolCalls = null, ?array $metadata = null): AIAgentWorkflowCopilotMessage
    {
        return AIAgentWorkflowCopilotMessage::query()->create([
            'workflow_id' => $workflow->id,
            'role'        => $role,
            'content'     => $content,
            'tool_calls'  => $toolCalls,
            'metadata'    => $metadata,
        ]);
    }
}
