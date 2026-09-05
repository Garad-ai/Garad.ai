<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full px-4 py-6"
    x-show="contactInfo.activeTab === 'logs'"
    x-transition.opacity
    x-data="{ showAllRuns: false, showAllMessages: false }"
>
    <div
        class="flex items-center justify-center gap-2 px-4 py-8 text-xs font-medium text-foreground/60"
        x-show="logsLoading"
        x-transition.opacity
    >
        <x-tabler-refresh class="size-4 animate-spin" />
        {{ __('Loading') }}
    </div>

    <div
        x-show="!logsLoading"
        x-transition.opacity
    >
        {{-- Workflow runs --}}
        <p class="mb-3 px-1 text-2xs font-semibold uppercase tracking-wider text-foreground/40">{{ __('Workflow Runs') }}</p>

        <template x-if="workflowRuns.length === 0">
            <p class="mb-6 px-1 text-xs text-foreground/50">{{ __('No runs found.') }}</p>
        </template>

        <template x-if="workflowRuns.length > 0">
            <div class="mb-6">
                <ul class="flex flex-col gap-1.5">
                    <template x-for="(run, index) in (showAllRuns ? workflowRuns : workflowRuns.slice(0, 5))" :key="run.id">
                        <li
                            class="rounded-lg border px-3 py-2.5"
                            x-data="{ expanded: false }"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :class="{
                                        'bg-green-500': run.status === 'completed',
                                        'bg-red-500': run.status === 'failed',
                                        'bg-yellow-400': run.status === 'running',
                                        'bg-foreground/20': !['completed','failed','running'].includes(run.status),
                                    }"
                                ></span>
                                <span
                                    class="grow truncate text-xs font-medium capitalize"
                                    x-text="run.status"
                                ></span>
                                <span
                                    class="shrink-0 text-2xs text-foreground/50"
                                    x-text="run.duration_ms ? (run.duration_ms / 1000).toFixed(1) + 's' : ''"
                                ></span>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <p
                                    class="mb-0 text-2xs text-foreground/50"
                                    x-text="run.started_at ? new Date(run.started_at).toLocaleString('sv') : ''"
                                ></p>
                                <button
                                    class="shrink-0 text-2xs font-medium text-foreground/40 underline underline-offset-2 transition hover:text-foreground/80"
                                    type="button"
                                    @click="expanded = !expanded"
                                    x-text="expanded ? '{{ __('Hide') }}' : '{{ __('Details') }}'"
                                ></button>
                            </div>
                            <template x-if="run.error_message">
                                <p
                                    class="mb-0 mt-1 truncate text-2xs text-red-500"
                                    x-text="run.error_message"
                                    :title="run.error_message"
                                ></p>
                            </template>
                            <div
                                x-show="expanded"
                                x-collapse
                                class="mt-2 space-y-1.5"
                            >
                                <template x-if="run.steps_output && run.steps_output.length > 0">
                                    <div class="space-y-1.5">
                                        <template x-for="(step, si) in run.steps_output" :key="si">
                                            <div class="rounded bg-foreground/5 px-2.5 py-2">
                                                <p
                                                    class="mb-1 text-2xs font-semibold"
                                                    x-text="step.label || step.action || `Step ${si + 1}`"
                                                ></p>
                                                <p
                                                    class="mb-0 break-words text-2xs text-foreground/60"
                                                    x-text="typeof step.output === 'string' ? step.output : JSON.stringify(step.output)"
                                                ></p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!run.steps_output || run.steps_output.length === 0">
                                    <p class="mb-0 text-2xs text-foreground/40">{{ __('No step details available.') }}</p>
                                </template>
                            </div>
                        </li>
                    </template>
                </ul>
                <template x-if="workflowRuns.length > 5">
                    <button
                        class="mt-2 w-full rounded-lg border border-dashed py-2 text-2xs font-medium text-foreground/50 transition hover:border-foreground/30 hover:text-foreground/80"
                        type="button"
                        @click="showAllRuns = !showAllRuns"
                        x-text="showAllRuns ? '{{ __('Show less') }}' : `{{ __('Show') }} ${workflowRuns.length - 5} {{ __('more') }}`"
                    ></button>
                </template>
            </div>
        </template>

        {{-- Message history --}}
        <p class="mb-3 px-1 text-2xs font-semibold uppercase tracking-wider text-foreground/40">{{ __('Messages') }}</p>

        <template x-if="thread.length === 0">
            <p class="mb-0 px-1 text-xs text-foreground/50">{{ __('No messages yet.') }}</p>
        </template>

        <template x-if="thread.length > 0">
            <div>
                <ul class="flex flex-col gap-1.5">
                    <template x-for="msg in (showAllMessages ? thread : thread.slice(0, 5))" :key="msg.id">
                        <li class="rounded-lg border px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span
                                    class="shrink-0 rounded px-1.5 py-0.5 text-2xs font-medium"
                                    :class="msg.direction === 'inbound' ? 'bg-primary/10 text-primary' : 'bg-foreground/10 text-foreground/60'"
                                    x-text="msg.direction === 'inbound' ? '{{ __('In') }}' : '{{ __('Out') }}'"
                                ></span>
                                <span
                                    class="shrink-0 text-2xs text-foreground/40"
                                    x-text="msg.created_at ? new Date(msg.created_at).toLocaleString('sv') : ''"
                                ></span>
                            </div>
                            <p
                                class="mb-0 mt-1 line-clamp-2 text-xs"
                                x-text="msg.content"
                            ></p>
                        </li>
                    </template>
                </ul>
                <template x-if="thread.length > 5">
                    <button
                        class="mt-2 w-full rounded-lg border border-dashed py-2 text-2xs font-medium text-foreground/50 transition hover:border-foreground/30 hover:text-foreground/80"
                        type="button"
                        @click="showAllMessages = !showAllMessages"
                        x-text="showAllMessages ? '{{ __('Show less') }}' : `{{ __('Show') }} ${thread.length - 5} {{ __('more') }}`"
                    ></button>
                </template>
            </div>
        </template>
    </div>
</div>
