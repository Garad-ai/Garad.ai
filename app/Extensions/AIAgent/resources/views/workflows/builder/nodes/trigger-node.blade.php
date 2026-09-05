{{-- Trigger Node --}}
<div
    class="group relative w-[min(275px,100%)] cursor-pointer rounded-xl bg-background transition hover:border-foreground"
    :class="{ 'ring-2 ring-border': selectedStepId === 'trigger', 'border-2 ring-2 ring-border/50': !triggerConfigured, 'border': triggerConfigured }"
    @click="openStepSettings('trigger')"
>
    {{-- Unconfigured State --}}
    <template x-if="!triggerConfigured">
        <div class="p-4">
            <x-tabler-plus class="mb-3 size-4 opacity-60 transition group-hover:opacity-100" />
            <p class="mb-0.5 text-2xs font-semibold text-heading-foreground">
                {{ __('Trigger') }}
            </p>
            <p class="m-0 text-2xs">
                {{ __('When should we run this agent?') }}
            </p>
        </div>
    </template>

    {{-- Configured State --}}
    <template x-if="triggerConfigured">
        <div>
            {{-- Head --}}
            <div class="flex items-center gap-2 border-b px-3 py-2.5">
                <div class="inline-grid size-[30px] shrink-0 place-items-center rounded-full bg-gradient-to-t from-[#CECFF2] to-[#E6E9F1] text-black">
                    <template x-if="triggerType === 'schedule'">
                        <x-tabler-clock class="size-4" />
                    </template>
                    <template x-if="triggerType === 'channel_message'">
                        <x-tabler-message class="size-4" />
                    </template>
                    <template x-if="triggerType === 'webhook'">
                        <x-tabler-webhook class="size-4" />
                    </template>
                    <template x-if="triggerType !== 'schedule' && triggerType !== 'channel_message' && triggerType !== 'webhook'">
                        <x-tabler-bolt class="size-4" />
                    </template>
                </div>

                <p
                    class="m-0 truncate text-2xs font-medium"
                    x-text="triggerTypeLabel"
                ></p>

                <div class="ms-auto flex items-center justify-end gap-1">
                    {{-- Warning: channel_message with no channel selected --}}
                    <template x-if="triggerType === 'channel_message' && !triggerConfig.channel_id">
                        <div
                            class="ms-auto size-4 shrink-0 text-amber-500"
                            title="{{ __('No channel selected') }}"
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
                                @click.stop="openStepSettings('trigger'); open = false"
                            >
                                <x-tabler-pencil class="size-4" />
                                {{ __('Edit') }}
                            </button>
                            <button
                                class="flex w-full items-center gap-2 px-3 py-2 text-2xs font-medium text-red-500 hover:bg-red-50"
                                type="button"
                                @click.stop="disconnectTrigger(); open = false"
                            >
                                <x-tabler-unlink class="size-4" />
                                {{ __('Disconnect') }}
                            </button>
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </div>
            </div>

            {{-- Details --}}
            <div class="flex items-center gap-2 p-4">
                <p
                    class="m-0 text-2xs"
                    x-text="triggerSummary"
                ></p>
            </div>
        </div>
    </template>
</div>
