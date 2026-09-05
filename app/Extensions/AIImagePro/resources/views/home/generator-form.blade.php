@pushOnce('css')
    <style>
        .lqd-adv-img-editor-form {
            interpolate-size: allow-keywords;
        }

        .lqd-adv-img-editor-form.is-sticky {
            position: fixed;
            margin: 0 auto;
            inset-inline-start: 1rem;
            inset-inline-end: calc(var(--body-padding, 0px) + 1rem);
            max-width: inherit;
            z-index: 50;
        }

        @media (min-width: 992px) {
            .lqd-adv-img-editor-form.is-sticky {
                inset-inline-start: calc(var(--navbar-width) + 1rem);
            }
        }
    </style>
@endPushOnce

{{-- generator form --}}
<div
    class="lqd-adv-img-editor-form-wrap mx-auto flex min-h-[85vh] max-w-[min(740px,100%)] flex-col justify-center py-10 sm:py-16"
    x-data="stickyFormWrapper"
    @scroll.window.throttle.16ms="onWindowScroll"
    @resize.window.debounce.150ms="onWindowResize"
>
    <div class="mb-8 text-center">
        <h2 class="mb-0 text-[30px] font-medium">
            <span class="block text-[0.7em]">
                <span class="opacity-50">
                    @lang('What would you like to do?')
                </span>
                👋
            </span>
            @lang('Create anything you can imagine.')
        </h2>
    </div>

    <div
        class="lqd-adv-img-editor-form-wrapper relative"
        :style="{ height: formHeight }"
    >
        <form
            class="lqd-adv-img-editor-form group/form relative rounded-[20px] border bg-background/90 shadow-2xl shadow-black/15 backdrop-blur-lg transition [&.is-sticky]:max-w-[min(740px,100%)]"
            id="submitForm"
            data-sticky-form
            :class="{ 'is-sticky': isFormSticky }"
            :style="formStyle"
            enctype="multipart/form-data"
            x-data="aiImageProGeneratorForm"
            x-ref="formElement"
            @submit.prevent="submitForm"
        >
            @csrf

            {{-- AI Model Dropdown --}}
            <div class="flex border-b px-[22px] py-4 transition-all group-[&.is-sticky]/form:py-2 sm:hidden">
                @include('ai-image-pro::includes.select-model-dropdown')
            </div>

            {{-- Main Prompt Textarea --}}
            <x-forms.input
                class:container="w-full rounded-t-[inherit]"
                class="w-full resize-none rounded-t-[inherit] border-none bg-transparent px-[22px] pt-4 !shadow-none !outline-none transition-all placeholder:truncate placeholder:text-foreground focus-visible:outline-none focus-visible:ring-0 group-[&.is-sticky]/form:h-16 sm:text-sm"
                id="prompt"
                name="prompt"
                type="textarea"
                required
                rows="3"
                x-ref="prompt"
                placeholder="{{ __('Describe the image you want to generate...') }}"
                @keydown.enter.prevent="if (!$event.shiftKey && !isSubmitting) submitForm()"
            />

            <div class="flex items-center gap-2.5 px-2 pb-4 transition-all group-[&.is-sticky]/form:pb-1 max-sm:overflow-hidden md:pe-3">
                <div class="flex grow items-center gap-1.5 p-1 max-sm:overflow-x-auto max-sm:overflow-y-hidden md:gap-3">
                    {{-- Dynamic Inputs --}}
                    <template
                        x-for="(input, index) in currentInputs"
                        :key="index"
                    >
                        <div x-show="shouldShowInput(input) && input.name !== 'prompt'">
                            {{-- Textarea Input --}}
                            <template x-if="input.type === 'textarea' && input.name !== 'prompt'">
                                <div>
                                    <label
                                        class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label"
                                        :for="input.name"
                                    >
                                        <span
                                            class="lqd-input-label-txt"
                                            x-text="input.label"
                                        ></span>
                                    </label>
                                    <x-forms.input
                                        ::id="input.name"
                                        ::name="input.name"
                                        type="textarea"
                                        size="lg"
                                        ::rows="input.rows || 4"
                                        ::placeholder="input.placeholder"
                                        ::required="input.required"
                                        x-model="formValues[input.name]"
                                        label=""
                                    />
                                </div>
                            </template>

                            {{-- Text Input --}}
                            <template x-if="input.type === 'text'">
                                <div>
                                    <label
                                        class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label"
                                        :for="input.name"
                                    >
                                        <span
                                            class="lqd-input-label-txt"
                                            x-text="input.label"
                                        ></span>
                                    </label>
                                    <x-forms.input
                                        ::id="input.name"
                                        ::name="input.name"
                                        type="text"
                                        size="lg"
                                        ::placeholder="input.placeholder"
                                        ::required="input.required"
                                        x-model="formValues[input.name]"
                                        label=""
                                    />
                                </div>
                            </template>

                            {{-- Image Input --}}
                            <template x-if="input.type === 'image'">
                                @include('ai-image-pro::home.image-input-helper')
                            </template>

                            {{-- File Input --}}
                            <template x-if="input.type === 'file'">
                                <div class="flex w-full flex-col gap-2">
                                    <label class="text-xs font-medium text-label">
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
                                        class="lqd-filepicker-label min-h-34 flex w-full cursor-pointer flex-col items-center justify-center rounded-card border-2 border-dashed border-foreground/10 bg-background text-center transition-colors hover:bg-background/80"
                                        :for="input.name.replace(/[\[\]]/g, '_')"
                                        @drop="dropHandler($event, input.name.replace(/[\[\]]/g, '_'))"
                                        @dragover.prevent
                                    >
                                        <div class="flex flex-col items-center justify-center py-6">
                                            <x-tabler-cloud-upload
                                                class="mb-4 size-11"
                                                stroke-width="1.5"
                                            />
                                            <p class="mb-1 text-sm font-semibold">
                                                {{ __('Drop your file here or browse.') }}
                                            </p>
                                            <p class="file-name mb-0 text-2xs">
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
                                        </div>
                                        <input
                                            class="hidden"
                                            :id="input.name.replace(/[\[\]]/g, '_')"
                                            :name="input.name + (input.multiple ? '[]' : '')"
                                            type="file"
                                            :accept="input.accept"
                                            :multiple="input.multiple"
                                            :required="input.required"
                                            @if (!auth()->check()) data-exclude-media-manager="true" @endif
                                            @change="handleFileSelect(input.name.replace(/[\[\]]/g, '_'))"
                                        />
                                    </label>
                                    {{-- Show selected files count for multiple uploads --}}
                                    <div
                                        class="mt-1 text-xs text-foreground/60"
                                        x-show="input.multiple"
                                        x-data="{ fileCount: 0 }"
                                    >
                                        <span x-text="fileCount > 0 ? fileCount + ' file(s) selected' : ''"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- Select Input --}}
                            <template x-if="input.type === 'select'">
                                <div class="inline-flex">
                                    <x-dropdown.dropdown
                                        class:dropdown-dropdown="backdrop-blur-lg rounded-dropdown"
                                        anchor="start"
                                        offsetY="8px"
                                    >
                                        <x-slot:trigger
                                            class="min-h-[38px] min-w-[38px] whitespace-nowrap px-4 text-2xs font-medium outline-2 outline-foreground/5 group-[&.lqd-is-active]/dropdown:bg-primary group-[&.lqd-is-active]/dropdown:text-primary-foreground group-[&.lqd-is-active]/dropdown:outline-primary max-sm:whitespace-nowrap"
                                            variant="outline"
                                        >
                                            <span
                                                x-text="(input.options || [])
											.find(opt => opt.value === (formValues[input.name] ?? input.default))
											?.label || ''"
                                            ></span>
                                        </x-slot:trigger>

                                        <x-slot:dropdown
                                            class="max-h-60 min-w-[min(240px,100vw)] !transform-none space-y-1 overflow-y-auto rounded-lg border-none bg-background/50 p-2 shadow-xl shadow-black/10"
                                        >
                                            <span
                                                class="block px-4 py-1.5 text-2xs font-medium opacity-50"
                                                x-show="input.label"
                                                x-text="input.label"
                                            ></span>
                                            <template
                                                x-for="option in input.options"
                                                :key="option.value"
                                            >
                                                <button
                                                    class="flex w-full items-center justify-between gap-2 rounded-lg px-4 py-3 text-start text-xs transition hover:bg-foreground/5 [&.selected]:bg-foreground/5"
                                                    type="button"
                                                    @click="formValues[input.name] = option.value; toggle('collapse')"
                                                    :class="{ 'selected': formValues[input.name] === option.value }"
                                                >
                                                    <span x-text="option.label"></span>
                                                    <template x-if="formValues[input.name] === option.value">
                                                        <x-tabler-check class="size-5 shrink-0" />
                                                    </template>
                                                </button>
                                            </template>
                                        </x-slot:dropdown>
                                    </x-dropdown.dropdown>

                                    {{-- Hidden input for form submission --}}
                                    <input
                                        type="hidden"
                                        ::name="input.name"
                                        x-model="formValues[input.name]"
                                    >
                                </div>
                            </template>

                            {{-- Modal Input (for Style Selection) --}}
                            <template x-if="input.type === 'modal'">
                                @include('ai-image-pro::home.image-style-modal')
                            </template>

                            {{-- Number Input --}}
                            <template x-if="input.type === 'number'">
                                <x-forms.input
                                    class="inline-flex w-24 rounded-lg border border-border px-4 py-2 text-xs font-medium transition-colors hover:bg-background/50"
                                    ::id="input.name"
                                    ::name="input.name"
                                    type="number"
                                    size="sm"
                                    ::min="input.min"
                                    ::max="input.max"
                                    ::step="input.step"
                                    ::placeholder="input.label"
                                    ::required="input.required"
                                    x-model="formValues[input.name]"
                                    label=""
                                />
                            </template>

                            {{-- Checkbox Input --}}
                            <template x-if="input.type === 'checkbox'">
                                <label
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border px-4 py-2 text-xs font-medium transition-colors hover:bg-background/50"
                                >
                                    <span x-text="input.label"></span>
                                    <x-forms.input
                                        class="bg-foreground/30 checked:bg-primary"
                                        type="checkbox"
                                        ::name="input.name"
                                        switcher
                                        ::checked="input.default"
                                        x-model="formValues[input.name]"
                                    />
                                </label>
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
                                    <input
                                        class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-foreground/10"
                                        type="range"
                                        :id="input.name"
                                        :name="input.name"
                                        :min="input.min"
                                        :max="input.max"
                                        :step="input.step"
                                        :required="input.required"
                                        x-model="formValues[input.name]"
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

                {{-- Hidden inputs for all dynamic form values (excluding style-model) --}}
                <template
                    x-for="(input, index) in (currentModel?.inputs || []).filter(inp => inp.name !== 'style' && inp.name !== 'prompt')"
                    :key="'hidden-' + input.name"
                >
                    <input
                        type="hidden"
                        :name="input.name"
                        :value="formValues[input.name] || input.default || ''"
                    >
                </template>

                {{-- Hidden input for form submission --}}
                <input
                    type="hidden"
                    name="model"
                    x-model="selectedModel"
                >
                <input
                    type="hidden"
                    name="engine"
                    :value="currentModel?.engine"
                >
                <input
                    type="hidden"
                    name="slug"
                    :value="currentModel?.slug"
                >

                <div class="ms-auto flex items-center gap-4">
                    {{-- AI Model Dropdown --}}
                    <div class="hidden sm:inline-flex">
                        @include('ai-image-pro::includes.select-model-dropdown')
                    </div>

                    {{-- SUBMIT BUTTON --}}
                    <button
                        class="group inline-grid size-11 place-items-center rounded-full bg-heading-foreground text-heading-background transition hover:scale-105 hover:shadow-lg hover:shadow-black/15 disabled:pointer-events-none disabled:opacity-50 disabled:hover:scale-100"
                        type="submit"
                        title="{{ __('Generate Image') }}"
                        :disabled="isSubmitting"
                    >
                        <svg
                            x-show="!isSubmitting"
                            width="15"
                            height="12"
                            viewBox="0 0 15 12"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M0 12V7.5L6 6L0 4.5V0L14.25 6L0 12Z" />
                        </svg>
                        <x-tabler-loader-2
                            class="size-5 animate-spin"
                            x-show="isSubmitting"
                            x-cloak
                        />
                    </button>
                </div>
            </div>
        </form>
    </div>

    <x-cost-preview class="w-full justify-end" />
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('stickyFormWrapper', () => ({
                isFormSticky: false,
                formElement: null,
                scrollRoot: null,
                threshold: 0,
                formTop: 0,
                formHeight: 'auto',
                externalScrollHandler: null,
                isMeasuring: false,

                get stickyOffset() {
                    const bodyPadding = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--body-padding')) || 0;
                    return (window.innerWidth <= 991 ? 10 : {{ auth()->check() ? 50 : 65 }}) + bodyPadding;
                },

                get formStyle() {
                    return this.isFormSticky ? `top:${this.stickyOffset}px;` : '';
                },

                init() {
                    this.formElement = this.$el.querySelector('[data-sticky-form]');

                    if (!this.formElement) {
                        return;
                    }

                    this.scrollRoot = this.resolveScrollRoot();

                    if (this.scrollRoot !== window) {
                        this.externalScrollHandler = () => {
                            this.updateStickyState();
                        };
                        this.scrollRoot.addEventListener('scroll', this.externalScrollHandler);
                    }

                    this.measureAndSync();
                },

                resolveScrollRoot() {
                    const theme = document.body.getAttribute('data-theme');
                    const stickyThemes = ['social-media-dashboard', 'bolt'];

                    if (stickyThemes.includes(theme)) {
                        return document.querySelector('.lqd-page-content-wrap') || window;
                    }

                    return window;
                },

                getScrollTop() {
                    if (this.scrollRoot === window) {
                        return window.scrollY || window.pageYOffset || 0;
                    }

                    return this.scrollRoot.scrollTop || 0;
                },

                measureAndSync() {
                    if (!this.formElement) {
                        return;
                    }

                    this.isMeasuring = true;
                    const wasSticky = this.isFormSticky;
                    this.isFormSticky = false;

                    requestAnimationFrame(() => {
                        this.$nextTick(() => {
                            const scrollTop = this.getScrollTop();
                            this.formHeight = `${this.formElement.offsetHeight}px`;
                            this.formTop = this.formElement.getBoundingClientRect().top + scrollTop;
                            this.threshold = Math.max(this.formTop - this.stickyOffset, 0);
                            this.isMeasuring = false;
                            this.isFormSticky = wasSticky;

                            this.updateStickyState();
                        });
                    });
                },

                updateStickyState() {
                    if (this.isMeasuring || !this.formTop) {
                        return;
                    }

                    const scrollTop = this.getScrollTop();
                    const wasSticky = this.isFormSticky;
                    const stickAt = this.threshold;
                    const unstickAt = Math.max(this.threshold - 8, 0);

                    if (this.isFormSticky) {
                        this.isFormSticky = scrollTop > unstickAt;
                    } else {
                        this.isFormSticky = stickAt > 0 && scrollTop >= stickAt;
                    }

                    if (!wasSticky && this.isFormSticky) {
                        this.formHeight = `${this.formElement.offsetHeight}px`;
                    }

                    if (wasSticky !== this.isFormSticky) {
                        this.closeDropdowns();
                    }
                },

                onWindowScroll() {
                    if (this.scrollRoot !== window) {
                        return;
                    }

                    this.updateStickyState();
                },

                onWindowResize() {
                    this.measureAndSync();
                },

                closeDropdowns() {
                    this.$el.querySelectorAll('.lqd-dropdown').forEach((dropdownElement) => {
                        const dropdownData = Alpine.$data(dropdownElement);

                        if (typeof dropdownData?.toggle === 'function') {
                            dropdownData.toggle('collapse');
                        }
                    });
                },
            }));

            Alpine.data('aiImageProGeneratorForm', () => ({
                selectedModel: '',
                formValues: {},
                models: @json($activeImageModels),
                isSubmitting: false,

                init() {
                    const modelKeys = Object.keys(this.models);
                    const savedModel = localStorage.getItem('aiImageProSelectedModel');

                    // Use saved model if it exists and is valid, otherwise use first model
                    if (savedModel && modelKeys.includes(savedModel)) {
                        this.selectedModel = savedModel;
                    } else if (modelKeys[0]) {
                        this.selectedModel = modelKeys[0];
                    }

                    this.initializeFormValues();

                    // Dispatch cost preview on init
                    this.$nextTick(() => {
                        const slug = this.currentModel?.slug || this.selectedModel;
                        if (slug) {
                            this.$dispatch('generator-changed', { generator: slug, quantity: parseInt(this.formValues['image_count'] || 1), _force: Date.now() });
                        }
                    });

                    // Watch for model changes and save to localStorage
                    this.$watch('selectedModel', (value) => {
                        if (value) {
                            localStorage.setItem('aiImageProSelectedModel', value);
                            const slug = this.currentModel?.slug || value;
                            this.$dispatch('generator-changed', { generator: slug, quantity: parseInt(this.formValues['image_count'] || 1), _force: Date.now() });
                        }
                    });

                    // Watch for image_count changes to update cost preview
                    this.$watch('formValues.image_count', (value) => {
                        const slug = this.currentModel?.slug || this.selectedModel;
                        if (slug) {
                            this.$dispatch('generator-changed', { generator: slug, quantity: parseInt(value || 1), _force: Date.now() });
                        }
                    });
                },

                get currentModel() {
                    return this.selectedModel && this.models[this.selectedModel] ? this.models[this.selectedModel] : null;
                },

                get currentInputs() {
                    if (!this.currentModel?.inputs) return [];
                    return this.currentModel.inputs.filter(input => !input.advanced);
                },

                get advancedInputs() {
                    if (!this.currentModel?.inputs) return [];
                    return this.currentModel.inputs.filter(input => input.advanced);
                },

                hasAdvancedInputs() {
                    return this.advancedInputs.length > 0;
                },

                initializeFormValues() {
                    this.formValues = {};
                    const allInputs = this.currentModel?.inputs || [];
                    allInputs.forEach(input => {
                        if (input.default !== undefined) {
                            this.formValues[input.name] = input.default;
                        } else {
                            this.formValues[input.name] = '';
                        }
                    });
                    // Add the model-specific values
                    if (this.currentModel) {
                        this.formValues.model = this.selectedModel;
                        this.formValues.engine = this.currentModel.engine;
                        this.formValues.slug = this.currentModel.slug;
                    }
                },

                shouldShowInput(input) {
                    if (!input.show_if) return true;
                    return this.formValues[input.show_if] === true;
                },

                getPromptInput() {
                    return this.currentInputs.find(input => input.name === 'prompt');
                },

                async submitForm() {
                    if (this.isSubmitting) return;

                    const form = this.$refs.formElement;
                    const formData = new FormData(form);

                    // Ensure model values are included
                    formData.set('model', this.selectedModel);
                    formData.set('engine', this.currentModel?.engine || '');
                    formData.set('slug', this.currentModel?.slug || '');

                    // Add all form values that might not be in the DOM
                    Object.entries(this.formValues).forEach(([key, value]) => {
                        if (value !== undefined && value !== null && value !== '') {
                            if (!formData.has(key)) {
                                formData.set(key, value);
                            }
                        }
                    });

                    this.isSubmitting = true;

                    try {
                        const response = await fetch('{{ route('ai-image-pro.generate') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            // Show success message
                            if (window.toastr) {
                                window.toastr.success(data.message || '{{ __('Image generation started!') }}');
                            }

                            // Notify the navbar to start polling for the new image
                            window.dispatchEvent(new CustomEvent('ai-image-generation-started', {
                                detail: {
                                    recordId: data.record_id
                                }
                            }));

                            // Clear the prompt field for a new generation
                            const promptInput = this.getPromptInput();
                            if (promptInput) {
                                this.formValues[promptInput.name] = '';
                            }

                            if (this.$refs.prompt) {
                                this.$refs.prompt.value = '';
                            }
                        } else {
                            // Show error message
                            if (window.toastr) {
                                window.toastr.error(data.message || '{{ __('Failed to start image generation.') }}');
                            }
                        }
                    } catch (error) {
                        console.error('Form submission error:', error);
                        if (window.toastr) {
                            window.toastr.error('{{ __('An error occurred. Please try again.') }}');
                        }
                    } finally {
                        this.isSubmitting = false;
                    }
                }
            }));
        });
    </script>
@endpush
