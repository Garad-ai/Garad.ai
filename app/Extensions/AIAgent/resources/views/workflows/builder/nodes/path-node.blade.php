{{-- Path Node — used inside x-for loop, variable: step --}}
<div class="flex w-full flex-col items-center">

    {{-- Split header node --}}
    <div
        class="group relative w-[min(275px,100%)] cursor-pointer rounded-xl border bg-background transition hover:border-foreground"
        :class="{ 'ring-2 ring-border': selectedStepId === step.id }"
        @click.prevent="openStepSettings(step.id)"
    >
        {{-- Head --}}
        <div class="flex items-center gap-2 border-b px-3 py-2.5">
            <div class="inline-grid size-[30px] place-items-center rounded-full bg-[#E6E9F1] text-black">
                <x-tabler-git-fork class="size-4" />
            </div>

            <p
                class="m-0 truncate text-2xs font-medium"
                x-text="actionTypeLabel(step.type)"
            ></p>

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

        {{-- Summary --}}
        <div class="p-4">
            <p
                class="m-0 text-2xs"
                x-text="stepSummary(step)"
            ></p>
        </div>
    </div>

    {{-- Connector line from split node to branches --}}
    <div class="h-6 w-px bg-border"></div>

    {{-- Branch columns --}}
    <div class="flex w-full items-start gap-6 overflow-x-auto">
        <template
            x-for="(path, pathIndex) in step.config.paths"
            :key="path.id"
        >
            <div class="group/branch flex w-[min(275px,100%)] shrink-0 grow-0 basis-auto flex-col items-center first-of-type:ms-auto last-of-type:me-auto">
                <div class="mb-4 h-px min-w-full bg-border"></div>

                {{-- Branch label pill --}}
                <x-button
                    class="group/btn whitespace-nowrap text-2xs"
                    variant="outline"
                    size="sm"
                    @click.stop="openStepSettings(step.id)"
                >
                    <span class="inline-grid size-5 place-items-center">
                        <x-tabler-git-fork
                            class="visible col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-100 opacity-100 transition group-hover/btn:invisible group-hover/btn:scale-90 group-hover/btn:opacity-0"
                        />
                        <x-tabler-pencil
                            class="invisible col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-90 opacity-0 transition group-hover/btn:visible group-hover/btn:scale-100 group-hover/btn:opacity-100"
                        />
                    </span>
                    <span x-text="path.name"></span>
                </x-button>

                {{-- Sub-steps --}}
                <div class="flex w-full flex-col items-center">
                    {{-- Add first sub-step button --}}
                    <button
                        class="group/btn flex flex-col items-center gap-2 p-1"
                        @click.stop="openAddSubStepModal(step.id, pathIndex, 0)"
                        type="button"
                        x-show="(path.steps ?? []).length === 0"
                        x-cloak
                    >
                        <span class="h-3 w-px bg-foreground/10"></span>

                        <span
                            class="inline-grid size-6 place-items-center rounded-button transition group-hover/btn:scale-110 group-hover/btn:bg-primary group-hover/btn:text-primary-foreground"
                        >
                            <x-tabler-plus class="size-4" />
                        </span>
                    </button>

                    <template
                        x-for="(subStep, subIndex) in (path.steps ?? [])"
                        :key="subStep.id"
                    >
                        <div class="flex w-full flex-col items-center">
                            <div class="h-4 w-px bg-border"></div>

                            {{-- Sub-step card --}}
                            <div
                                class="group relative w-[min(275px,100%)] cursor-pointer rounded-xl border bg-background transition hover:border-foreground"
                                :class="{ 'ring-2 ring-border': selectedStepId === 'substep::' + step.id + '::' + pathIndex + '::' + subStep.id }"
                                @click.stop="openSubStepSettings(step.id, pathIndex, subStep.id)"
                            >
                                {{-- Head --}}
                                <div class="flex items-center gap-2 border-b px-3 py-2.5">
                                    <div
                                        class="inline-grid size-[30px] place-items-center rounded-full"
                                        :class="{
                                            'bg-[#FFEEAB] text-[#90761F]': subStep.type === 'ai_call',
                                            'bg-[#E6E9F1] text-black': !['ai_call'].includes(subStep.type),
                                        }"
                                    >
                                        <template x-if="subStep.type === 'ai_call'">
                                            <x-tabler-brain class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'send_message'">
                                            <x-tabler-send class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'generate_report'">
                                            <x-tabler-report-analytics class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'path'">
                                            <x-tabler-git-fork class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'external_chatbot'">
                                            <x-tabler-message-chatbot class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'marketing_bot'">
                                            <x-tabler-speakerphone class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'social_media_agent'">
                                            <x-tabler-social class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'gmail'">
                                            <x-tabler-brand-gmail class="size-4" />
                                        </template>
                                        <template x-if="subStep.type === 'outlook'">
                                            <x-tabler-brand-office class="size-4" />
                                        </template>
                                        <template
                                            x-if="!['ai_call','send_message','generate_report','path','external_chatbot','marketing_bot','social_media_agent','gmail','outlook'].includes(subStep.type)"
                                        >
                                            <x-tabler-bolt class="size-4" />
                                        </template>
                                    </div>

                                    <p
                                        class="m-0 truncate text-2xs font-medium"
                                        x-text="actionTypeLabel(subStep.type)"
                                    ></p>

                                    <div class="ms-auto flex items-center justify-end gap-1">
                                        {{-- Warning: required fields missing --}}
                                        <template x-if="subStep.type === 'send_message' && !subStep.config.channel_id">
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
                                                    @click.stop="openSubStepSettings(step.id, pathIndex, subStep.id); open = false"
                                                >
                                                    <x-tabler-pencil class="size-4" />
                                                    {{ __('Edit') }}
                                                </button>
                                                <button
                                                    class="flex w-full items-center gap-2 px-3 py-2 text-2xs font-medium text-red-500 hover:bg-red-50"
                                                    type="button"
                                                    @click.stop="removeSubStep(step.id, pathIndex, subStep.id); open = false"
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
                                        x-text="stepSummary(subStep)"
                                    ></p>
                                </div>
                            </div>

                            {{-- Add step after this sub-step --}}
                            <div class="flex flex-col items-center">
                                <button
                                    class="group/btn flex flex-col items-center gap-2 p-1"
                                    @click.stop="openAddSubStepModal(step.id, pathIndex, subIndex + 1)"
                                    type="button"
                                >
                                    <span class="h-3 w-px bg-foreground/10"></span>

                                    <span
                                        class="inline-grid size-6 place-items-center rounded-button transition group-hover/btn:scale-110 group-hover/btn:bg-primary group-hover/btn:text-primary-foreground"
                                    >
                                        <x-tabler-plus class="size-4" />
                                    </span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Path ends indicator --}}
                <div class="mt-2 rounded-full border border-dashed border-border px-3 py-1 text-[10px] text-foreground/30">
                    {{ __('Path ends') }}
                </div>
            </div>
        </template>
    </div>
</div>
