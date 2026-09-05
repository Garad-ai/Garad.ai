{{-- Right Step Settings Panel --}}
<div
    class="group/settings relative z-20 flex shrink-0 flex-col gap-5 overflow-hidden overflow-y-auto border-s border-border bg-background py-4 transition-all duration-200 max-lg:absolute max-lg:inset-x-0 max-lg:bottom-0 max-lg:h-[calc(100vh-var(--header-h)-6rem)] max-lg:translate-y-full max-lg:border-t max-lg:shadow-2xl max-lg:shadow-black/5 lg:w-0 lg:py-6 max-lg:[&.is-open]:translate-y-0 lg:[&.is-open]:w-[--sidebar-w]"
    :class="{ 'is-open': settingsPanelOpen }"
>
    {{-- Panel Header --}}
    <div class="mb-5 flex shrink-0 items-center gap-2 px-4 lg:px-6">
        <div class="min-w-0 flex-1">
            <p class="mb-1 text-base font-medium">
                <span x-show="selectedStepId === 'trigger'">
                    {{ __('Trigger') }}
                </span>
                <span
                    x-show="selectedStepId === 'workflow_settings'"
                    x-cloak
                >
                    {{ __('Agent Settings') }}
                </span>
                <span
                    x-show="selectedStepId !== 'trigger' && selectedStepId !== 'workflow_settings' && selectedStep"
                    x-text="selectedStep ? actionTypeLabel(selectedStep.type) : ''"
                ></span>
            </p>

            <p class="m-0 text-2xs">
                <span x-show="selectedStepId === 'trigger'">
                    {{ __('When should this agent run?') }}
                </span>
                <span
                    x-show="selectedStepId === 'workflow_settings'"
                    x-cloak
                >
                    {{ __('Name, role, avatar & instructions') }}
                </span>
                <span
                    x-show="selectedStepId !== 'trigger' && selectedStepId !== 'workflow_settings' && selectedStep"
                    x-text="'Step ' + ((selectedStep?.order ?? 0) + 1)"
                ></span>
            </p>
        </div>

        {{-- Close button --}}
        <x-button
            class="inline-grid size-[30px] place-items-center"
            variant="ghost"
            size="none"
            type="button"
            @click="closeStepSettings()"
        >
            <x-tabler-x class="size-4" />
            <span class="sr-only">{{ __('Close') }}</span>
        </x-button>
    </div>

    {{-- Trigger selector (only shown when trigger panel, above config) --}}
    <div
        class="shrink-0 px-4 lg:px-6"
        x-show="selectedStepId === 'trigger'"
    >
        <div class="grid grid-cols-2 gap-2.5">
            @foreach ([
        ['schedule', __('Schedule'), 'tabler-clock'],
        ['channel_message', __('Channel Message'), 'tabler-message'],
        // ['webhook',         __('Webhook'),         'tabler-webhook'], // TODO: add webhook trigger support in the future
    ] as [$val, $lbl, $icon])
                <button
                    class="flex flex-col items-center justify-center gap-1 rounded-lg border p-3.5 text-center font-medium text-[2xs] transition active:scale-95"
                    :class="{ 'border-primary ring-1 ring-primary': triggerType === '{{ $val }}' }"
                    type="button"
                    @click.prevent="triggerType = '{{ $val }}'"
                >
                    <span class="inline-grid size-[30px] place-items-center rounded-full bg-[#E6E9F1] text-black">
                        <x-dynamic-component
                            class="size-4"
                            :component="$icon"
                        />
                    </span>
                    <span>{{ $lbl }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex h-full flex-col px-4 lg:px-6">
        {{-- Trigger config --}}
        <template x-if="selectedStepId === 'trigger'">
            @include('ai-agent::workflows.builder.partials.panel-trigger-config')
        </template>

        {{-- Agent settings --}}
        <template x-if="selectedStepId === 'workflow_settings'">
            @include('ai-agent::workflows.builder.partials.panel-workflow-config')
        </template>

        {{-- Step config --}}
        <template x-if="selectedStepId !== 'trigger' && selectedStepId !== 'workflow_settings' && selectedStep">
            @include('ai-agent::workflows.builder.partials.panel-step-config')
        </template>

        {{-- Footer --}}
        <div class="sticky bottom-0 mt-auto shrink-0 pt-12 lg:pt-5">
            <x-progressive-blur class="-bottom-4 -end-4 -start-4 top-0 z-0 lg:-bottom-6 lg:-end-6 lg:-start-6 lg:-top-4" />

            <div class="relative z-1">
                <x-button
                    class="w-full"
                    variant="secondary"
                    type="button"
                    @click.prevent="selectedStepId === 'trigger' ? saveTriggerConfig() : saveWorkflow()"
                >
                    {{ __('Save') }}
                    <span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                        <x-tabler-chevron-right class="size-4" />
                    </span>
                </x-button>
            </div>
        </div>
    </div>
</div>
