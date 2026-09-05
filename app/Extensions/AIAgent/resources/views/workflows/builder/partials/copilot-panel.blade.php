{{-- Copilot Panel (Left Rail) --}}
<div
    class="group/copilot relative z-30 flex shrink-0 flex-col border-border bg-background transition-all duration-200 max-lg:absolute max-lg:inset-x-0 max-lg:bottom-0 max-lg:h-[calc(100vh-var(--header-h)-6rem)] max-lg:translate-y-full max-lg:border-t max-lg:shadow-2xl max-lg:shadow-black/5 lg:w-10 lg:border-e max-lg:[&.is-open]:translate-y-0 lg:[&.is-open]:w-[--sidebar-w]"
    :class="{ 'is-open': copilotOpen }"
>
    {{-- Collapse toggle --}}
    <x-button
        class="absolute z-10 grid size-[30px] place-items-center bg-background p-0 max-lg:-top-4 max-lg:end-4 max-lg:hidden max-lg:group-[&.is-open]/copilot:inline-flex lg:-end-3.5 lg:top-5"
        hover-variant="primary"
        variant="outline"
        type="button"
        @click="copilotOpen = !copilotOpen"
        ::title="copilotOpen ? '{{ __('Hide Copilot') }}' : '{{ __('Show Copilot') }}'"
    >
        <x-tabler-chevron-left
            class="size-4 transition-transform max-lg:hidden"
            ::class="copilotOpen ? '' : 'rotate-180'"
        />
        <x-tabler-x class="size-4 lg:hidden" />
    </x-button>

    {{-- Collapsed label --}}
    <div
        class="absolute inset-0 flex flex-col items-center justify-center gap-2"
        x-show="!copilotOpen"
        x-transition:enter="transition-opacity delay-100 duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-cloak
    >
        <x-tabler-message-bolt class="size-4" />
        <span class="text-2xs font-medium [writing-mode:vertical-rl]">
            {{ __('Copilot') }}
        </span>
    </div>

    {{-- Panel content --}}
    <div
        class="flex min-h-0 flex-1 flex-col"
        x-show="copilotOpen"
        x-transition:enter="transition-opacity duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-cloak
    >
        {{-- Messages --}}
        <div
            class="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto p-5"
            id="copilot-messages"
            x-ref="copilotMessages"
            x-init="$nextTick(() => $refs.copilotMessages.scrollTop = $refs.copilotMessages.scrollHeight)"
        >
            {{-- Empty state --}}
            <template x-if="copilotMessages.length === 0">
                <div class="flex h-full flex-col items-start justify-center gap-3.5 lg:px-11">
                    <svg
                        width="17"
                        height="16"
                        viewBox="0 0 17 16"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M8.51931 16C8.25155 16 8.02439 15.8086 7.95484 15.55C7.49539 13.8419 6.16424 11.8096 3.80729 10.1458C2.68932 9.35206 1.55397 8.80364 0.432039 8.51506C0.182551 8.45089 0 8.23105 0 7.97344C0 7.72102 0.175341 7.50379 0.418994 7.43782C2.77329 6.80035 5.0462 5.27586 6.52995 3.25C7.21868 2.31369 7.69245 1.39294 7.95127 0.447396C8.02188 0.189443 8.24975 0 8.51719 0C8.78905 0 9.01922 0.195407 9.0891 0.458127C9.61536 2.43648 11.2589 4.55011 13.4362 6C14.4533 6.68115 15.505 7.16709 16.576 7.44344C16.8214 7.50676 17 7.72355 17 7.97695C17 8.23234 16.8187 8.45001 16.5717 8.51495C14.2187 9.13362 11.6059 11.0796 10.2708 13.0625C9.68099 13.9473 9.29069 14.7742 9.08714 15.5434C9.018 15.8047 8.78958 16 8.51931 16Z"
                        />
                    </svg>

                    <p class="m-0 text-lg font-medium leading-[1.1em]">
                        <span class="block">
                            <span class="opacity-50">
                                {{ __('Hello :username', ['username' => $username]) }}
                            </span>
                            👋
                        </span>
                        {{ __('Describe your agent') }}
                    </p>

                    <p class="m-0 text-pretty text-2xs opacity-85">
                        {{ __('Describe a workflow and I\'ll build it for you, or ask me to modify existing steps.') }}
                    </p>

                    <x-button
                        variant="outline"
                        @click="copilotInput = examplePrompt; $nextTick(() => $refs.copilotInput?.focus())"
                        type="button"
                    >
                        {{ __('Try an example') }}
                    </x-button>
                </div>
            </template>

            {{-- Message list --}}
            <template
                x-for="msg in copilotMessages"
                :key="msg.id"
            >
                <div
                    class="flex flex-col gap-1"
                    :class="msg.role === 'user' ? 'items-end' : 'items-start'"
                    x-show="msg.role === 'user' || (msg.steps && msg.steps.length > 0) || msg.content"
                >
                    {{-- User bubble --}}
                    <template x-if="msg.role === 'user'">
                        <div
                            class="max-w-[85%] rounded-lg bg-secondary p-4 text-secondary-foreground dark:bg-foreground/5"
                            x-init="$nextTick(() => $refs.copilotMessages.scrollTop = $refs.copilotMessages.scrollHeight)"
                        >
                            <div
                                class="prose prose-sm whitespace-pre-wrap text-2xs leading-[1.4em] dark:prose-invert"
                                x-text="msg.content"
                            ></div>
                        </div>
                    </template>

                    {{-- Assistant: activity log (new streaming messages with steps) --}}
                    <template x-if="msg.role === 'assistant' && msg.steps && msg.steps.length > 0">
                        <div class="prose prose-sm w-full rounded-lg border p-4 text-2xs leading-[1.4em] dark:prose-invert">
                            <p class="mb-3 flex items-center gap-2 text-sm font-semibold">
                                {{-- blade-formatter-disable --}}
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.77319 11.52C5.59214 11.52 5.43882 11.3897 5.39508 11.214C5.08801 9.98045 4.18496 8.50872 2.58 7.305C1.8282 6.73785 1.0648 6.34467 0.310202 6.13564C0.130827 6.08595 0 5.92676 0 5.74063C0 5.55849 0.125318 5.40133 0.300231 5.35052C1.8898 4.88877 3.42297 3.79365 4.425 2.34C4.89576 1.66002 5.21832 0.991432 5.39268 0.304434C5.43717 0.129129 5.59098 0 5.77184 0C5.95551 0 6.11075 0.132976 6.15461 0.311327C6.50617 1.74106 7.62319 3.27155 9.105 4.32C9.78895 4.80665 10.496 5.15491 11.2159 5.35466C11.3922 5.40356 11.52 5.56041 11.52 5.74331C11.52 5.92774 11.39 6.08531 11.2126 6.13561C9.62257 6.58627 7.86144 7.98241 6.96 9.405C6.55586 10.0491 6.28996 10.6507 6.15333 11.2097C6.10997 11.3871 5.95584 11.52 5.77319 11.52Z" fill="url(#paint0_linear_16239_1451)"/> <defs> <linearGradient id="paint0_linear_16239_1451" x1="0.32" y1="4.32" x2="11.52" y2="4.32" gradientUnits="userSpaceOnUse"> <stop stop-color="#8364FF"/> <stop offset="0.469702" stop-color="#4FACBE"/> <stop offset="1" stop-color="#CA99E7"/> </linearGradient> </defs></svg>
								{{-- blade-formatter-enable --}}
                                {{ __('Agent Builder') }}
                            </p>

                            <template
                                x-for="(step, si) in msg.steps"
                                :key="si"
                            >
                                <div x-init="$nextTick(() => $refs.copilotMessages.scrollTop = $refs.copilotMessages.scrollHeight)">
                                    {{-- Thinking step --}}
                                    <template x-if="step.type === 'thinking'">
                                        <div class="my-2">
                                            <template x-if="step.status === 'pending'">
                                                <x-shimmer-text>
                                                    {{ __('Thinking...') }}
                                                </x-shimmer-text>
                                            </template>
                                            <template x-if="step.status === 'done'">
                                                <div>
                                                    <button
                                                        class="flex w-full items-center gap-1.5 text-start text-foreground/65 transition hover:text-foreground"
                                                        type="button"
                                                        :disabled="!step.content"
                                                        ::class="step.content ? 'cursor-pointer' : 'cursor-default'"
                                                        @click="step.content && (msg.steps[si] = { ...step, expanded: !step.expanded })"
                                                    >
                                                        <x-tabler-chevron-right
                                                            class="size-3 shrink-0 transition rtl:rotate-180"
                                                            ::class="{ 'rotate-90 rtl:rotate-90': step.expanded }"
                                                            x-show="step.content"
                                                        />
                                                        <span
                                                            x-text="step.durationMs !== null && step.durationMs !== undefined ? `{{ __('Thought for') }} ${formatThinkingDuration(step.durationMs)}` : `{{ __('Thinking') }}`"
                                                        ></span>
                                                    </button>

                                                    <template x-if="step.expanded && step.content">
                                                        <div class="mt-1.5 border-s border-border ps-3 text-foreground/60">
                                                            <div
                                                                class="prose prose-sm whitespace-pre-wrap text-[11px] leading-[1.4em] dark:prose-invert"
                                                                x-text="step.content"
                                                            ></div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- Text step --}}
                                    <template x-if="step.type === 'text'">
                                        <div x-html="renderMarkdown(step.content)"></div>
                                    </template>

                                    {{-- Tool call step --}}
                                    <template x-if="step.type === 'tool_call'">
                                        <x-card
                                            class="mb-2 last:mb-0"
                                            size="none"
                                        >
                                            <button
                                                class="flex w-full items-center gap-2 px-2.5 py-2 text-start"
                                                type="button"
                                                @click="msg.steps[si] = { ...step, expanded: !step.expanded }; $nextTick(() => {})"
                                            >
                                                <x-tabler-tool class="size-4 shrink-0" />
                                                <span
                                                    class="min-w-0 flex-1 truncate font-medium"
                                                    x-text="toolCallLabel(step)"
                                                ></span>
                                                {{-- Status badge --}}
                                                <template x-if="step.status === 'pending'">
                                                    <span class="size-3 animate-spin rounded-full border border-foreground/30 border-t-foreground/60"></span>
                                                </template>
                                                <template x-if="step.status === 'success'">
                                                    <span class="flex size-4 items-center justify-center rounded-full bg-green-500/15 text-green-600 dark:text-green-400">
                                                        <x-tabler-check class="size-3" />
                                                    </span>
                                                </template>
                                                <template x-if="step.status === 'error'">
                                                    <span class="flex size-4 items-center justify-center rounded-full bg-red-500/15 text-red-600 dark:text-red-400">
                                                        <x-tabler-x class="size-3" />
                                                    </span>
                                                </template>
                                                <x-tabler-chevron-down
                                                    class="size-3 shrink-0 transition"
                                                    ::class="step.expanded ? 'rotate-180' : ''"
                                                />
                                            </button>
                                            <template x-if="step.expanded">
                                                <div class="border-t border-border px-2.5 py-2">
                                                    <div>
                                                        @include('ai-agent::workflows.builder.partials.render-arg-value', ['expr' => 'step.args'])
                                                    </div>
                                                </div>
                                            </template>
                                        </x-card>
                                    </template>

                                    {{-- Action buttons step --}}
                                    <template x-if="step.type === 'action_buttons'">
                                        <div class="flex flex-col gap-1.5 pt-0.5">
                                            <template
                                                x-for="(btn, bi) in step.buttons"
                                                :key="bi"
                                            >
                                                <button
                                                    class="flex w-full items-center gap-3 rounded-xl border border-border bg-background p-3 text-start shadow-sm transition-colors hover:bg-foreground/5"
                                                    type="button"
                                                    @click="handleCopilotAction(btn.action, btn.args)"
                                                >
                                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                        <template x-if="btn.icon === 'channel'"><x-tabler-message-circle class="size-4" /></template>
                                                        <template x-if="btn.icon === 'bolt'"><x-tabler-bolt class="size-4" /></template>
                                                        <template x-if="btn.icon === 'settings'"><x-tabler-settings-2 class="size-4" /></template>
                                                        <template x-if="btn.icon === 'play'"><x-tabler-player-play class="size-4" /></template>
                                                        <template x-if="btn.icon === 'adjustments'"><x-tabler-adjustments class="size-4" /></template>
                                                        <template x-if="!btn.icon || !['channel','bolt','settings','play','adjustments'].includes(btn.icon)">
                                                            <x-tabler-arrow-right class="size-4" />
                                                        </template>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p
                                                            class="m-0 text-[12px] font-semibold text-heading-foreground"
                                                            x-text="btn.label"
                                                        ></p>
                                                        <p
                                                            class="m-0 text-3xs opacity-65"
                                                            x-show="btn.description"
                                                            x-text="(btn.description ?? '') + ' →'"
                                                        ></p>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Assistant: legacy bubble (messages loaded from DB without steps) --}}
                    <template x-if="msg.role === 'assistant' && (!msg.steps || msg.steps.length === 0) && msg.content">
                        <div class="prose prose-sm w-full rounded-lg border p-4 text-2xs leading-[1.4em] dark:prose-invert">
                            <p class="mb-3 flex items-center gap-2 text-sm font-semibold">
                                {{-- blade-formatter-disable --}}
								<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.77319 11.52C5.59214 11.52 5.43882 11.3897 5.39508 11.214C5.08801 9.98045 4.18496 8.50872 2.58 7.305C1.8282 6.73785 1.0648 6.34467 0.310202 6.13564C0.130827 6.08595 0 5.92676 0 5.74063C0 5.55849 0.125318 5.40133 0.300231 5.35052C1.8898 4.88877 3.42297 3.79365 4.425 2.34C4.89576 1.66002 5.21832 0.991432 5.39268 0.304434C5.43717 0.129129 5.59098 0 5.77184 0C5.95551 0 6.11075 0.132976 6.15461 0.311327C6.50617 1.74106 7.62319 3.27155 9.105 4.32C9.78895 4.80665 10.496 5.15491 11.2159 5.35466C11.3922 5.40356 11.52 5.56041 11.52 5.74331C11.52 5.92774 11.39 6.08531 11.2126 6.13561C9.62257 6.58627 7.86144 7.98241 6.96 9.405C6.55586 10.0491 6.28996 10.6507 6.15333 11.2097C6.10997 11.3871 5.95584 11.52 5.77319 11.52Z" fill="url(#paint0_linear_16239_1451)"/> <defs> <linearGradient id="paint0_linear_16239_1451" x1="0.32" y1="4.32" x2="11.52" y2="4.32" gradientUnits="userSpaceOnUse"> <stop stop-color="#8364FF"/> <stop offset="0.469702" stop-color="#4FACBE"/> <stop offset="1" stop-color="#CA99E7"/> </linearGradient> </defs></svg>
								{{-- blade-formatter-enable --}}
                                {{ __('Agent Builder') }}
                            </p>

                            <div
                                class="m-0"
                                x-html="renderMarkdown(msg.content)"
                            ></div>

                            <template x-if="msg.toolCalls && msg.toolCalls.length > 0">
                                <div class="mt-2 space-y-1 border-t border-foreground/10 pt-2">
                                    <template
                                        x-for="(tc, ti) in msg.toolCalls"
                                        :key="ti"
                                    >
                                        <div class="flex items-center gap-1.5 text-[10px] text-foreground/60">
                                            <x-tabler-tool class="size-3 shrink-0" />
                                            <span x-text="toolCallLabel(tc)"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Loading indicator --}}
            <template x-if="copilotLoading">
                <div class="flex items-start">
                    <div class="flex items-center gap-1 rounded-xl bg-foreground/5 px-3 py-3">
                        <span class="size-1 animate-bounce-load-more rounded-full bg-foreground/30 [animation-delay:-0.3s]"></span>
                        <span class="size-1 animate-bounce-load-more rounded-full bg-foreground/30 [animation-delay:-0.15s]"></span>
                        <span class="size-1 animate-bounce-load-more rounded-full bg-foreground/30"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Input area --}}
        <div class="shrink-0 p-4">
            <div class="rounded-[22px] border border-transparent shadow-xl shadow-black/5 dark:border-foreground/5">
                <x-forms.input
                    class="rounded-none border-none bg-transparent p-5 !outline-none !ring-0"
                    type="textarea"
                    rows="3"
                    x-ref="copilotInput"
                    x-model="copilotInput"
                    placeholder="{{ __('Ask anything') }}"
                    @keydown.meta.enter.prevent="sendCopilotMessage()"
                    @keydown.ctrl.enter.prevent="sendCopilotMessage()"
                />
                <div class="flex items-center justify-between gap-1 px-5 pb-4 pt-1">
                    <div>

                    </div>

                    <x-button
                        class="size-10 shrink-0 bg-heading-foreground p-0 text-header-background"
                        variant="primary"
                        type="button"
                        @click="sendCopilotMessage"
                        ::disabled="copilotLoading || !copilotInput.trim()"
                    >
                        <svg
                            width="15"
                            height="12"
                            viewBox="0 0 15 12"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M0 12V7.5L6 6L0 4.5V0L14.25 6L0 12Z" />
                        </svg>
                        <span class="sr-only">
                            {{ __('Send') }}
                        </span>
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</div>
