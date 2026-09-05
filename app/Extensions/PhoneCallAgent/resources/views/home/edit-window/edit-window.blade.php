{{-- Phone Call Agent Edit Window --}}
<template x-teleport="body">
    <div
        class="lqd-phone-agent-edit-window fixed bottom-0 end-0 start-0 top-0 z-[100] overflow-y-auto bg-background lg:start-[--navbar-width]"
        x-show="activeAgent.id"
        :class="{ active: activeAgent }"
    >
        {{-- Header --}}
        <div class="lqd-chatbot-edit-window-header sticky top-0 z-2 border-b bg-background/60 backdrop-blur-lg backdrop-saturate-150">
            <div class="flex flex-wrap items-center justify-between gap-4 px-3 py-3 lg:h-[--header-height] lg:px-12">
                <div class="flex flex-wrap items-center gap-3">
                    <x-button
                        class="text-2xs text-heading-foreground/50 hover:text-heading-foreground"
                        @click.prevent="setActiveAgent(null)"
                        variant="link"
                    >
                        <x-tabler-chevron-left class="size-4" />
                        @lang('Close')
                    </x-button>

                    <span class="opacity-10">|</span>

                    <span class="text-heading-foreground/50">
                        @lang('Editing'):
                        <span
                            class="text-heading-foreground"
                            x-text="activeAgent.title"
                        ></span>
                    </span>
                </div>

                {{-- Step indicators --}}
                <div class="lqd-steps hidden flex-col gap-1 lg:flex">
                    <div class="lqd-steps-steps flex items-center justify-between gap-1 lg:gap-3">
                        @foreach ([__('Configure'), __('Train'), __('Phone Numbers')] as $stepLabel)
                            <button
                                class="lqd-step group/step flex gap-3 rounded p-2 text-3xs font-semibold capitalize text-heading-foreground transition-colors hover:bg-heading-foreground/5 disabled:pointer-events-none disabled:opacity-50 lg:min-w-32"
                                type="button"
                                @click.prevent="setEditingStep({{ $loop->index + 1 }})"
                                :disabled="submittingData"
                            >
                                <span class="inline-grid size-[21px] place-items-center rounded-md border border-heading-foreground/10 transition-colors group-hover/step:border-heading-foreground group-hover/step:bg-heading-foreground group-hover/step:text-heading-background">
                                    <span
                                        class="col-start-1 col-end-1 row-start-1 row-end-1"
                                        x-show="editingStep <= {{ $loop->index + 1 }}"
                                        x-transition
                                    >{{ $loop->index + 1 }}</span>
                                    <svg
                                        class="col-start-1 col-end-1 row-start-1 row-end-1"
                                        x-show="editingStep > {{ $loop->index + 1 }}"
                                        x-transition
                                        width="9"
                                        height="7"
                                        viewBox="0 0 9 7"
                                        fill="currentColor"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path d="M3.14724 7L0 3.68191L0.78681 2.85239L3.14724 5.34096L8.21319 0L9 0.829522L3.14724 7Z" />
                                    </svg>
                                </span>
                                @lang($stepLabel)
                            </button>
                        @endforeach
                    </div>
                    <div class="lqd-step-progress relative h-[3px] w-full overflow-hidden rounded-lg bg-heading-foreground/5">
                        <div
                            class="lqd-step-progress-bar absolute start-0 top-0 h-full w-0 rounded-full bg-gradient-to-r from-gradient-from to-gradient-to transition-all"
                            :style="{ width: (editingStep / 3 * 100) + '%' }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lqd-chatbot-edit-window-content py-8 lg:py-0">
            <div class="container flex flex-wrap justify-between gap-y-5">
                <div class="lqd-chatbot-edit-window-options grid w-full lg:min-h-[calc(100vh-var(--header-height))] lg:w-[550px] lg:py-16">
                    <input
                        type="hidden"
                        name="id"
                        x-model="activeAgent.id"
                    >

                    @include('phone-call-agent::home.edit-window.edit-steps.edit-step-configure')
                    @include('phone-call-agent::home.edit-window.edit-steps.edit-step-train')
                    @include('phone-call-agent::home.edit-window.edit-steps.edit-step-usage')

                    <div
                        class="col-start-1 col-end-2 row-start-2 row-end-2 mt-10 flex flex-col gap-3"
                        :class="{ 'invisible': editingStep > 1 }"
                        x-transition
                    >
                        <x-button
                            class="w-full"
                            variant="secondary"
                            ::class="{ 'invisible opacity-0': editingStep > 1 }"
                            @click.prevent="setEditingStep('>')"
                            size="lg"
                            type="button"
                            ::disabled="submittingData"
                        >
                            @lang('Next')
                        </x-button>
                        <x-button
                            class="w-full"
                            variant="outline"
                            ::class="{ 'invisible opacity-0': editingStep <= 1 }"
                            @click.prevent="setEditingStep('<')"
                            size="lg"
                            type="button"
                            ::disabled="submittingData"
                        >
                            @lang('Back')
                        </x-button>
                    </div>
                </div>

                {{-- Right column: phone simulator --}}
                <div class="hidden w-full lg:flex lg:w-[calc(50%-2rem)] lg:items-start lg:py-16">
                    @include('phone-call-agent::home.phone-simulator')
                </div>
            </div>
        </div>
    </div>
</template>
