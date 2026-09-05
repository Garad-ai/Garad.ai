@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Edit Agent'))

@section('content')
    <form
		class="py-10"
        method="POST"
        action="{{ route('dashboard.user.ai-agent.workflows.update', $workflow) }}"
        x-data="workflowBuilder()"
    >
        @csrf
        @method('PUT')
        <div class="flex flex-col gap-6 py-10">

            <x-card>
                <div class="flex flex-col gap-4 p-5">
                    <h3 class="text-sm font-semibold">@lang('Agent Details')</h3>

                    <x-forms.input
                        name="name"
                        :label="__('Name')"
                        :value="old('name') ?? $workflow->name"
                        required
                    />
                    <x-forms.input
                        name="description"
                        :label="__('Description')"
                        :value="(old('description') ?? $workflow->description) ?? ''"
                    />

                    <x-forms.input type="select"
                        name="status"
                        :label="__('Status')"
                    >
                        @foreach (\App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum::cases() as $status)
                            <option
                                value="{{ $status->value }}"
                                {{ $workflow->status->value === $status->value ? 'selected' : '' }}
                            >
                                {{ ucfirst($status->value) }}
                            </option>
                        @endforeach
                    </x-forms.input>
                </div>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-4 p-5">
                    <h3 class="text-sm font-semibold">@lang('Trigger')</h3>

                    <x-forms.input type="select"
                        name="trigger_type"
                        :label="__('Trigger Type')"
                        x-model="triggerType"
                    >
                        @foreach ($triggerTypes as $type)
                            <option
                                value="{{ $type->value }}"
                                {{ $workflow->trigger_type->value === $type->value ? 'selected' : '' }}
                            >
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </x-forms.input>

                    <div x-show="triggerType === 'schedule'" x-cloak>
                        <x-forms.input
                            name="trigger_config[cron]"
                            :label="__('Cron Expression')"
                            :value="(old('trigger_config.cron') ?? ($workflow->trigger_config['cron'] ?? null)) ?? ''"
                        />
                    </div>

                    <div x-show="triggerType === 'channel_message'" x-cloak>
                        <x-forms.input type="select"
                            name="trigger_config[channel_id]"
                            :label="__('Channel')"
                        >
                            @foreach ($channels as $channel)
                                <option
                                    value="{{ $channel->id }}"
                                    {{ ($workflow->trigger_config['channel_id'] ?? null) == $channel->id ? 'selected' : '' }}
                                >
                                    {{ $channel->name }}
                                </option>
                            @endforeach
                        </x-forms.input>
                        <x-forms.input
                            name="trigger_config[keyword_pattern]"
                            :label="__('Keyword (optional)')"
                            :placeholder="__('e.g. /start — leave empty to match any message')"
                            :value="(old('trigger_config.keyword_pattern') ?? ($workflow->trigger_config['keyword_pattern'] ?? null)) ?? ''"
                        />
                    </div>

                    <div x-show="triggerType === 'webhook'" x-cloak>
                        <p class="text-2xs text-foreground/60">@lang('Webhook URL:')</p>
                        <code class="mt-1 block rounded-input bg-input-background px-3 py-2 text-2xs">
                            {{ route('api.ai-agent.webhook', ['uuid' => $workflow->id]) }}
                        </code>
                    </div>
                </div>
            </x-card>

            {{-- Actions --}}
            <x-card>
                <div class="flex flex-col gap-4 p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold">@lang('Actions')</h3>
                        <x-button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="addAction"
                        >
                            <x-tabler-plus class="size-3.5" />
                            @lang('Add action')
                        </x-button>
                    </div>

                    <template x-for="(action, index) in actions" :key="index">
                        <div class="rounded-input border border-input-border bg-input-background p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-1 flex-col gap-3">
                                    <select
                                        :name="`actions[${index}][type]`"
                                        x-model="action.type"
                                        class="rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                    >
                                        <option value="ai_call">@lang('Ask Agent')</option>
                                        <option value="send_message">@lang('Send Message')</option>
                                        <option value="generate_report">@lang('Generate Report')</option>
                                    </select>

                                    {{-- AI Call fields --}}
                                    <div x-show="action.type === 'ai_call'" class="flex flex-col gap-2">
                                        <textarea
                                            :name="`actions[${index}][config][system_prompt]`"
                                            x-model="action.config.system_prompt"
                                            placeholder="{{ __('System prompt…') }}"
                                            rows="3"
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        ></textarea>
                                        <input
                                            :name="`actions[${index}][config][user_message]`"
                                            x-model="action.config.user_message"
                                            placeholder="{{ __('User message — use {message_text} for incoming text, {sender_id} for chat id') }}"
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        />
                                        <input
                                            :name="`actions[${index}][config][store_output_as]`"
                                            x-model="action.config.store_output_as"
                                            placeholder="{{ __('Store output as… (default: ai_response)') }}"
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        />
                                    </div>

                                    {{-- Send Message fields --}}
                                    <div x-show="action.type === 'send_message'" class="flex flex-col gap-2">
                                        <select
                                            :name="`actions[${index}][config][channel_id]`"
                                            x-model="action.config.channel_id"
                                            class="rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        >
                                            @foreach ($channels as $channel)
                                                <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                                            @endforeach
                                        </select>
                                        <input
                                            :name="`actions[${index}][config][recipient_id]`"
                                            x-model="action.config.recipient_id"
                                            placeholder="{{ __('Recipient ID — Telegram chat_id, or {sender_id}') }}"
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        />
                                        <textarea
                                            :name="`actions[${index}][config][message]`"
                                            x-model="action.config.message"
                                            placeholder="{{ __('Message text — use {ai_response} for AI output') }}"
                                            rows="3"
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        ></textarea>
                                    </div>

                                    {{-- Generate Report fields --}}
                                    <div x-show="action.type === 'generate_report'" class="flex flex-col gap-2">
                                        <select
                                            :name="`actions[${index}][config][date_range]`"
                                            class="rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :value="action.config.date_range ?? 'today'"
                                        >
                                            <option value="today">@lang('Today')</option>
                                            <option value="yesterday">@lang('Yesterday')</option>
                                            <option value="this_week">@lang('This Week')</option>
                                        </select>
                                        <p class="text-2xs text-foreground/50">@lang('Sections to include:')</p>
                                        <div class="flex flex-wrap gap-3 text-xs">
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="messages"
                                                    :checked="(action.config.sections ?? ['messages','workflows']).includes('messages')"
                                                />
                                                @lang('Messages')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="workflows"
                                                    :checked="(action.config.sections ?? ['messages','workflows']).includes('workflows')"
                                                />
                                                @lang('Agents')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="chatbot"
                                                    :checked="(action.config.sections ?? []).includes('chatbot')"
                                                />
                                                @lang('Chatbot')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="marketing"
                                                    :checked="(action.config.sections ?? []).includes('marketing')"
                                                />
                                                @lang('Marketing')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="blogpilot"
                                                    :checked="(action.config.sections ?? []).includes('blogpilot')"
                                                />
                                                @lang('Blog Pilot')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="social_media"
                                                    :checked="(action.config.sections ?? []).includes('social_media')"
                                                />
                                                @lang('Social Media')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="social_media_agent"
                                                    :checked="(action.config.sections ?? []).includes('social_media_agent')"
                                                />
                                                @lang('Social Media Agent')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="ai_usage"
                                                    :checked="(action.config.sections ?? []).includes('ai_usage')"
                                                />
                                                @lang('AI Usage')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="creative_suite"
                                                    :checked="(action.config.sections ?? []).includes('creative_suite')"
                                                />
                                                @lang('Creative Suite')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="ai_images"
                                                    :checked="(action.config.sections ?? []).includes('ai_images')"
                                                />
                                                @lang('AI Images')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="marketing_contacts"
                                                    :checked="(action.config.sections ?? []).includes('marketing_contacts')"
                                                />
                                                @lang('Marketing Contacts')
                                            </label>
                                        </div>
                                        <input
                                            :name="`actions[${index}][config][store_output_as]`"
                                            x-model="action.config.store_output_as"
                                            placeholder="{{ __('Store output as… (default: report_text)') }}"
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        />
                                    </div>
                                </div>

                                <x-button
                                    type="button"
                                    variant="ghost"
                                    size="xs"
                                    @click="removeAction(index)"
                                >
                                    <x-tabler-trash class="size-3.5 text-red-500" />
                                </x-button>
                            </div>
                        </div>
                    </template>

                    <p x-show="actions.length === 0" class="text-xs text-foreground/50">
                        @lang('No actions yet. Add one above.')
                    </p>
                </div>
            </x-card>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">@lang('Update Agent')</x-button>
                <x-button href="{{ route('dashboard.user.ai-agent.workflows.index') }}" variant="ghost">@lang('Cancel')</x-button>
            </div>

        </div>
    </form>

    @push('script')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('workflowBuilder', () => ({
                    triggerType: '{{ $workflow->trigger_type->value }}',
                    actions: @json(
                        collect($workflow->actions ?? [])->map(fn($a) => [
                            'type'   => $a['type'] ?? 'ai_call',
                            'config' => $a['config'] ?? (object)[],
                        ])->values()
                    ),

                    addAction(type = 'ai_call') {
                        const defaults = {
                            ai_call: { system_prompt: '', user_message: '{message_text}', store_output_as: 'ai_response' },
                            send_message: { channel_id: null, recipient_id: '{sender_id}', message: '' },
                            generate_report: { date_range: 'today', sections: ['messages', 'workflows'], store_output_as: 'report_text', system_prompt: "You are a concise business analyst. You will receive platform activity data as JSON.\nWrite a short, friendly summary for the owner:\n- Start with one sentence summarising the period overall.\n- Highlight only metrics with non-zero values.\n- Skip any metric that is 0 or empty.\n- Use plain text. No markdown headings. Bullet points are fine for key metrics.\n- Keep it under 300 words." },
                        };
                        this.actions.push({ type, config: { ...(defaults[type] ?? {}) } });
                    },

                    removeAction(index) {
                        this.actions.splice(index, 1);
                    },
                }));
            });
        </script>
    @endpush
@endsection
