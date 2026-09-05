@extends('panel.layout.app', ['disable_tblr' => true, 'disable_navbar' => true])
@section('title', __('Create Agent'))
@section('titlebar_subtitle', __('Define a trigger and a sequence of actions.'))

@section('content')
    <form
        class="py-10"
        method="POST"
        action="{{ route('dashboard.user.ai-agent.workflows.store') }}"
        x-data="workflowBuilder()"
    >
        @csrf
        <div class="flex flex-col gap-6">

            {{-- Basic info --}}
            <x-card>
                <div class="flex flex-col gap-4 p-5">
                    <h3 class="text-sm font-semibold">@lang('Agent Details')</h3>

                    <x-forms.input
                        name="name"
                        :label="__('Name')"
                        :placeholder="__('e.g. Daily Morning Briefing')"
                        :value="old('name') ?? ''"
                        required
                    />

                    <x-forms.input
                        name="description"
                        :label="__('Description (optional)')"
                        :value="old('description') ?? ''"
                    />
                </div>
            </x-card>

            {{-- Trigger --}}
            <x-card>
                <div class="flex flex-col gap-4 p-5">
                    <h3 class="text-sm font-semibold">@lang('Trigger')</h3>

                    <x-forms.input
                        type="select"
                        name="trigger_type"
                        :label="__('Trigger Type')"
                        x-model="triggerType"
                        required
                    >
                        @foreach ($triggerTypes as $type)
                            <option
                                value="{{ $type->value }}"
                                {{ old('trigger_type') === $type->value ? 'selected' : '' }}
                            >
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </x-forms.input>

                    {{-- Schedule config --}}
                    <div
                        x-show="triggerType === 'schedule'"
                        x-cloak
                    >
                        <x-forms.input
                            name="trigger_config[cron]"
                            :label="__('Cron Expression')"
                            :placeholder="__('e.g. 0 9 * * * (every day at 9am)')"
                            :value="old('trigger_config.cron') ?? ''"
                        />
                        <p class="mt-1 text-2xs text-foreground/50">
                            @lang('Uses standard 5-field cron syntax. Times are UTC.')
                        </p>
                    </div>

                    {{-- Channel message config --}}
                    <div
                        class="flex flex-col gap-3"
                        x-show="triggerType === 'channel_message'"
                        x-cloak
                    >
                        <x-forms.input
                            type="select"
                            name="trigger_config[channel_id]"
                            :label="__('Channel')"
                        >
                            <option value="">@lang('Any channel')</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                            @endforeach
                        </x-forms.input>

                        <x-forms.input
                            name="trigger_config[keyword_pattern]"
                            :label="__('Keyword (optional)')"
                            :placeholder="__('e.g. /start — leave empty to match any message')"
                        />
                    </div>

                    {{-- Webhook info --}}
                    <div
                        x-show="triggerType === 'webhook'"
                        x-cloak
                    >
                        <p class="text-2xs text-foreground/60">
                            @lang('After saving, a unique webhook URL will be shown on the workflow edit page.')
                        </p>
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

                    <template
                        x-for="(action, index) in actions"
                        :key="index"
                    >
                        <div class="rounded-input border border-input-border bg-input-background p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-1 flex-col gap-3">
                                    <select
                                        class="rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                        :name="`actions[${index}][type]`"
                                        x-model="action.type"
                                    >
                                        <option value="ai_call">@lang('Ask Agent')</option>
                                        <option value="send_message">@lang('Send Message')</option>
                                        <option value="generate_report">@lang('Generate Report')</option>
                                    </select>

                                    {{-- AI Call fields --}}
                                    <div
                                        class="flex flex-col gap-2"
                                        x-show="action.type === 'ai_call'"
                                    >
                                        <textarea
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][system_prompt]`"
                                            placeholder="{{ __('System prompt…') }}"
                                            rows="3"
                                        ></textarea>
                                        <input
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][user_message]`"
                                            placeholder="{{ __('User message — use {message_text} for incoming text, {sender_id} for chat id') }}"
                                        />
                                        <input
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][store_output_as]`"
                                            placeholder="{{ __('Store output as… (default: ai_response)') }}"
                                        />
                                    </div>

                                    {{-- Send Message fields --}}
                                    <div
                                        class="flex flex-col gap-2"
                                        x-show="action.type === 'send_message'"
                                    >
                                        <select
                                            class="rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][channel_id]`"
                                        >
                                            @foreach ($channels as $channel)
                                                <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                                            @endforeach
                                        </select>
                                        <input
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][recipient_id]`"
                                            placeholder="{{ __('Recipient ID — Telegram chat_id, or {sender_id}') }}"
                                        />
                                        <textarea
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][message]`"
                                            placeholder="{{ __('Message text — use {ai_response} for AI output') }}"
                                            rows="3"
                                        ></textarea>
                                    </div>

                                    {{-- Generate Report fields --}}
                                    <div
                                        class="flex flex-col gap-2"
                                        x-show="action.type === 'generate_report'"
                                    >
                                        <select
                                            class="rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][date_range]`"
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
                                                    checked
                                                />
                                                @lang('Messages')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="workflows"
                                                    checked
                                                />
                                                @lang('Agents')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="chatbot"
                                                />
                                                @lang('Chatbot')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="marketing"
                                                />
                                                @lang('Marketing')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="blogpilot"
                                                />
                                                @lang('Blog Pilot')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="social_media"
                                                />
                                                @lang('Social Media')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="social_media_agent"
                                                />
                                                @lang('Social Media Agent')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="ai_usage"
                                                />
                                                @lang('AI Usage')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="creative_suite"
                                                />
                                                @lang('Creative Suite')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="ai_images"
                                                />
                                                @lang('AI Images')
                                            </label>
                                            <label class="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    :name="`actions[${index}][config][sections][]`"
                                                    value="marketing_contacts"
                                                />
                                                @lang('Marketing Contacts')
                                            </label>
                                        </div>
                                        <input
                                            class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-xs"
                                            :name="`actions[${index}][config][store_output_as]`"
                                            placeholder="{{ __('Store output as… (default: report_text)') }}"
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

                    <x-empty-state
                        icon="tabler-list-check"
                        :title="__('No actions yet')"
                        :description="__('Add at least one action for this agent to do something.')"
                        show="actions.length === 0"
                        x-cloak
                    />
                </div>
            </x-card>

            <div class="flex gap-3">
                <x-button
                    type="submit"
                    variant="primary"
                >@lang('Save Agent')</x-button>
                <x-button
                    href="{{ route('dashboard.user.ai-agent.workflows.index') }}"
                    variant="ghost"
                >@lang('Cancel')</x-button>
            </div>

        </div>
    </form>

    @push('script')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('workflowBuilder', () => ({
                    triggerType: '{{ old('trigger_type', 'schedule') }}',
                    actions: [],

                    addAction(type = 'ai_call') {
                        const defaults = {
                            ai_call: {
                                system_prompt: '',
                                user_message: '{message_text}',
                                store_output_as: 'ai_response'
                            },
                            send_message: {
                                channel_id: null,
                                recipient_id: '{sender_id}',
                                message: ''
                            },
                            generate_report: {
                                date_range: 'today',
                                sections: ['messages', 'workflows'],
                                store_output_as: 'report_text',
                                system_prompt: "You are a concise business analyst. You will receive platform activity data as JSON.\nWrite a short, friendly summary for the owner:\n- Start with one sentence summarising the period overall.\n- Highlight only metrics with non-zero values.\n- Skip any metric that is 0 or empty.\n- Use plain text. No markdown headings. Bullet points are fine for key metrics.\n- Keep it under 300 words."
                            },
                        };
                        this.actions.push({
                            type,
                            config: {
                                ...(defaults[type] ?? {})
                            }
                        });
                    },

                    removeAction(index) {
                        this.actions.splice(index, 1);
                    },
                }));
            });
        </script>
    @endpush
@endsection
