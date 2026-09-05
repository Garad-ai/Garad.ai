<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full transition duration-300"
    x-show="currentStep === 3"
    @keydown.enter.prevent
    x-transition:enter-start="opacity-0 translate-x-3 blur-sm"
    x-transition:enter-end="opacity-100 translate-x-0 blur-0"
    x-transition:leave-start="opacity-100 translate-x-0 blur-0"
    x-transition:leave-end="opacity-0 -translate-x-3 blur-sm"
    x-cloak
>
    <h2 class="mb-9 text-center text-[24px] font-medium leading-[1.2em]">
        <span class="block text-[0.875em] opacity-50">
            @lang('Describe your target audience')
        </span>
        @lang('Who are you trying to reach?')
    </h2>

    <x-forms.input
        class:container="mb-9"
        class:label="flex-row-reverse justify-between text-heading-foreground text-xs font-medium select-none"
        type="checkbox"
        label="{{ __('AI Decides automatically for the best engagement') }}"
        size="sm"
        switcher
        switcherFill
        x-model="formData.ai_target_audience"
		@keydown.enter="nextStep()"
	/>

    <div
        x-show="!formData.ai_target_audience"
        x-collapse
    >
        <div class="flex flex-col gap-3 rounded-[20px] border px-5 py-7 text-center">
            <div
                class="flex flex-wrap gap-2"
                x-show="availableTargets.length"
                x-cloak
            >
                <template
                    x-for="target in availableTargets"
                    :key="target.id"
                >
                    <label
                        class="group relative flex min-h-[42px] items-center gap-1.5 rounded-full bg-foreground/5 px-4 py-2.5 text-xs text-heading-foreground has-[input:checked]:bg-primary/5 has-[input:checked]:text-primary"
                    >
                        <input
                            class="peer absolute inset-0 z-1 cursor-pointer opacity-0"
                            type="checkbox"
                            :value="target"
                            @change="toggleTarget(target)"
                            :checked="isTargetSelected(target.id)"

                        >
                        <span x-text="target.name"></span>
                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 mb-2 w-[clamp(269px,269px,85vw)] origin-bottom -translate-x-1/2 translate-y-1 scale-95 rounded-md border bg-background/95 px-3 py-2 text-center text-2xs font-medium text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm transition group-hover:translate-y-0 group-hover:scale-100 group-hover:opacity-100"
                            x-text="target.description"
                        ></span>
                        <span
                            class="inline-grid size-[22px] place-items-center rounded-full border border-foreground/15 peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground"
                        >
                            <x-tabler-check class="hidden size-4 group-has-[input:checked]:flex" />
                        </span>
                    </label>
                </template>
            </div>

            <hr
                x-show="availableTargets.length"
                x-cloak
            >

            <x-button
                class="self-center underline disabled:bg-transparent disabled:no-underline"
                variant="link"
                type="button"
                @click.prevent="generateTargets"
                ::disabled="generatingTargets"
            >
                <span x-show="!generatingTargets && !availableTargets.length">
                    @lang('Generate Target Audiences')
                </span>
                <span
                    class="flex items-center gap-2"
                    x-show="generatingTargets"
                    x-cloak
                >
                    @lang('Generating Audiences')
                    <x-tabler-loader-2 class="size-4 animate-spin" />
                </span>
                <span
                    x-show="!generatingTargets && availableTargets.length"
                    x-cloak
                >
                    @lang('Regenerate Targets')
                </span>
            </x-button>
        </div>
    </div>

    <div class="mt-2">
        @include('social-media-agent::create.step-error', ['step' => 3])
    </div>

    <x-button
        class="mt-5 w-full bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to py-[18px] text-xs font-medium leading-none text-primary-foreground disabled:from-foreground/5 disabled:via-foreground/5 disabled:to-foreground/5"
        type="button"
        @click.prevent="nextStep()"
        ::disabled="generatingTargets"
    >
        @lang('Continue')
        <x-tabler-arrow-right class="size-4" />
    </x-button>
</div>
