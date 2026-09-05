{{-- Step Node — used inside x-for loop, variable: step --}}
<div
    class="group relative w-[min(275px,100%)] cursor-pointer rounded-xl border bg-background transition hover:border-foreground"
    :class="{ 'ring-2 ring-border': selectedStepId === step.id }"
    @click="openStepSettings(step.id)"
>
    {{-- Head --}}
    <div class="flex items-center gap-2 border-b px-3 py-2.5">
        <div
            class="inline-grid size-[30px] place-items-center rounded-full"
            :class="{
                'bg-[#FFEEAB] text-[#90761F]': step.type === 'ai_call',
                'bg-[#E6E9F1] text-black': !['ai_call'].includes(step.type),
            }"
        >
            <template x-if="step.type === 'ai_call'">
                <x-tabler-brain class="size-4" />
            </template>
            <template x-if="step.type === 'send_message'">
                <x-tabler-send class="size-4" />
            </template>
            <template x-if="step.type === 'generate_report'">
                <x-tabler-report-analytics class="size-4" />
            </template>
            <template x-if="step.type === 'path'">
                <x-tabler-git-fork class="size-4" />
            </template>
            <template x-if="step.type === 'external_chatbot'">
                <x-tabler-message-chatbot class="size-4" />
            </template>
            <template x-if="step.type === 'marketing_bot'">
                <x-tabler-speakerphone class="size-4" />
            </template>
            <template x-if="step.type === 'social_media_agent'">
                <x-tabler-social class="size-4" />
            </template>
            <template x-if="step.type === 'gmail'">
                <x-tabler-brand-gmail class="size-4" />
            </template>
            <template x-if="step.type === 'outlook'">
                <x-tabler-brand-office class="size-4" />
            </template>
            <template x-if="!['ai_call','send_message','generate_report','path','external_chatbot','marketing_bot','social_media_agent','gmail','outlook'].includes(step.type)">
                <x-tabler-bolt class="size-4" />
            </template>
        </div>

        <p
            class="m-0 truncate text-2xs font-medium"
            x-text="actionTypeLabel(step.type)"
        ></p>

        <div class="ms-auto flex items-center justify-end gap-1">
            {{-- Warning: references a variable no earlier step produces --}}
            <template x-if="stepUnknownVariables(step).length > 0">
                <div
                    class="ms-auto size-4 shrink-0 text-red-500"
                    :title="'{{ __('Unknown variable') }}: ' + stepUnknownVariables(step).join(', ')"
                >
                    <x-tabler-variable-off class="size-4" />
                </div>
            </template>

            {{-- Warning: required fields missing --}}
            <template x-if="stepHasMissingRequired(step)">
                <div
                    class="ms-auto size-4 shrink-0 text-amber-500"
                    title="{{ __('Required fields missing') }}"
                >
                    <x-tabler-alert-triangle class="size-4" />
                </div>
            </template>

            {{-- Ellipsis menu --}}
            <x-dropdown.dropdown
                class="relative ms-auto shrink-0"
                class:dropdown="overflow-hidden"
                offsetY="10px"
                anchor="end"
            >
                <x-slot:trigger
                    class="size-7 p-0"
                    variant="ghost"
                    size="none"
                    type="button"
                >
                    <x-tabler-dots-vertical class="size-3.5" />
                </x-slot:trigger>
                <x-slot:dropdown>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-2xs font-medium hover:bg-foreground/5"
                        type="button"
                        @click.stop="openStepSettings(step.id); open = false"
                    >
                        <x-tabler-pencil class="size-4" />
                        {{ __('Edit') }}
                    </button>
                    <button
                        class="flex w-full items-center gap-2 px-3 py-2 text-2xs font-medium text-red-500 hover:bg-red-50"
                        type="button"
                        @click.stop="removeStep(step.id); open = false"
                    >
                        <x-tabler-trash class="size-4" />
                        {{ __('Delete') }}
                    </button>
                </x-slot:dropdown>
            </x-dropdown.dropdown>
        </div>
    </div>

    {{-- Summary --}}
    <div class="p-4">
        <p
            class="m-0 text-2xs"
            x-text="stepSummary(step)"
        ></p>
    </div>
</div>
