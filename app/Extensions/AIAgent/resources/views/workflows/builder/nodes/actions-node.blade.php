<div class="w-56 rounded-xl border border-border bg-background shadow-md transition-shadow hover:shadow-lg sm:w-72">
    <div class="flex items-center gap-2 rounded-t-xl border-b border-border px-4 py-3">
        <div class="grid size-8 place-items-center rounded-lg bg-primary/10 text-primary">
            <x-tabler-bolt class="size-4" />
        </div>
        <span class="text-sm font-semibold text-heading-foreground">{{ __('Do') }}</span>
    </div>

    <div class="p-4">
        <template x-if="actions.length === 0">
            <div>
                <p class="mb-3 text-xs text-foreground/60">
                    {{ __('Add actions to run when this workflow fires.') }}
                </p>
                <div class="rounded-lg border border-dashed border-border px-3 py-2.5 text-center">
                    <button class="text-sm font-medium text-primary hover:underline" @click="openActionsConfig()">
                        + {{ __('Add Actions') }}
                    </button>
                </div>
            </div>
        </template>

        <template x-if="actions.length > 0">
            <div>
                <div class="space-y-2">
                    <template x-for="(action, index) in actions.slice(0, 4)" :key="'act-prev-' + index">
                        <div class="flex items-start gap-2">
                            <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded bg-foreground/5 text-[9px] font-semibold text-foreground/50" x-text="index + 1"></span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-heading-foreground" x-text="actionTypeLabel(action.type)"></p>
                                <p class="line-clamp-1 text-[11px] text-foreground/50" x-text="actionSummary(action)"></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="actions.length > 4">
                        <p class="text-center text-[10px] text-foreground/40" x-text="'+ ' + (actions.length - 4) + ' more'"></p>
                    </template>
                </div>
                <button class="mt-3 text-xs font-medium text-primary hover:underline" @click="openActionsConfig()">
                    {{ __('Edit Actions') }}
                </button>
            </div>
        </template>
    </div>
</div>
