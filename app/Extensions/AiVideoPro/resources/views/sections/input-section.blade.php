<p class="mb-5 border-b py-2.5 text-[12px] font-semibold transition">
    {{ __('Video') }}
</p>

<x-card
    class:body="p-5"
    class="bg-transparent"
>
    <form
        class="flex flex-col gap-5"
        id="photo-studio-form"
        data-ai-video-pro-form
        method="post"
        action="{{ route('dashboard.user.ai-video-pro.store') }}"
        enctype="multipart/form-data"
        x-data="aiVideoProForm"
        @submit="handleSubmit($event)"
    >
        @csrf

        {{-- Custom Multi-Level Model Dropdown --}}
        <div>
            <label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition">
                {{ __('Model') }}
            </label>
            <div
                class="relative"
                @click.outside="modelDropdownOpen = false; activeModelHover = null"
            >
                <button
                    class="flex h-10 w-full items-center justify-between rounded-input border border-input-border px-4 py-2 text-start text-2xs font-medium transition"
                    type="button"
                    @click.prevent="modelDropdownOpen = !modelDropdownOpen"
                >
                    <span
                        class="w-full truncate"
                        x-text="getSelectedModelLabel"
                    ></span>
                    <x-tabler-chevron-down class="size-4 shrink-0 opacity-50" />
                </button>

                {{-- Dropdown Panel --}}
                <div
                    class="absolute inset-x-0 top-full z-50 mt-1 origin-top space-y-1.5 rounded-dropdown px-2 py-4 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] max-lg:max-h-96 max-lg:overflow-y-auto max-lg:bg-dropdown-background/90 max-lg:backdrop-blur-lg lg:before:absolute lg:before:inset-0 lg:before:-z-1 lg:before:rounded-[inherit] lg:before:bg-dropdown-background/90 lg:before:backdrop-blur-xl"
                    x-show="modelDropdownOpen"
                    x-cloak
                    x-transition
                >
                    <p class="m-0 px-4 text-2xs font-medium opacity-50">
                        {{ __('Select AI Model') }}
                    </p>

                    <template
                        x-for="(model, modelKey) in models"
                        :key="modelKey"
                    >
                        <template x-if="model.isActive">
                            <div
                                class="group/model relative"
                                x-data="{ dropdownOpen: false }"
                                :class="{ 'is-open': dropdownOpen }"
                            >
                                <button
                                    class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2 text-start text-xs text-heading-foreground transition group-hover/model:bg-foreground/[3%]"
                                    :class="{ 'active': selectedAction === modelKey }"
                                    type="button"
                                    @click.prevent="selectModel(modelKey); dropdownOpen = !dropdownOpen"
                                >
                                    <x-tabler-check
                                        class="invisible size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100"
                                    />

                                    <span class="block">
                                        <span
                                            class="block font-medium"
                                            x-text="model.label"
                                        ></span>
                                        <span
                                            class="block opacity-60"
                                            x-text="model.description || ''"
                                        ></span>
                                    </span>

                                    <x-tabler-chevron-right
                                        class="ms-auto size-4 shrink-0 opacity-40 transition max-lg:group-[&.is-open]/model:rotate-90"
                                        x-show="Object.keys(model.subModels || {}).length > 1"
                                    />
                                </button>

                                <template x-if="Object.keys(model.subModels || {}).length > 1">
                                    {{-- Sub-model --}}
                                    <div
                                        class="space-y-1.5 rounded-dropdown px-2 py-4 transition max-md:hidden md:invisible md:absolute md:start-full md:top-0 md:-ms-1 md:w-[min(320px,100%)] md:-translate-x-1 md:opacity-0 md:group-hover/model:visible md:group-hover/model:translate-x-0 md:group-hover/model:opacity-100 lg:shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] lg:before:absolute lg:before:inset-0 lg:before:-z-1 lg:before:rounded-[inherit] lg:before:bg-dropdown-background/90 lg:before:backdrop-blur-xl max-md:[&.active]:block"
                                        :class="{ 'active': dropdownOpen }"
                                    >
                                        <p class="m-0 px-4 text-2xs font-medium opacity-50 max-lg:hidden">
                                            {{ __('Select AI Model') }}
                                        </p>

                                        <template
                                            x-for="(subModel, subKey) in model.subModels"
                                            :key="subKey"
                                        >
                                            <template x-if="subModel.isActive">
                                                <button
                                                    class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
                                                    :class="{ 'active': selectedAction === modelKey && selectedSubModel === subKey }"
                                                    type="button"
                                                    @click.prevent="selectSubModelFromDropdown(modelKey, subKey); dropdownOpen = !dropdownOpen"
                                                >
                                                    <x-tabler-check
                                                        class="invisible size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100"
                                                    />

                                                    <span class="block">
                                                        <span
                                                            class="block font-medium"
                                                            x-text="subModel?.features[subKey]?.label ?? subKey"
                                                        ></span>
                                                        <span
                                                            class="block opacity-60"
                                                            x-text="subModel?.features[subKey]?.description ?? ''"
                                                        ></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>

        {{-- Hidden inputs for form submission --}}
        <input
            type="hidden"
            name="action"
            :value="selectedAction"
        >
        <input
            type="hidden"
            name="sub_model_value"
            :value="selectedSubModel"
        >

        {{-- Feature Selection (only if multiple features) --}}
        <div
            x-show="selectedSubModel && shouldShowFeatures"
            x-cloak
        >
            <label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition">
                {{ __('Select Feature') }}
            </label>

            <div
                class="relative"
                @click.outside="featureDropdownOpen = false"
            >
                <button
                    class="flex h-10 w-full items-center justify-between rounded-input border border-input-border px-4 py-2 text-start text-2xs font-medium transition"
                    type="button"
                    @click.prevent="featureDropdownOpen = !featureDropdownOpen"
                >
                    <span
                        class="w-full truncate"
                        x-text="getSelectedFeatureLabel"
                    ></span>

                    <x-tabler-chevron-down class="size-4 shrink-0 opacity-50" />
                </button>

                <div
                    class="absolute inset-x-0 top-full z-50 mt-1 origin-top space-y-1.5 rounded-dropdown bg-dropdown-background/90 px-2 py-4 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
                    x-show="featureDropdownOpen"
                    x-cloak
                    x-transition
                >
                    <p class="m-0 px-4 text-2xs font-medium opacity-50">
                        {{ __('Select Model Feature') }}
                    </p>

                    <template
                        x-for="feature in features"
                        :key="feature.value"
                    >
                        <button
                            class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
                            :class="{ 'active': selectedFeature === feature.value }"
                            type="button"
                            @click.prevent="selectedFeature = feature.value; initializeFormValues(); featureDropdownOpen = false"
                        >
                            <x-tabler-check class="invisible size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100" />

                            <span class="block">
                                <span
                                    class="block font-medium"
                                    x-text="feature.label"
                                ></span>
                                <span
                                    class="block opacity-60"
                                    x-text="feature.description ?? ''"
                                ></span>
                            </span>
                        </button>
                    </template>
                </div>
                <input
                    type="hidden"
                    name="feature"
                    :value="selectedFeature"
                >
            </div>
        </div>
        <input
            x-show="!shouldShowFeatures"
            type="hidden"
            name="feature"
            :value="selectedFeature"
            x-cloak
        >

        {{-- Dynamic Inputs Based on Selected Feature (single column) --}}
        <div
            class="flex flex-col gap-4"
            x-show="currentInputs.length > 0"
            x-cloak
        >
            <template
                x-for="(input, index) in currentInputs"
                :key="index"
            >
                <div x-show="shouldShowInput(input)">
                    {{-- Textarea Input --}}
                    <template x-if="input.type === 'textarea'">
                        <div>
                            <x-forms.input
                                class:label="text-foreground mb-1"
                                class:label-txt="flex items-center w-full gap-1 justify-between"
                                ::id="input.name"
                                ::name="input.name"
                                type="textarea"
                                size="lg"
                                ::rows="input.rows || 3"
                                ::placeholder="input.placeholder"
                                ::required="input.required"
                                x-model="formValues[input.name]"
                            >
                                <x-slot:label
                                    ::for="input.name"
                                >
                                    <span x-text="input.label"></span>

                                    <x-button
                                        class="ms-auto size-8 p-0"
                                        variant="ghost"
                                        hover-variant="none"
                                        size="none"
                                        type="button"
                                        title="{{ __('AI Enhance') }}"
                                        @click="enhancePrompt(input.name)"
                                        ::disabled="enhancingPrompt"
                                    >
                                        <span
                                            class="inline-block size-5 animate-spin rounded-full border-2 border-current border-t-transparent opacity-50"
                                            x-show="enhancingPrompt"
                                        ></span>
                                        {{-- blade-formatter-disable --}}
										<svg x-show="!enhancingPrompt" width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.18945 9.5332C3.29759 9.5333 3.40267 9.57014 3.48633 9.63867C3.57002 9.70725 3.6271 9.80311 3.64844 9.90918L3.72559 10.2891C3.82049 10.7565 4.05143 11.1852 4.38867 11.5225C4.72594 11.8597 5.15465 12.0907 5.62207 12.1855L6.00195 12.2627C6.10785 12.284 6.20295 12.3413 6.27148 12.4248C6.34007 12.5085 6.37787 12.6135 6.37793 12.7217C6.37793 12.83 6.34009 12.9348 6.27148 13.0186C6.20292 13.1022 6.108 13.1603 6.00195 13.1816L5.62207 13.2588C5.15482 13.3536 4.72588 13.5838 4.38867 13.9209C4.05134 14.2582 3.82044 14.6877 3.72559 15.1553L3.64844 15.5342C3.62717 15.6403 3.56998 15.7361 3.48633 15.8047C3.40266 15.8732 3.29761 15.911 3.18945 15.9111C3.08114 15.9111 2.97538 15.8733 2.8916 15.8047C2.808 15.7361 2.75075 15.6402 2.72949 15.5342L2.65234 15.1553C2.55749 14.6878 2.32753 14.2582 1.99023 13.9209C1.65294 13.5836 1.22333 13.3537 0.755859 13.2588L0.376953 13.1816C0.270757 13.1604 0.175093 13.1023 0.106445 13.0186C0.0378685 12.9348 0 12.8299 0 12.7217C6.36891e-05 12.6135 0.037856 12.5085 0.106445 12.4248C0.17508 12.3411 0.270847 12.284 0.376953 12.2627L0.755859 12.1855C1.22336 12.0907 1.65292 11.8598 1.99023 11.5225C2.32728 11.1853 2.55747 10.7563 2.65234 10.2891L2.72949 9.90918C2.75082 9.80311 2.80792 9.70726 2.8916 9.63867C2.97538 9.57002 3.08114 9.5332 3.18945 9.5332Z" fill="url(#paint0_linear_45_6)"/><path d="M11.1982 0C11.3405 2.3679e-05 11.4788 0.0494705 11.5889 0.139648C11.6988 0.22983 11.7738 0.355706 11.8018 0.495117L12.085 1.89062C12.2214 2.5627 12.5532 3.1801 13.0381 3.66504C13.5231 4.1499 14.1404 4.4808 14.8125 4.61719L16.208 4.90039C16.3472 4.92892 16.4717 5.00519 16.5615 5.11523C16.6514 5.22534 16.7012 5.36277 16.7012 5.50488C16.7012 5.647 16.6514 5.78443 16.5615 5.89453C16.4717 6.00457 16.3472 6.08085 16.208 6.10938L14.8125 6.3916C14.1404 6.528 13.5231 6.85983 13.0381 7.34473C12.5531 7.8297 12.2214 8.447 12.085 9.11914L11.8018 10.5146C11.7738 10.6541 11.6988 10.7799 11.5889 10.8701C11.4788 10.9603 11.3405 11.0097 11.1982 11.0098C11.0559 11.0098 10.9177 10.9603 10.8076 10.8701C10.6976 10.7799 10.6227 10.6541 10.5947 10.5146L10.3115 9.11914C10.1752 8.44693 9.8434 7.82973 9.3584 7.34473C8.87336 6.85975 8.25621 6.52789 7.58398 6.3916L6.18848 6.10938C6.04924 6.08088 5.92385 6.00464 5.83398 5.89453C5.7443 5.78449 5.69533 5.64685 5.69531 5.50488C5.69531 5.36293 5.74433 5.22528 5.83398 5.11523C5.92384 5.00514 6.04925 4.92889 6.18848 4.90039L7.58398 4.61719C8.25616 4.4809 8.87338 4.14996 9.3584 3.66504C9.84336 3.18007 10.1752 2.56277 10.3115 1.89062L10.5947 0.495117C10.6227 0.355654 10.6976 0.229837 10.8076 0.139648C10.9177 0.0494488 11.0559 0 11.1982 0Z" fill="url(#paint1_linear_45_6)"/><defs><linearGradient id="paint0_linear_45_6" x1="16.7009" y1="7.9555" x2="-0.163271" y2="6.01201" gradientUnits="userSpaceOnUse"><stop stop-color="#8D65E9"/><stop offset="0.483" stop-color="#5391E4"/><stop offset="1" stop-color="#6BCD94"/></linearGradient><linearGradient id="paint1_linear_45_6" x1="16.7009" y1="7.9555" x2="-0.163271" y2="6.01201" gradientUnits="userSpaceOnUse"><stop stop-color="#8D65E9"/><stop offset="0.483" stop-color="#5391E4"/><stop offset="1" stop-color="#6BCD94"/></linearGradient></defs></svg>
										{{-- blade-formatter-enable --}}
                                    </x-button>
                                </x-slot:label>
                            </x-forms.input>
                        </div>
                    </template>

                    {{-- Text Input --}}
                    <template x-if="input.type === 'text'">
                        <x-forms.input
                            ::id="input.name"
                            ::name="input.name"
                            type="text"
                            size="lg"
                            ::placeholder="input.placeholder"
                            ::required="input.required"
                            x-model="formValues[input.name]"
                        >
                            <x-slot:label
                                ::for="input.name"
                            >
                                <span x-text="input.label"></span>
                            </x-slot:label>
                        </x-forms.input>
                    </template>

                    {{-- Number Input --}}
                    <template x-if="input.type === 'number'">
                        <x-forms.input
                            ::id="input.name"
                            ::name="input.name"
                            type="number"
                            size="lg"
                            ::min="input.min"
                            ::max="input.max"
                            ::step="input.step"
                            ::placeholder="input.placeholder"
                            ::required="input.required"
                            x-model="formValues[input.name]"
                        >
                            <x-slot:label
                                ::for="input.name"
                            >
                                <span x-text="input.label"></span>
                            </x-slot:label>
                        </x-forms.input>
                    </template>

                    {{-- Select Input --}}
                    <template x-if="input.type === 'select'">
                        <div
                            class="relative"
                            @click.outside="openSelect === input.name ? openSelect = null : null"
                        >
                            <label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition">
                                <span
                                    class="lqd-input-label-txt"
                                    x-text="input.label"
                                ></span>
                                <template x-if="input.tooltip">
                                    <span
                                        class="lqd-tooltip-container group relative inline-flex cursor-default before:absolute before:-start-1.5 before:-top-1.5 before:h-7 before:w-7 [&:hover>.lqd-tooltip-content]:visible [&:hover>.lqd-tooltip-content]:translate-y-0 [&:hover>.lqd-tooltip-content]:opacity-100"
                                    >
                                        <span class="lqd-tooltip-icon opacity-40">
                                            <x-tabler-info-circle-filled class="size-4" />
                                        </span>
                                        <span
                                            class="lqd-tooltip-content invisible absolute bottom-full start-1/2 z-50 mb-3 min-w-64 -translate-x-1/2 translate-y-1 rounded-xl bg-background/80 px-4 py-3 text-center text-xs leading-normal text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm backdrop-saturate-150 transition-all before:absolute before:inset-x-0 before:-top-3 before:h-3"
                                            x-text="input.tooltip"
                                        ></span>
                                    </span>
                                </template>
                            </label>
                            <button
                                class="flex h-10 w-full items-center justify-between rounded-input border border-input-border px-4 py-2 text-start text-2xs font-medium transition"
                                type="button"
                                @click.prevent="toggleSelect(input.name)"
                            >
                                <span
                                    class="w-full truncate"
                                    x-text="getSelectLabel(input)"
                                ></span>

                                <x-tabler-chevron-down class="size-4 shrink-0 opacity-50" />
                            </button>

                            <div
                                class="absolute inset-x-0 top-full z-50 mt-1 origin-top space-y-1.5 rounded-dropdown bg-dropdown-background/90 px-2 py-4 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
                                x-show="openSelect === input.name"
                                x-cloak
                                x-transition
                            >
                                <template
                                    x-for="option in input.options"
                                    :key="option.value"
                                >
                                    <button
                                        class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
                                        :class="{ 'active': formValues[input.name] == option.value }"
                                        type="button"
                                        @click.prevent="formValues[input.name] = option.value; openSelect = null"
                                    >
                                        <x-tabler-check
                                            class="invisible size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100"
                                        />

                                        <span class="block">
                                            <span
                                                class="block font-medium"
                                                x-text="option.label"
                                            ></span>
                                            <span
                                                class="block opacity-60"
                                                x-text="option.description ?? ''"
                                            ></span>
                                        </span>
                                    </button>
                                </template>
                            </div>

                            <input
                                type="hidden"
                                :name="input.name"
                                :value="formValues[input.name]"
                            >
                        </div>
                    </template>

                    {{-- Checkbox Input --}}
                    <template x-if="input.type === 'checkbox'">
                        <div class="flex w-full items-center justify-between rounded-md border p-3">
                            <div class="flex gap-3">
                                <span
                                    class="text-xs font-medium text-heading-foreground"
                                    x-text="input.label"
                                ></span>
                                <template x-if="input.tooltip">
                                    <span
                                        class="lqd-tooltip-container group relative inline-flex cursor-default before:absolute before:-start-1.5 before:-top-1.5 before:h-7 before:w-7 [&:hover>.lqd-tooltip-content]:visible [&:hover>.lqd-tooltip-content]:translate-y-0 [&:hover>.lqd-tooltip-content]:opacity-100"
                                    >
                                        <span class="lqd-tooltip-icon opacity-40">
                                            <x-tabler-info-circle-filled class="size-4" />
                                        </span>
                                        <span
                                            class="lqd-tooltip-content invisible absolute bottom-full start-1/2 z-50 mb-3 min-w-64 -translate-x-1/2 translate-y-1 rounded-xl bg-background/80 px-4 py-3 text-center text-xs leading-normal text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm backdrop-saturate-150 transition-all before:absolute before:inset-x-0 before:-top-3 before:h-3"
                                            x-text="input.tooltip"
                                        ></span>
                                    </span>
                                </template>
                            </div>
                            <x-forms.input
                                class="bg-foreground/30 checked:bg-primary"
                                type="checkbox"
                                ::name="input.name"
                                value="1"
                                switcher
                                ::checked="input.default"
                                x-model="formValues[input.name]"
                            />
                        </div>
                    </template>

                    {{-- File Input --}}
                    <template x-if="input.type === 'file'">
                        <div
                            class="w-full"
                            ::data-exclude-media-manager="input.excludeMediaManager ? 'true' : undefined"
                        >
                            <label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition">
                                <span x-text="input.label"></span>
                                <template x-if="input.tooltip">
                                    <span
                                        class="lqd-tooltip-container group relative inline-flex cursor-default before:absolute before:-start-1.5 before:-top-1.5 before:h-7 before:w-7 [&:hover>.lqd-tooltip-content]:visible [&:hover>.lqd-tooltip-content]:translate-y-0 [&:hover>.lqd-tooltip-content]:opacity-100"
                                    >
                                        <span class="lqd-tooltip-icon opacity-40">
                                            <x-tabler-info-circle-filled class="size-4" />
                                        </span>
                                        <span
                                            class="lqd-tooltip-content invisible absolute bottom-full start-1/2 z-50 mb-3 min-w-64 -translate-x-1/2 translate-y-1 rounded-xl bg-background/80 px-4 py-3 text-center text-xs leading-normal text-foreground opacity-0 shadow-lg shadow-black/5 backdrop-blur-sm backdrop-saturate-150 transition-all before:absolute before:inset-x-0 before:-top-3 before:h-3"
                                            x-text="input.tooltip"
                                        ></span>
                                    </span>
                                </template>
                            </label>

                            <label
                                class="lqd-filepicker-label block cursor-pointer rounded-input border border-dashed px-4 py-5 text-center transition hover:border-primary/50 hover:bg-primary/[3%]"
                                :for="input.name.replace(/[\[\]]/g, '_')"
                                @drop="dropHandler($event, input.name.replace(/[\[\]]/g, '_'))"
                                @dragover.prevent
                            >
                                <svg
                                    class="mx-auto mb-2.5 opacity-25"
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M20.4677 20.5161C18.1452 22.8387 15.3226 24 12 24C8.67742 24 5.83871 22.8387 3.48387 20.5161C1.16129 18.1613 0 15.3226 0 12C0 8.67742 1.16129 5.85484 3.48387 3.53226C5.83871 1.17742 8.67742 0 12 0C15.3226 0 18.1452 1.17742 20.4677 3.53226C22.8226 5.85484 24 8.67742 24 12C24 15.3226 22.8226 18.1613 20.4677 20.5161ZM18.8226 5.17742C16.9516 3.27419 14.6774 2.32258 12 2.32258C9.32258 2.32258 7.03226 3.27419 5.12903 5.17742C3.25806 7.04839 2.32258 9.32258 2.32258 12C2.32258 14.6774 3.25806 16.9677 5.12903 18.871C7.03226 20.7419 9.32258 21.6774 12 21.6774C14.6774 21.6774 16.9516 20.7419 18.8226 18.871C20.7258 16.9677 21.6774 14.6774 21.6774 12C21.6774 9.32258 20.7258 7.04839 18.8226 5.17742ZM12.9677 18.1935H11.0323C10.6452 18.1935 10.4516 18 10.4516 17.6129V15C10.4516 13.3431 9.10847 12 7.45161 12H7.20968C6.95161 12 6.77419 11.8871 6.67742 11.6613C6.58065 11.4032 6.6129 11.1935 6.77419 11.0323L11.6129 6.19355C11.871 5.93548 12.129 5.93548 12.3871 6.19355L17.2258 11.0323C17.3871 11.1935 17.4194 11.4032 17.3226 11.6613C17.2258 11.8871 17.0484 12 16.7903 12H16.5484C14.8915 12 13.5484 13.3431 13.5484 15V17.6129C13.5484 18 13.3548 18.1935 12.9677 18.1935Z"
                                    />
                                </svg>
                                <p class="mb-2 text-xs font-medium">
                                    <template x-if="input.accept && input.accept.includes('video')">
                                        <span>{{ __('Upload Video') }}</span>
                                    </template>
                                    <template x-if="!input.accept || !input.accept.includes('video')">
                                        <span>{{ __('Upload Image') }}</span>
                                    </template>
                                </p>
                                <p class="file-name mb-0 text-4xs opacity-50">
                                    <template x-if="input.multiple">
                                        <span>{{ __('(Upload 1-3 images)') }}</span>
                                    </template>
                                    <template x-if="!input.multiple && input.accept && input.accept.includes('image')">
                                        <span>{{ __('(Only jpg, png accepted)') }}</span>
                                    </template>
                                    <template x-if="!input.multiple && input.accept && input.accept.includes('video')">
                                        <span>{{ __('(Video files accepted)') }}</span>
                                    </template>
                                </p>
                                <input
                                    class="hidden"
                                    :id="input.name.replace(/[\[\]]/g, '_')"
                                    :name="input.name + (input.multiple ? '[]' : '')"
                                    type="file"
                                    :accept="input.accept"
                                    :multiple="input.multiple"
                                    :data-required="input.required"
                                    ::data-exclude-media-manager="input.excludeMediaManager ? 'true' : undefined"
                                    @change="handleFileSelect(input.name.replace(/[\[\]]/g, '_'))"
                                />
                            </label>
                            <div
                                class="mt-1 text-xs text-foreground/60"
                                x-show="input.multiple"
                                x-data="{ fileCount: 0 }"
                            >
                                <span x-text="fileCount > 0 ? fileCount + ' file(s) selected' : ''"></span>
                            </div>
                        </div>
                    </template>

                    {{-- Range Input --}}
                    <template x-if="input.type === 'range'">
                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                <span x-text="input.label"></span>
                                <span
                                    class="text-foreground/60"
                                    x-text="': ' + (formValues[input.name] || input.default)"
                                ></span>
                            </label>
                            <x-forms.input
                                class="h-2 w-full cursor-pointer appearance-none rounded-lg !border-0 bg-foreground/10 !px-0 !py-0"
                                ::id="input.name"
                                ::name="input.name"
                                type="range"
                                ::min="input.min"
                                ::max="input.max"
                                ::step="input.step"
                                ::required="input.required"
                                x-model="formValues[input.name]"
                                label=""
                            />
                            <div class="mt-1 flex justify-between text-xs text-foreground/60">
                                <span x-text="input.min"></span>
                                <span x-text="input.max"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Advanced Options --}}
        <div
            x-show="advancedInputs.length > 0"
            x-cloak
        >
            <label class="lqd-input-label mb-3 flex items-center gap-2 text-2xs font-medium leading-none text-foreground transition">
                {{ __('Advanced Options') }}
            </label>

            <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                <template
                    x-for="(input, index) in pillInputs"
                    :key="'pill-' + index"
                >
                    <div
                        class="group/pill relative"
                        x-show="shouldShowInput(input)"
                    >
                        <x-dropdown.dropdown
                            class:dropdown="min-w-[245px] max-h-80 overflow-y-auto border-none bg-dropdown-background/90 px-2 py-4 shadow-[0_10px_15px_-3px_hsl(0_0%_0%/10%)] backdrop-blur-xl"
                            offsetY="0.25rem"
                        >
                            <x-slot:trigger
                                class="min-h-10 w-full !rounded-input"
                                variant="outline"
                                type="button"
                            >
                                <span x-show="getPillIcon(input) === 'clock'"><x-tabler-clock class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'volume'"><x-tabler-volume class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'speaker'"><x-tabler-device-speaker class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'ratio'"><x-tabler-aspect-ratio class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'resolution'"><x-tabler-device-desktop class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'sparkle'"><x-tabler-sparkles class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'wand'"><x-tabler-wand class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'dice'"><x-tabler-dice class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'ban'"><x-tabler-ban class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'text'"><x-tabler-align-left class="size-[18px] shrink-0" /></span>
                                <span x-show="getPillIcon(input) === 'settings'"><x-tabler-settings class="size-[18px] shrink-0" /></span>
                                <span
                                    class="truncate"
                                    x-text="getPillDisplayValue(input)"
                                ></span>
                            </x-slot:trigger>

                            <x-slot:dropdown>
                                <p
                                    class="mb-3 px-4 text-2xs font-medium opacity-50"
                                    x-text="input.label"
                                ></p>

                                <template x-if="input.type === 'select'">
                                    <div class="space-y-0.5">
                                        <template
                                            x-for="option in input.options"
                                            :key="option.value"
                                        >
                                            <button
                                                class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2.5 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
                                                :class="{ 'active': formValues[input.name] == option.value }"
                                                type="button"
                                                @click.prevent="formValues[input.name] = option.value; toggle(false)"
                                            >
                                                <span x-text="option.label"></span>

                                                <x-tabler-check
                                                    class="invisible ms-auto size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100"
                                                />
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="input.type === 'checkbox'">
                                    <div class="space-y-0.5">
                                        <button
                                            class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2.5 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
                                            :class="{ 'active': formValues[input.name] }"
                                            type="button"
                                            @click.prevent="formValues[input.name] = true; toggle(false)"
                                        >
                                            {{ __('On') }}

                                            <x-tabler-check
                                                class="invisible ms-auto size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100"
                                            />
                                        </button>
                                        <button
                                            class="group/trigger-btn flex w-full items-center gap-2.5 rounded-lg px-4 py-2.5 text-start text-xs text-heading-foreground transition hover:bg-foreground/5"
                                            :class="{ 'active': !formValues[input.name] }"
                                            type="button"
                                            @click.prevent="formValues[input.name] = false; toggle(false)"
                                        >
                                            {{ __('Off') }}

                                            <x-tabler-check
                                                class="invisible ms-auto size-5 shrink-0 opacity-0 transition group-[&.active]/trigger-btn:visible group-[&.active]/trigger-btn:opacity-100"
                                            />
                                        </button>
                                    </div>
                                </template>

                                <template x-if="input.type === 'number'">
                                    <x-forms.input
                                        class:container="px-4"
                                        ::name="input.name"
                                        type="number"
                                        ::min="input.min"
                                        ::max="input.max"
                                        ::step="input.step"
                                        ::placeholder="input.placeholder"
                                        x-model="formValues[input.name]"
                                    />
                                </template>

                                <template x-if="input.type === 'textarea'">
                                    <x-forms.input
                                        class:container="px-4"
                                        ::name="input.name"
                                        type="textarea"
                                        ::rows="3"
                                        ::placeholder="input.placeholder"
                                        x-model="formValues[input.name]"
                                    />
                                </template>

                            </x-slot:dropdown>
                        </x-dropdown.dropdown>

                        {{-- Hidden input for pill values (must be outside dropdown to stay in form DOM) --}}
                        <input
                            type="hidden"
                            :name="input.name"
                            :value="input.type === 'checkbox' ? (formValues[input.name] ? '1' : '0') : formValues[input.name]"
                        >
                    </div>
                </template>
            </div>
        </div>

        {{-- Submit Button --}}
        @if (\App\Helpers\Classes\Helper::appIsDemo())
            <x-button
                class="openai_generator_button w-full py-[18px]"
                onclick="toastr.info('This feature is disabled in the demo version.')"
                size="lg"
                type="button"
                x-show="selectedAction && selectedFeature"
            >
                {{ __('Generate') }}
            </x-button>
        @else
            <x-button
                class="openai_generator_button w-full py-[18px]"
                size="lg"
                type="submit"
                x-show="selectedAction && selectedFeature"
                ::disabled="submitting"
            >
                <span x-show="!submitting">{{ __('Generate') }}</span>
                <span
                    class="inline-flex items-center gap-2"
                    x-show="submitting"
                    x-cloak
                >
                    <span class="inline-block size-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    {{ __('Generating...') }}
                </span>
            </x-button>
        @endif

		<div
			x-init="$nextTick(() => {
				if (selectedFeature) {
					const durationFields = ['sora_seconds', 'duration', 'grok_video_duration', 'kling25turbo_duration', 'kling26pro_duration', 'kling_v3_duration'];
					let quantity = 1;
					for (const field of durationFields) {
						const val = parseInt(formValues[field]);
						if (val > 0) { quantity = val; break; }
					}
					$dispatch('generator-changed', { generator: selectedFeature, quantity: quantity });
				}
			})"
			x-effect="
				if (selectedFeature) {
					const durationFields = ['sora_seconds', 'duration', 'grok_video_duration', 'kling25turbo_duration', 'kling26pro_duration', 'kling_v3_duration'];
					let quantity = 1;
					for (const field of durationFields) {
						const val = parseInt(formValues[field]);
						if (val > 0) { quantity = val; break; }
					}
					$dispatch('generator-changed', { generator: selectedFeature, quantity: quantity, _force: Date.now() });
				}
			"
		>
			<x-cost-preview class="w-full justify-end" />
		</div>
    </form>
</x-card>
@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiVideoProForm', () => ({
                selectedAction: '',
                selectedSubModel: '',
                selectedFeature: '',
                formValues: {},
                models: {{ Js::from($models) }},
                modelDropdownOpen: false,
                activeModelHover: null,
                featureDropdownOpen: false,
                openSelect: null,
                enhancingPrompt: false,
                submitting: false,

                async enhancePrompt(inputName) {
                    const text = this.formValues[inputName];
                    if (!text || !text.trim()) {
                        toastr.warning('{{ __('Please enter a description first.') }}');
                        return;
                    }
                    this.enhancingPrompt = true;
                    try {
                        const response = await fetch('{{ route('dashboard.user.ai-video-pro.enhance-prompt') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                prompt: text
                            }),
                        });
                        const data = await response.json();
                        if (response.ok && data.result) {
                            this.formValues[inputName] = data.result;
                            toastr.success('{{ __('Prompt enhanced!') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Failed to enhance prompt.') }}');
                        }
                    } catch (e) {
                        toastr.error('{{ __('An error occurred while enhancing the prompt.') }}');
                    } finally {
                        this.enhancingPrompt = false;
                    }
                },

                init() {
                    const firstModel = Object.keys(this.models).find(key => this.models[key].isActive !== false);

                    if (firstModel) {
                        this.selectedAction = firstModel;
                        this.autoSelectSubModel();
                        this.autoSelectFeature();
                    }

                    this.loadSessionImage();
                },

                handleSubmit(e) {
                    e.preventDefault();

                    // Normalize checkbox values into hidden inputs
                    if (this.formValues && this.currentFeature?.inputs) {
                        const checkboxNames = this.currentFeature.inputs
                            .filter(i => i.type === 'checkbox')
                            .map(i => i.name);
                        checkboxNames.forEach(name => {
                            this.$el.querySelectorAll(`input[name="${name}"]`).forEach(el => el.remove());
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = name;
                            input.value = this.formValues[name] ? '1' : '0';
                            this.$el.appendChild(input);
                        });
                    }

                    // Validate required file inputs
                    const fileInputs = this.$el.querySelectorAll('input[type="file"][data-required="true"]');
                    let hasError = false;

                    fileInputs.forEach(input => {
                        const container = input.closest('[x-show]');
                        const isVisible = !container || container.style.display !== 'none';

                        if (isVisible && (!input.files || input.files.length === 0) && !hasError) {
                            hasError = true;

                            const labelElement = input.closest('.flex')?.querySelector('label.text-xs');
                            const labelText = labelElement?.textContent?.trim() || '{{ __('a file') }}';

                            toastr.error(`{{ __('Please select :file before generating.', ['file' => '${labelText}']) }}`);

                            const scrollTarget = input.closest('.flex') || input.closest('div');
                            scrollTarget?.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });

                            const dropZone = input.closest('label.lqd-filepicker-label');
                            if (dropZone) {
                                dropZone.style.borderColor = '#ef4444';
                                dropZone.style.backgroundColor = '#fef2f2';
                                setTimeout(() => {
                                    dropZone.style.borderColor = '';
                                    dropZone.style.backgroundColor = '';
                                }, 3000);
                            }
                        }
                    });

                    if (hasError) return false;

                    // AJAX submission
                    this.submitting = true;
                    const formData = new FormData(this.$el);

                    fetch(this.$el.getAttribute('action'), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json'
                            },
                            body: formData
                        })
                        .then(async (response) => {
                            const data = await response.json();

                            if (!response.ok) {
                                if (response.status === 422 && data.errors) {
                                    Object.values(data.errors).forEach(msgs => {
                                        toastr.error(Array.isArray(msgs) ? msgs[0] : msgs);
                                    });
                                } else {
                                    toastr.error(data.message || '{{ __('An error occurred.') }}');
                                }
                                return;
                            }

                            if (data.success) {
                                toastr.success(data.message);

                                if (data.video_id) {
                                    this.$dispatch('video-generated', {
                                        video_id: data.video_id
                                    });
                                }

                                this.resetFormInputs();
                            } else {
                                toastr.error(data.message || '{{ __('Generation failed.') }}');
                            }
                        })
                        .catch(() => {
                            toastr.error('{{ __('An error occurred. Please try again.') }}');
                        })
                        .finally(() => {
                            this.submitting = false;
                        });
                },

                resetFormInputs() {
                    this.initializeFormValues();
                    this.$el.querySelectorAll('input[type="file"]').forEach(input => {
                        input.value = '';
                        const label = input.closest('label.lqd-filepicker-label');
                        if (label) {
                            const fileNameEl = label.querySelector('.file-name');
                            if (fileNameEl) fileNameEl.textContent = '';
                        }
                    });
                },

                async loadSessionImage() {
                    const videoImageData = sessionStorage.getItem('videoImageData');
                    if (!videoImageData) return;

                    try {
                        const data = JSON.parse(videoImageData);
                        setTimeout(async () => {
                            const fileInput = this.$el.querySelector('input[type="file"][accept*="image"]');
                            if (fileInput) {
                                const response = await fetch(data.url);
                                const blob = await response.blob();
                                const file = new File([blob], data.fileName, {
                                    type: blob.type
                                });

                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(file);
                                fileInput.files = dataTransfer.files;
                                fileInput.dispatchEvent(new Event('change', {
                                    bubbles: true
                                }));

                                const label = fileInput.closest('label.lqd-filepicker-label');
                                if (label) {
                                    const fileNameElement = label.querySelector('.file-name');
                                    if (fileNameElement) {
                                        fileNameElement.innerHTML = `<span class="text-primary font-medium">${data.fileName}</span>`;
                                    }
                                }
                                toastr.success('Image loaded from photoshoot!');
                            }
                            sessionStorage.removeItem('videoImageData');
                        }, 500);
                    } catch (error) {
                        console.error('Failed to load pre-selected image:', error);
                        toastr.error('Failed to load selected image');
                        sessionStorage.removeItem('videoImageData');
                    }
                },

                get currentModel() {
                    return this.selectedAction && this.models[this.selectedAction] ? this.models[this.selectedAction] : null;
                },

                get subModels() {
                    return this.currentModel?.subModels || {};
                },

                get currentSubModel() {
                    return this.selectedSubModel && this.subModels[this.selectedSubModel] ? this.subModels[this.selectedSubModel] : null;
                },

                get features() {
                    if (!this.currentSubModel?.features) return [];
                    return Object.entries(this.currentSubModel.features).map(([key, value]) => ({
                        value: key,
                        label: value.label
                    }));
                },

                get currentFeature() {
                    if (!this.currentSubModel?.features || !this.selectedFeature) return null;
                    return this.currentSubModel.features[this.selectedFeature];
                },

                isPrimaryInput(input) {
                    if (input.type === 'file') return true;
                    if (input.type === 'textarea' && input.required) return true;
                    return false;
                },

                get currentInputs() {
                    if (!this.currentFeature?.inputs) return [];
                    return (this.currentFeature.inputs || []).filter(input => this.isPrimaryInput(input));
                },

                get advancedInputs() {
                    if (!this.currentFeature?.inputs) return [];
                    return (this.currentFeature.inputs || []).filter(input => !this.isPrimaryInput(input));
                },

                hasAdvancedInputs() {
                    return this.advancedInputs.length > 0;
                },

                get pillInputs() {
                    return this.advancedInputs;
                },

                get visiblePillInputs() {
                    return this.pillInputs.filter(input => this.shouldShowInput(input));
                },

                get shouldShowSubModels() {
                    const subModelKeys = Object.keys(this.subModels);
                    if (subModelKeys.length === 0) return false;
                    if (subModelKeys.length === 1 && subModelKeys[0].toLowerCase() === this.selectedAction.toLowerCase()) {
                        return false;
                    }
                    return subModelKeys.length > 1;
                },

                get shouldShowFeatures() {
                    return this.features.length > 1;
                },

                autoSelectSubModel() {
                    const subModelKeys = Object.keys(this.subModels);
                    if (subModelKeys.length > 0) {
                        this.selectedSubModel = subModelKeys[0];
                    }
                },

                autoSelectFeature() {
                    if (this.features.length > 0) {
                        this.selectedFeature = this.features[0].value;
                        this.initializeFormValues();
                    }
                },

                initializeFormValues() {
                    this.formValues = {};
                    const allInputs = this.currentFeature?.inputs || [];
                    allInputs.forEach(input => {
                        if (input.default !== undefined) {
                            this.formValues[input.name] = input.default;
                        }
                    });
                },

                shouldShowInput(input) {
                    if (!input.show_if) return true;
                    return this.formValues[input.show_if] === true;
                },

                selectModel(modelKey) {
                    const subModelKeys = Object.keys(this.models[modelKey]?.subModels || {});
                    if (subModelKeys.length <= 1) {
                        this.selectedAction = modelKey;
                        this.selectedSubModel = '';
                        this.selectedFeature = '';
                        this.autoSelectSubModel();
                        this.autoSelectFeature();
                        this.modelDropdownOpen = false;
                        this.activeModelHover = null;
                    } else {
                        this.activeModelHover = modelKey;
                    }
                },

                selectSubModelFromDropdown(modelKey, subModelKey) {
                    this.selectedAction = modelKey;
                    this.selectedSubModel = subModelKey;
                    this.selectedFeature = '';
                    this.autoSelectFeature();
                    this.modelDropdownOpen = false;
                    this.activeModelHover = null;
                },

                getSelectedModelLabel() {
                    if (!this.currentModel) return '{{ __('Select AI Model') }}';
                    let label = this.currentModel.label;
                    if (this.shouldShowSubModels && this.selectedSubModel) {
                        label += ' / ' + this.selectedSubModel;
                    }
                    return label;
                },

                getPillDisplayValue(input) {
                    const val = this.formValues[input.name];
                    if (input.type === 'checkbox') {
                        return val ? '{{ __('On') }}' : '{{ __('Off') }}';
                    }
                    if (input.options) {
                        const opt = input.options.find(o => o.value == val);
                        return opt ? opt.label : (val || input.default || '');
                    }
                    if (input.type === 'number') {
                        return val || input.label;
                    }
                    if (input.type === 'textarea') {
                        return val ? val.substring(0, 15) + (val.length > 15 ? '...' : '') : input.label;
                    }
                    return val || input.label || '';
                },

                getSelectedFeatureLabel() {
                    if (!this.selectedFeature || this.features.length === 0) return '{{ __('Select Feature') }}';
                    const f = this.features.find(f => f.value === this.selectedFeature);
                    return f ? f.label : '{{ __('Select Feature') }}';
                },

                getSelectLabel(input) {
                    const val = this.formValues[input.name];
                    if (input.options) {
                        const opt = input.options.find(o => o.value == val);
                        return opt ? opt.label : (val || input.placeholder || '{{ __('Select') }}');
                    }
                    return val || input.placeholder || '{{ __('Select') }}';
                },

                toggleSelect(name) {
                    this.openSelect = this.openSelect === name ? null : name;
                },

                getPillIcon(input) {
                    if (input.name === 'duration' || input.name === 'sora_seconds') return 'clock';
                    if (input.name === 'generate_audio') return 'volume';
                    if (input.name === 'keep_original_sound') return 'volume';
                    if (input.name === 'aspect_ratio' || input.name === 'sora_size') return 'ratio';
                    if (input.name === 'resolution') return 'resolution';
                    if (input.name === 'enhance_prompt') return 'sparkle';
                    if (input.name === 'auto_fix') return 'wand';
                    if (input.name === 'seed') return 'dice';
                    if (input.name === 'negative_prompt') return 'ban';
                    if (input.type === 'textarea') return 'text';
                    if (input.type === 'number') return 'dice';
                    return 'settings';
                }
            }));
        });
    </script>
@endpush
