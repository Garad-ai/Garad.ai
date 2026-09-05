<x-modal
    class:modal-content="p-5 md:px-8 md:pb-8 md:pt-5 !max-h-none h-[min(670px,calc(100vh-4rem))] !min-w-0 w-[min(940px,100%)]"
    class:modal-head="px-0"
    class:modal-title="text-[12px] font-semibold"
    class:modal-body="p-0"
    class:close-btn="size-7"
    class:close-btn-icon="size-4"
    id="ai-agent-logs-modal"
    title="{{ __('Logs') }}"
>
    <x-slot:modal>
        <div
            x-data="workflowLogs"
            @open-logs.window="openLogs($event.detail)"
        >
            {{-- Body --}}
            <div class="min-h-0 flex-1 overflow-y-auto">
                {{-- Loading --}}
                <div
                    class="flex h-full items-center justify-center p-10"
                    x-show="loading"
                >
                    <x-tabler-loader-2 class="size-6 animate-spin text-foreground/30" />
                </div>

                {{-- Empty --}}
                <x-empty-state
                    class="p-10"
                    icon="tabler-history-off"
                    title="{{ __('No runs yet') }}"
                    description="{{ __('This workflow has not been executed.') }}"
                    x-show="!loading && runs.length === 0"
                />

                {{-- Run list --}}
                <div
                    class="divide-y divide-border"
                    x-show="!loading && runs.length > 0"
                >
                    <template
                        x-for="run in runs"
                        :key="run.id"
                    >
                        <div
                            class="px-5 py-3"
                            x-data="{ expanded: false }"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="inline-flex size-2 shrink-0 rounded-full"
                                        :class="{
                                            'bg-green-500': run.status === 'completed',
                                            'bg-red-500': run.status === 'failed',
                                            'bg-yellow-500': run.status === 'running',
                                        }"
                                    ></span>
                                    <span
                                        class="text-xs font-medium capitalize text-heading-foreground"
                                        x-text="run.status"
                                    ></span>
                                    <span
                                        class="text-[10px] text-foreground/40"
                                        x-text="run.started_at ? new Date(run.started_at).toLocaleString() : '—'"
                                    ></span>
                                    <template x-if="run.duration_ms !== null">
                                        <span
                                            class="text-[10px] text-foreground/30"
                                            x-text="run.duration_ms >= 1000 ? (run.duration_ms / 1000).toFixed(1) + 's' : run.duration_ms + 'ms'"
                                        ></span>
                                    </template>
                                </div>
                                <button
                                    class="flex items-center gap-1 text-[10px] text-foreground/40 transition-colors hover:text-foreground"
                                    type="button"
                                    @click="expanded = !expanded"
                                    x-text="expanded ? '{{ __('Hide') }}' : '{{ __('Details') }}'"
                                ></button>
                            </div>

                            {{-- Error message --}}
                            <template x-if="run.error_message">
                                <p
                                    class="mt-1.5 rounded-lg bg-red-500/5 px-3 py-2 text-[11px] text-red-600"
                                    x-text="run.error_message"
                                ></p>
                            </template>

                            {{-- Steps output --}}
                            <template x-if="expanded && run.steps_output && run.steps_output.length > 0">
                                <div class="mt-2 space-y-1.5">
                                    <template
                                        x-for="(step, idx) in run.steps_output"
                                        :key="idx"
                                    >
                                        <div class="rounded-lg border border-border bg-foreground/[1.5%] px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="inline-flex size-1.5 shrink-0 rounded-full"
                                                    :class="{
                                                        'bg-green-500': step.status === 'completed' || step.status === 'success',
                                                        'bg-red-500': step.status === 'failed' || step.status === 'error',
                                                        'bg-foreground/30': !step.status || (step.status !== 'completed' && step.status !== 'success' && step
                                                            .status !== 'failed' && step.status !== 'error'),
                                                    }"
                                                ></span>
                                                <p
                                                    class="text-[11px] font-medium text-heading-foreground"
                                                    x-text="step.step_id || step.action || ('Step ' + (idx + 1))"
                                                ></p>
                                            </div>
                                            <template x-if="step.output || step.error">
                                                <pre
                                                    class="mt-1.5 overflow-x-auto whitespace-pre-wrap break-all text-[10px] text-foreground/60"
                                                    x-text="step.error || (typeof step.output === 'string' ? step.output : JSON.stringify(step.output, null, 2))"
                                                ></pre>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </x-slot:modal>
</x-modal>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('workflowLogs', () => ({
                loading: false,
                workflowName: '',
                runs: [],

                getModalData() {
                    return Alpine.$data(document.querySelector('#ai-agent-logs-modal'));
                },

                async openLogs({
                    id,
                    name,
                    url
                }) {
                    this.getModalData().modalOpen = true;

                    this.workflowName = name;
                    this.runs = [];
                    this.loading = true;
                    try {
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const json = await res.json();
                        this.runs = json.data ?? [];
                    } finally {
                        this.loading = false;
                    }
                },
            }));
        });
    </script>
@endPushOnce
