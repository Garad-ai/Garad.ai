<div class="w-56 rounded-xl border border-border bg-background shadow-md transition-shadow hover:shadow-lg sm:w-72">
    <div class="flex items-center gap-2 rounded-t-xl border-b border-border px-4 py-3">
        <div class="grid size-8 place-items-center rounded-lg bg-foreground/5 text-foreground/70">
            <x-tabler-clock class="size-4" />
        </div>
        <span class="text-sm font-semibold text-heading-foreground">{{ __('When') }}</span>
    </div>

    <div class="p-4">
        <template x-if="!triggerConfigured">
            <div>
                <p class="mb-3 text-xs text-foreground/60">
                    {{ __('Define when this workflow should run.') }}
                </p>
                <div class="rounded-lg border border-dashed border-border px-3 py-2.5 text-center">
                    <button class="text-sm font-medium text-primary hover:underline" @click="openTriggerConfig()">
                        + {{ __('Configure Trigger') }}
                    </button>
                </div>
            </div>
        </template>

        <template x-if="triggerConfigured">
            <div>
                <div class="mb-2 flex items-center gap-2">
                    <template x-if="triggerType === 'schedule'">
                        <div class="grid size-6 place-items-center rounded bg-amber-500/10 text-amber-600">
                            <x-tabler-clock class="size-3.5" />
                        </div>
                    </template>
                    <template x-if="triggerType === 'channel_message'">
                        <div class="grid size-6 place-items-center rounded bg-primary/10 text-primary">
                            <x-tabler-message-circle class="size-3.5" />
                        </div>
                    </template>
                    <template x-if="triggerType === 'webhook'">
                        <div class="grid size-6 place-items-center rounded bg-green-500/10 text-green-600">
                            <x-tabler-webhook class="size-3.5" />
                        </div>
                    </template>
                    <p class="text-xs font-medium text-heading-foreground" x-text="triggerTypeLabel"></p>
                </div>
                <p class="font-mono text-xs text-foreground/60" x-text="triggerSummary"></p>

                {{-- Auto Tool Calling badge --}}
                <template x-if="triggerConfig.auto_tool_calling">
                    <div class="mt-3 flex flex-wrap items-center gap-1">
                        <span class="flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                            <x-tabler-cpu class="size-3" />
                            {{ __('Auto Tools') }}
                        </span>
                        <template x-for="toolKey in (triggerConfig.enabled_tools || [])" :key="toolKey">
                            <span class="rounded-full bg-foreground/5 px-2 py-0.5 text-[10px] text-foreground/50" x-text="toolKey.replace('_', ' ')"></span>
                        </template>
                    </div>
                </template>

                <button class="mt-3 text-xs font-medium text-primary hover:underline" @click="openTriggerConfig()">
                    {{ __('Edit Trigger') }}
                </button>
            </div>
        </template>
    </div>
</div>
