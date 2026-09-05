<div>
    <p class="mb-4 text-sm text-foreground/60">{{ __('Build the sequence of actions to run when this workflow fires.') }}</p>

    {{-- Action Toolbar --}}
    <div class="mb-6 flex flex-wrap gap-2">
        <x-button variant="ghost-shadow" size="sm" @click="addAction('ai_call')">
            <x-tabler-plus class="size-3.5" />{{ __('Ask Agent') }}
        </x-button>
        <x-button variant="ghost-shadow" size="sm" @click="addAction('send_message')">
            <x-tabler-plus class="size-3.5" />{{ __('Send Message') }}
        </x-button>
        <x-button variant="ghost-shadow" size="sm" @click="addAction('generate_report')">
            <x-tabler-plus class="size-3.5" />{{ __('Generate Report') }}
        </x-button>
    </div>

    {{-- Actions List --}}
    <div class="space-y-4">
        <template x-for="(action, index) in actions" :key="'action-cfg-' + index">
            <div class="rounded-xl border border-border bg-background p-4">
                {{-- Action Header --}}
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="grid size-6 place-items-center rounded bg-foreground/5 text-[10px] font-medium text-foreground/60" x-text="index + 1"></span>
                        <span class="text-xs font-semibold text-heading-foreground" x-text="actionTypeLabel(action.type)"></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button class="grid size-6 place-items-center rounded text-foreground/40 hover:bg-foreground/5 hover:text-foreground disabled:opacity-30" @click="moveAction(index, -1)" :disabled="index === 0" type="button">
                            <x-tabler-arrow-up class="size-3.5" />
                        </button>
                        <button class="grid size-6 place-items-center rounded text-foreground/40 hover:bg-foreground/5 hover:text-foreground disabled:opacity-30" @click="moveAction(index, 1)" :disabled="index === actions.length - 1" type="button">
                            <x-tabler-arrow-down class="size-3.5" />
                        </button>
                        <button class="grid size-6 place-items-center rounded text-foreground/40 hover:bg-red-50 hover:text-red-500" @click="removeAction(index)" type="button">
                            <x-tabler-trash class="size-3.5" />
                        </button>
                    </div>
                </div>

                {{-- AI Call Fields --}}
                <template x-if="action.type === 'ai_call'">
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('Agent Role') }}</label>
                            <textarea
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                rows="3"
                                x-model="action.config.system_prompt"
                                placeholder="{{ __('You are a helpful assistant...') }}"
                            ></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('User Message') }}</label>
                            <input
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                type="text"
                                x-model="action.config.user_message"
                                placeholder="{{ __('Use {message_text} for incoming text') }}"
                            >
                        </div>
                    </div>
                </template>

                {{-- Send Message Fields --}}
                <template x-if="action.type === 'send_message'">
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('Channel') }}</label>
                            <select
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                @change="action.config.channel_id = $event.target.value; actions = [...actions]"
                            >
                                <option value="">{{ __('Select channel...') }}</option>
                                <template x-for="ch in channels" :key="'ch-' + ch.id">
                                    <option :value="ch.id" :selected="String(action.config.channel_id) === String(ch.id)" x-text="ch.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('Recipient ID') }}</label>
                            <input
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                type="text"
                                x-model="action.config.recipient_id"
                                placeholder="{{ __('Telegram chat_id, or {sender_id}') }}"
                            >
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('Message') }}</label>
                            <textarea
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                rows="3"
                                x-model="action.config.message"
                                placeholder="{{ __('Use {ai_response} or {report_text} for dynamic content') }}"
                            ></textarea>
                        </div>
                    </div>
                </template>

                {{-- Generate Report Fields --}}
                <template x-if="action.type === 'generate_report'">
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('Date Range') }}</label>
                            <select
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                x-model="action.config.date_range"
                            >
                                <option value="today">{{ __('Today') }}</option>
                                <option value="yesterday">{{ __('Yesterday') }}</option>
                                <option value="this_week">{{ __('This Week') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-[11px] font-medium text-label">{{ __('Sections') }}</label>
                            <div class="grid grid-cols-1 gap-1.5">
                                @foreach([
                                    ['messages',           __('Inbox Messages'),     __('Inbox messages received and sent')],
                                    ['workflows',          __('Agents'),             __('Automated agent executions and triggers')],
                                    ['chatbot',            __('Chatbot'),            __('AI chatbot conversations and sessions')],
                                    ['marketing',          __('Marketing'),          __('Email campaigns and marketing activity')],
                                    ['blogpilot',          __('Blog Pilot'),         __('Blog post generation and publishing')],
                                    ['social_media',       __('Social Media'),       __('Social media posts and scheduling')],
                                    ['social_media_agent', __('Social Media Agent'), __('Social media agent automation activity')],
                                    ['ai_usage',           __('AI Usage'),           __('Token consumption and model usage stats')],
                                    ['creative_suite',     __('Creative Suite'),     __('Creative content generation activity')],
                                    ['ai_images',          __('AI Images'),          __('Image generation requests and results')],
                                    ['marketing_contacts', __('Marketing Contacts'), __('Marketing contact list changes and growth')],
                                ] as [$val, $lbl, $desc])
                                <label
                                    class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2.5 text-xs transition-colors hover:bg-foreground/5 [&.active]:border-primary/30 [&.active]:bg-primary/5"
                                    :class="{ 'active': (action.config.sections || []).includes('{{ $val }}') }"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 size-3.5 shrink-0 rounded"
                                        :checked="(action.config.sections || []).includes('{{ $val }}')"
                                        @change="toggleSection(action, '{{ $val }}')"
                                    >
                                    <div>
                                        <span class="font-medium text-heading-foreground">{{ $lbl }}</span>
                                        <p class="m-0 text-[10px] leading-relaxed text-foreground/50">{{ $desc }}</p>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] font-medium text-label">{{ __('Agent Role') }}</label>
                            <textarea
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm"
                                rows="4"
                                x-model="action.config.system_prompt"
                                placeholder="{{ __('System prompt for the AI summarisation...') }}"
                            ></textarea>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="actions.length === 0">
            <div class="rounded-xl border-2 border-dashed border-border py-12 text-center text-sm text-foreground/40">
                {{ __('Use the toolbar above to add actions') }}
            </div>
        </template>
    </div>

    <div class="mt-8">
        <button
            class="w-full rounded-xl px-6 py-3 text-sm font-semibold text-white shadow-lg transition-all hover:opacity-90 hover:shadow-xl"
            style="background: linear-gradient(135deg, hsl(var(--primary)) 0%, hsl(var(--secondary)) 100%);"
            @click="closePanel()"
        >
            {{ __('Done') }}
        </button>
    </div>
</div>
