{{-- generator-form.blade --}}
<div class="z-3 mx-auto w-full lg:sticky lg:bottom-0 lg:z-[90] lg:w-[min(100%,700px)] lg:px-5 lg:pb-8">
    <x-cost-preview class="mt-0 mb-2 justify-end" />
    <form
        class="relative border border-transparent bg-background transition dark:border-border max-lg:border-0 max-lg:border-t lg:rounded-[20px] lg:shadow-2xl lg:shadow-black/15"
        id="chatImageProForm"
        enctype="multipart/form-data"
        x-data="aiImageProChatGeneratorForm"
    >
        @csrf

        {{-- Main Prompt Textarea --}}
        <x-forms.input
            class:container="w-full"
            class="w-full resize-none rounded-none border-none bg-transparent bg-none px-[22px] pt-4 placeholder:text-foreground focus:!border-transparent focus:!ring-0 focus-visible:outline-none focus-visible:ring-0 sm:text-sm md:px-7"
            id="prompt"
            name="prompt"
            type="textarea"
            required
            rows="2"
            x-ref="prompt"
            placeholder="{{ __('Describe the image you want to generate...') }}"
        />

        <div class="flex items-center gap-2.5 px-2 pb-4 max-sm:overflow-hidden md:px-5">
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
                                        class="min-h-[38px] min-w-[38px] px-4 text-center text-2xs font-medium outline-2 outline-foreground/5 group-[&.lqd-is-active]/dropdown:bg-primary group-[&.lqd-is-active]/dropdown:text-primary-foreground group-[&.lqd-is-active]/dropdown:outline-primary max-sm:whitespace-nowrap"
                                        variant="outline"
                                    >
                                        <span
                                            x-text="(input.options || [])
										.find(opt => opt.value === (formValues[input.name] ?? input.default))
										?.label || ''"></span>
                                    </x-slot:trigger>

                                    <x-slot:dropdown
                                        class="max-h-60 min-w-[min(240px,100vw)] space-y-1 overflow-y-auto rounded-lg border-none bg-background/50 p-2 shadow-xl shadow-black/10"
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
                x-for="(input, index) in (currentModel?.inputs || []).filter(inp => inp.name !== 'style' && inp.name !== 'prompt' && inp.type !== 'image')"
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
                id="chat_id"
                type="hidden"
                name="chat_id"
                value="{{ isset($chat) ? $chat->id : '' }}"
            >
            <input
                id="chatType"
                type="hidden"
                name="chatType"
                value="chatPro-image"
            >
            <input
                type="hidden"
                name="model"
                :value="$store.chatsV2?.selectedModel || ''"
            >
            <input
                type="hidden"
                name="engine"
                :value="$store.chatsV2?.currentModel?.engine || ''"
            >
            <input
                type="hidden"
                name="slug"
                :value="$store.chatsV2?.currentModel?.slug || ''"
            >
            <input
                id="reimagine_image_url"
                type="hidden"
                name="reimagine_image_url"
                value=""
            >
            <input
                id="reimagine_prompt"
                type="hidden"
                name="reimagine_prompt"
                value=""
            >

            <div class="ms-auto flex items-center gap-4">
                {{-- SUBMIT BUTTON --}}
                <button
                    class="group !inline-grid size-11 place-items-center rounded-full bg-heading-foreground text-heading-background transition before:!content-none hover:scale-105 hover:shadow-lg hover:shadow-black/15 disabled:pointer-events-none disabled:opacity-50"
                    id="send_message_button"
                    type="submit"
                    title="{{ __('Generate Image') }}"
                >
                    <svg
                        class="group-[&.submitting]:hidden"
                        width="15"
                        height="12"
                        viewBox="0 0 15 12"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path d="M0 12V7.5L6 6L0 4.5V0L14.25 6L0 12Z" />
                    </svg>
                    <x-tabler-loader-2 class="hidden size-5 animate-spin group-[&.submitting]:block" />
                </button>
            </div>
        </div>
    </form>
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiImageProChatGeneratorForm', () => ({
                formValues: {},
                editImageUrl: '',
                get currentModel() {
                    return Alpine.store('chatsV2')?.currentModel || null;
                },
                get selectedModel() {
                    return Alpine.store('chatsV2')?.selectedModel || '';
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
                pendingImageData: null,
                init() {
                    // Check for pending image from AI Image Pro "Edit with Assistant"
                    this.checkPendingImage();

                    // Watch for currentModel changes to load image when ready
                    this.$watch('currentModel', (newModel) => {
                        if (newModel && this.pendingImageData) {
                            this.$nextTick(() => {
                                // Give the template time to render the image input
                                setTimeout(() => this.loadPendingImage(), 100);
                            });
                        }
                    });

                    // Dispatch quantity changes for cost preview
                    this.$watch('formValues.image_count', (value) => {
                        if (value && this.selectedModel) {
                            this.$dispatch('generator-changed', {
                                generator: this.selectedModel,
                                quantity: parseInt(value) || 1,
                            });
                        }
                    });
                },
                checkPendingImage() {
                    try {
                        const pendingData = sessionStorage.getItem('pendingImageForChatAssistant');
                        if (!pendingData) return;

                        this.pendingImageData = JSON.parse(pendingData);
                        sessionStorage.removeItem('pendingImageForChatAssistant');

                        // If currentModel is already set, load immediately
                        if (this.currentModel) {
                            this.$nextTick(() => {
                                setTimeout(() => this.loadPendingImage(), 100);
                            });
                        }
                    } catch (error) {
                        console.error('Failed to check pending image:', error);
                    }
                },
                loadPendingImage() {
                    if (!this.pendingImageData) return;

                    const imageData = this.pendingImageData;
                    this.pendingImageData = null; // Clear after loading

                    // Set the prompt
                    if (imageData.prompt) {
                        const promptInput = this.getPromptInput();
                        if (promptInput) {
                            this.formValues[promptInput.name] = imageData.prompt;
                        } else if (this.formValues.hasOwnProperty('prompt')) {
                            this.formValues['prompt'] = imageData.prompt;
                        }
                    }

                    // Load the image into the image input helper
                    if (imageData.url) {
                        window.dispatchEvent(new CustomEvent('load-image-from-url', {
                            detail: {
                                url: imageData.url,
                                name: imageData.name || 'image.png'
                            }
                        }));
                    }

                    if (window.toastr) {
                        window.toastr.success('{{ __('Image and prompt loaded! You can now edit and regenerate.') }}');
                    }
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
                }
            }))
        });
    </script>
@endpush
