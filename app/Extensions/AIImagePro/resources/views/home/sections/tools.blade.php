@php
    use App\Helpers\Classes\MarketplaceHelper;

    $advancedImageRegistered = MarketplaceHelper::isRegistered('advanced-image');
    $creativeSuiteRegistered = MarketplaceHelper::isRegistered('creative-suite');
    $aiChatProImageChatRegistered = MarketplaceHelper::isRegistered('ai-chat-pro-image-chat');
    $socialMediaRegistered = MarketplaceHelper::isRegistered('social-media');

    $tools_list = url('/vendor/ai-image-pro/templates/tools.json?v=' . time());
@endphp

<div class="lqd-cs-templates mb-20">
    <div class="container max-w-6xl p-0">
        {{-- Tools Grid --}}
        <div class="gap-5 max-sm:flex max-sm:snap-x max-sm:gap-4 max-sm:overflow-x-auto max-sm:pb-4 sm:grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">

            {{-- Image Editor Tool --}}
            @if ($advancedImageRegistered)
                <div class="snap-start max-sm:w-1/2 max-sm:shrink-0 max-sm:grow-0 max-sm:basis-auto">
                    <div
                        class="group relative h-[300px] w-full overflow-hidden rounded-2xl transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/5"
                        :intensity="1"
                        :perspective="1400"
                    >
                        <span
                            class="absolute inset-0 rounded-2xl bg-[#F0C8FF]"
                            data-depth="0.25"
                        ></span>
                        <p
                            class="relative z-1 m-0 px-7 py-5 text-xs font-medium text-black"
                            data-depth="0.5"
                        >
                            {{ __('Image Editor') }}
                        </p>

                        <div
                            class="absolute bottom-7 end-0 z-1 w-[calc(100%-1.75rem)]"
                            data-depth="0.75"
                        >
                            <img
                                class="max-h-[210px] w-full rounded-s-[10px] object-cover object-top transition-all"
                                src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p1.png') }}"
                                alt=""
                                aria-hidden="true"
                            >
                        </div>

                        <div
                            class="absolute bottom-10 end-0 z-2"
                            data-depth="1.25"
                        >
                            {{-- blade-formatter-disable --}}
							<svg class="transition origin-right rtl:origin-left group-hover:scale-110" width="170" height="173" viewBox="0 0 170 173" fill="none" xmlns="http://www.w3.org/2000/svg"> <rect x="8.69213" y="9.25493" width="162.122" height="154" rx="2.5" fill="#23AAE3" fill-opacity="0.4" stroke="#219FE6"/> <rect x="169.658" y="7.25493" width="2.593" height="4" fill="white" stroke="#219FE6"/> <rect x="169.658" y="161.255" width="2.593" height="4" fill="white" stroke="#219FE6"/> <rect x="7.25493" y="160.255" width="2.593" height="4" fill="white" stroke="#219FE6"/> <rect x="7.25493" y="7.25493" width="2.593" height="4" fill="white" stroke="#219FE6"/> <path d="M110.429 3.79393L113.824 0.186026C114.058 -0.0620086 114.452 -0.0620086 114.685 0.186026L118.081 3.79393C118.336 4.06503 118.144 4.50985 117.772 4.50985H110.738C110.366 4.50985 110.174 4.06503 110.429 3.79393Z" fill="white"/> <path d="M3.79393 87.0811L0.186026 83.6855C-0.0620086 83.452 -0.0620086 83.0579 0.186026 82.8244L3.79393 79.4287C4.06503 79.1736 4.50985 79.3658 4.50985 79.738V86.7718C4.50985 87.1441 4.06503 87.3363 3.79393 87.0811Z" fill="white"/> <path d="M113.081 168.716L109.685 172.324C109.452 172.572 109.058 172.572 108.824 172.324L105.429 168.716C105.174 168.445 105.366 168 105.738 168H112.772C113.144 168 113.336 168.445 113.081 168.716Z" fill="white"/> </svg>
							{{-- blade-formatter-enable --}}
                        </div>

                        <a
                            class="absolute inset-0 z-5"
                            data-depth="1.5"
                            href="#image-editor-modal"
                            @click.prevent="(function(){ var el = document.getElementById('image-editor-modal'); if (el) try { Alpine.$data(el).modalOpen = true; } catch (_) {} })()"
                        ></a>
                    </div>

                    <x-modal
                        class:modal-content="lg:px-10 p-5 w-[min(calc(100%-2rem),1140px)]"
                        class:modal-backdrop=" backdrop-blur bg-black/50"
                        class:modal-body="pt-5 px-0"
                        class:modal-head="px-0 py-1.5"
                        id="image-editor-modal"
                    >
                        <x-slot:title>
                            <span class="modal-title m-0 text-[12px] font-medium">
                                {{ __('Pick a Tool') }}
                            </span>
                        </x-slot:title>

                        <x-slot:modal>
                            @include('ai-image-pro::home.sections.partials.modals.editor')
                        </x-slot:modal>
                    </x-modal>
                </div>
            @endif

            {{-- Marketing Tool --}}
            @if ($creativeSuiteRegistered)
                <div class="snap-start max-sm:w-1/2 max-sm:shrink-0 max-sm:grow-0 max-sm:basis-auto">
                    <div
                        class="group relative h-[300px] w-full overflow-hidden rounded-2xl transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/5"
                        :intensity="1"
                        :perspective="1400"
                    >
                        <span
                            class="absolute inset-0 rounded-2xl bg-[#C1F6EC]"
                            data-depth="0.25"
                        ></span>
                        <p
                            class="relative z-1 m-0 px-7 py-5 text-xs font-medium text-black"
                            data-depth="0.5"
                        >
                            {{ __('Marketing') }}
                        </p>

                        <div
                            class="absolute inset-x-0 bottom-0 z-2 grid grid-cols-1 max-md:top-20"
                            data-depth="0.26"
                        >
                            <div
                                class="relative z-2 col-start-1 col-end-1 row-start-1 row-end-1 -mt-3 aspect-square w-[72%] self-start justify-self-center"
                                data-depth="0.25"
                            >
                                <div class="h-full w-full rotate-[9deg] rounded-2xl bg-[#CBD8FF] transition group-hover:-translate-y-3 group-hover:rotate-[13deg]"></div>
                            </div>
                            <div
                                class="relative z-2 col-start-1 col-end-1 row-start-1 row-end-1 -mt-3 aspect-square w-[67%] self-start justify-self-center"
                                data-depth="0.35"
                            >
                                <div class="h-full w-full rotate-[-4deg] rounded-2xl bg-white transition group-hover:-translate-y-2 group-hover:rotate-[-9deg]"></div>
                            </div>
                            <div
                                class="relative z-3 col-start-1 col-end-1 row-start-1 row-end-1 w-full self-end rounded-2xl px-4"
                                data-depth="0.45"
                            >
                                <img
                                    class="w-full rounded-t-md transition-all duration-300 group-hover:rounded-b-md group-hover:shadow-xl group-hover:shadow-black/10"
                                    src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p2.png') }}"
                                    alt=""
                                    loading="lazy"
                                >
                            </div>
                        </div>

                        <a
                            class="absolute inset-0 z-5"
                            data-depth="1.5"
                            href="#marketing-modal"
                            @click.prevent="(function(){ var el = document.getElementById('marketing-modal'); if (el) try { Alpine.$data(el).modalOpen = true; } catch (_) {} })()"
                        ></a>
                    </div>

                    <x-modal
                        class:modal-content="lg:px-10 p-5 w-[min(calc(100%-2rem),1140px)]"
                        class:modal-backdrop=" backdrop-blur bg-black/50"
                        class:modal-body="pt-5 px-0"
                        class:modal-head="px-0 py-1.5"
                        id="marketing-modal"
                    >
                        <x-slot:title>
                            <span class="modal-title m-0 text-[12px] font-medium">
                                {{ __('Pick a Template') }}
                            </span>
                        </x-slot:title>

                        <x-slot:modal>
                            @include('ai-image-pro::home.sections.partials.modals.marketing')
                        </x-slot:modal>
                    </x-modal>
                </div>
            @endif

            {{-- Assistant Tool --}}
            @if ($aiChatProImageChatRegistered)
                <div class="snap-start max-sm:w-1/2 max-sm:shrink-0 max-sm:grow-0 max-sm:basis-auto">
                    <div
                        class="group relative h-[300px] w-full overflow-hidden rounded-2xl transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/5"
                        :intensity="1"
                        :perspective="1400"
                    >
                        <span
                            class="absolute inset-0 rounded-2xl bg-[#CAE6F1]"
                            data-depth="0.25"
                        ></span>
                        <p
                            class="relative z-1 m-0 px-7 py-5 text-xs font-medium text-black"
                            data-depth="0.5"
                        >
                            {{ __('Assistant') }}
                        </p>

                        <div
                            class="absolute inset-x-0 bottom-0 top-14 z-2 grid grid-cols-1"
                            data-depth="0.3"
                        >
                            <div
                                class="relative z-3 col-start-1 col-end-1 row-start-1 row-end-1 h-full w-full self-end overflow-hidden ps-3.5"
                                data-depth="0.3"
                            >
                                <img
                                    class="h-full w-full rounded-ee-2xl rounded-ss-[10px] object-cover transition"
                                    src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p3.png') }}"
                                    alt=""
                                    loading="lazy"
                                >
                                <div
                                    class="absolute -end-5 bottom-10 start-7 z-4 rounded-full bg-black/15 p-5 backdrop-blur-xl transition group-hover:scale-105"
                                    data-depth="0.5"
                                >
                                    <p
                                        class="-me-3 mb-0 truncate whitespace-nowrap rounded-full border-2 border-current px-2.5 py-1.5 text-xs font-medium leading-none text-[#4EFF6C] transition group-hover:scale-105"
                                        data-depth="0.5"
                                    >
                                        {{ __('put bottle in her hand') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <a
                            class="absolute inset-0 z-5"
                            data-depth="1.5"
                            href="/ai-chat-image/chat"
                        ></a>
                    </div>
                </div>
            @endif

            <div
                class="snap-start max-sm:w-1/2 max-sm:shrink-0 max-sm:grow-0 max-sm:basis-auto"
                x-data="toolsTemplates('{{ $tools_list }}')"
            >
                <div
                    class="group relative h-[300px] w-full overflow-hidden rounded-2xl transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/5"
                    :intensity="1"
                    :perspective="1400"
                >
                    <span
                        class="absolute inset-0 rounded-2xl bg-[#F2F3F5]"
                        data-depth="0.25"
                    ></span>
                    <p
                        class="relative z-1 m-0 px-7 py-5 text-xs font-medium text-black"
                        data-depth="0.5"
                    >
                        {{ __('Tools') }}
                    </p>

                    <div
                        class="absolute end-0 top-[75px] z-2 w-[calc(100%-2.5rem)]"
                        data-depth="0.75"
                    >
                        <img
                            class="w-full rounded-lg border-4 border-white"
                            src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p4-2.png') }}"
                            alt=""
                            loading="lazy"
                        >
                    </div>

                    <div
                        class="absolute inset-x-0 bottom-0 top-0 z-3 flex items-end justify-start overflow-hidden"
                        data-depth="1.15"
                    >
                        <img
                            class="ms-[12.5%] max-h-full w-[150%] max-w-none origin-right object-contain transition group-hover:scale-105 rtl:origin-left"
                            src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p4-1.png') }}"
                            alt=""
                            loading="lazy"
                        >
                    </div>

                    <a
                        class="absolute inset-0 z-5"
                        data-depth="1.5"
                        href="#tools-modal"
                        @click.prevent="(function(){ var el = document.getElementById('tools-modal'); if (el) try { Alpine.$data(el).modalOpen = true; } catch (_) {} })()"
                    ></a>
                </div>

                {{-- Tools --}}
                <x-modal
                    class:modal-content="lg:px-10 p-5 w-[min(calc(100%-2rem),1140px)]"
                    class:modal-backdrop=" backdrop-blur bg-black/50"
                    class:modal-body="pt-5 px-0 pb-0"
                    class:modal-head="px-0 py-1.5"
                    class:modal-title="flex items-center gap-2"
                    id="tools-modal"
                >
                    <x-slot:title>
                        <x-button
                            class="size-8 rounded-lg p-0"
                            x-show="selectedTemplate"
                            @click.prevent="selectedTemplate = null"
                            title="{{ __('Back') }}"
                            variant="ghost"
                            x-cloak
                        >
                            <x-tabler-arrow-left class="size-4 rtl:rotate-180" />
                        </x-button>
                        <span
                            class="modal-title m-0 text-[12px] font-medium"
                            x-text="selectedTemplate?.name ?? '{{ __('Pick a Tool') }}'"
                        ></span>
                    </x-slot:title>

                    <x-slot:modal>
                        @include('ai-image-pro::home.sections.partials.modals.tools')
                    </x-slot:modal>
                </x-modal>
            </div>

            {{-- Social Media Tool --}}
            <div class="snap-start max-sm:w-1/2 max-sm:shrink-0 max-sm:grow-0 max-sm:basis-auto">
                <div
                    class="group relative h-[300px] w-full overflow-hidden rounded-2xl transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/5"
                    :intensity="1"
                    :perspective="1400"
                >
                    <span
                        class="absolute inset-0 rounded-2xl bg-[#FFDFA9]"
                        data-depth="0.25"
                    ></span>
                    <p
                        class="relative z-1 m-0 px-7 py-5 text-xs font-medium text-black"
                        data-depth="0.5"
                    >
                        {{ __('Social Media') }}
                    </p>

                    <div
                        class="absolute inset-x-0 bottom-0 top-[75px] overflow-hidden ps-8"
                        data-depth="0.75"
                    >
                        <img
                            class="w-full rounded-ss-[10px]"
                            src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p5-1.png') }}"
                            alt=""
                            loading="lazy"
                        >
                    </div>

                    <div
                        class="absolute bottom-5 start-4"
                        data-depth="1.2"
                    >
                        <img
                            class="transition group-hover:scale-110"
                            src="{{ custom_theme_url('/vendor/ai-image-pro/previews/p5-2.png') }}"
                            alt=""
                            loading="lazy"
                            width="67"
                            height="69.5"
                        >
                    </div>

                    <a
                        class="absolute inset-0 z-5"
                        data-depth="1.5"
                        href="#social-media-modal"
                        @click.prevent="(function(){ var el = document.getElementById('social-media-modal'); if (el) try { Alpine.$data(el).modalOpen = true; } catch (_) {} })()"
                    ></a>
                </div>

                <x-modal
                    class:modal-content="lg:px-10 p-5 w-[min(calc(100%-2rem),1140px)]"
                    class:modal-backdrop=" backdrop-blur bg-black/50"
                    class:modal-body="pt-5 px-0 pb-0"
                    class:modal-head="px-0 py-1.5"
                    id="social-media-modal"
                >
                    <x-slot:title>
                        <span class="modal-title m-0 text-[12px] font-medium">
                            {{ __('Pick a Template') }}
                        </span>
                    </x-slot:title>

                    <x-slot:modal>
                        @include('ai-image-pro::home.sections.partials.modals.social')
                    </x-slot:modal>
                </x-modal>
            </div>

        </div>

        {{-- More Tools Button --}}
        @if ($aiChatProImageChatRegistered)
            <div class="mt-10 flex justify-center">
                <x-button
                    class="rounded-xl text-sm leading-relaxed outline-2"
                    variant="outline"
                    hover-variant="primary"
                    href="{{ route('ai-chat-image.index') }}"
                >
                    {{ __('More tools') }}
                    <x-tabler-arrow-right class="size-4 transition group-hover:translate-x-1 rtl:rotate-180" />
                </x-button>
            </div>
        @endif
    </div>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('toolsTemplates', (templatesListUrl) => ({
                templatesList: [],
                loadingTemplatesFailed: false,
                templatesListUrl: templatesListUrl,
                selectedCategory: 'all',
                _selectedTemplate: null,
                formData: {},
                uploadedFiles: {},
                loading: false,

                get selectedTemplate() {
                    return this._selectedTemplate;
                },

                set selectedTemplate(template) {
                    this._selectedTemplate = template;
                },

                init() {
                    this.fetchTemplates();
                },

                fetchTemplates() {
                    this.loading = true;
                    this.loadingTemplatesFailed = false;

                    fetch(this.templatesListUrl)
                        .then(res => res.json())
                        .then(data => this.templatesList = data)
                        .catch(() => this.loadingTemplatesFailed = true)
                        .finally(() => this.loading = false);
                },

                retryFetch() {
                    this.loadingTemplatesFailed = false;
                    this.fetchTemplates();
                },

                get categories() {
                    if (!this.templatesList.length) return ['all'];
                    const cats = new Set(['all']);
                    this.templatesList.forEach(t => {
                        if (t.category) cats.add(t.category);
                    });
                    return Array.from(cats);
                },

                get filteredTemplates() {
                    if (this.selectedCategory === 'all') {
                        return this.templatesList;
                    }
                    return this.templatesList.filter(t => t.category === this.selectedCategory);
                },

                getCategoryCount(category) {
                    if (category === 'all') return this.templatesList.length;
                    return this.templatesList.filter(t => t.category === category).length;
                },

                hasInputValue(value) {
                    return value !== undefined && value !== null && value !== '';
                },

                getInputDefaultValue(input) {
                    if (input.type !== 'select') {
                        return '';
                    }

                    const defaultOption = input.options?.find(option => option.selected) || input.options?.[0];
                    return defaultOption?.value ?? '';
                },

                getInputValue(input) {
                    const currentValue = this.formData[input.key];
                    if (this.hasInputValue(currentValue)) {
                        return currentValue;
                    }

                    const defaultValue = this.getInputDefaultValue(input);
                    if (this.hasInputValue(defaultValue)) {
                        this.formData[input.key] = defaultValue;
                    }

                    return defaultValue;
                },

                initializeTemplateForm(template) {
                    this.formData = {};
                    this.uploadedFiles = {};

                    template?.data?.inputs?.forEach(input => {
                        this.formData[input.key] = this.getInputDefaultValue(input);
                        if (input.type === 'file') {
                            const fileInput = this.$el.querySelector(`input[name="${input.key}"]`);
                            if (fileInput) {
                                fileInput.value = '';
                            }
                        }
                    });
                },

                openTemplate(template) {
                    this.selectedTemplate = template;
                    this.initializeTemplateForm(template);
                },

                handleFileUpload(event, key) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.uploadedFiles[key] = {
                                file: file,
                                name: file.name,
                                preview: e.target.result
                            };
                            this.formData[key] = file.name;
                        };
                        reader.readAsDataURL(file);
                    }
                },

                removeFile(key) {
                    delete this.uploadedFiles[key];
                    delete this.formData[key];

                    const fileInput = this.$el.querySelector(`input[name="${key}"]`);
                    if (fileInput) {
                        fileInput.value = '';
                    }
                },

                handleSubmit(event) {
                    const errors = [];
                    this.selectedTemplate?.data.inputs.forEach(input => {
                        const value = input.type === 'file'
                            ? this.uploadedFiles[input.key]?.file
                            : this.getInputValue(input);

                        if (input.required && !this.hasInputValue(value)) {
                            errors.push(`${input.label} is required`);
                        }
                    });

                    if (errors.length > 0) {
                        toastr.error(errors.join('\n'));
                        return;
                    }

                    const submitButton = event.submitter;
                    const originalText = submitButton.textContent;
                    submitButton.disabled = true;

                    const formData = new FormData();
                    formData.append('tool_id', this.selectedTemplate.id);
                    formData.append('tool_name', this.selectedTemplate.name);

                    this.selectedTemplate.data.inputs.forEach(input => {
                        if (input.type === 'file') {
                            const uploadedFile = this.uploadedFiles[input.key];
                            if (uploadedFile?.file) {
                                formData.append(input.key, uploadedFile.file);
                            }
                        } else {
                            const value = this.getInputValue(input);
                            if (this.hasInputValue(value)) {
                                formData.append(input.key, value);
                            }
                        }
                    });

                    fetch('/ai-image-pro/tools/generate', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                            },
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                toastr.success(data.message || 'Image generation started!');

                                window.dispatchEvent(new CustomEvent('ai-image-generation-started', {
                                    detail: {
                                        tool: this.selectedTemplate?.name,
                                        timestamp: Date.now()
                                    }
                                }));

                                this.initializeTemplateForm(this.selectedTemplate);
                            } else {
                                toastr.error(data.message || 'Failed to generate image');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            toastr.error('An error occurred. Please try again.');
                        })
                        .finally(() => {
                            submitButton.disabled = false;
                            submitButton.textContent = originalText;
                        });
                },

                async enhancePrompt(inputKey, event) {
                    const isAuthenticated = {{ auth()->check() ? 'true' : 'false' }};
                    if (!isAuthenticated) {
                        toastr.error('{{ __('You must be logged in to use this feature.') }}');
                        return;
                    }

                    const button = event.currentTarget;
                    const container = button.closest('.relative');
                    const textarea = container.querySelector('textarea');
                    const prompt = textarea?.value;

                    const spinner = button.querySelector('#lds-dual-ring2');
                    const generateIcon = button.querySelector('.generate');

                    if (spinner) spinner.classList.remove('hidden');
                    if (generateIcon) generateIcon.classList.add('hidden');

                    const url = new URL('{{ route('dashboard.user.ai-image-pro.enhance-prompt') }}');
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            prompt: prompt,
                            tool_type: this.selectedTemplate?.name
                        }),
                    });

                    const data = await response.json();
                    if (data.enhanced_prompt) {
                        textarea.value = data.enhanced_prompt;
                        this.formData[inputKey] = data.enhanced_prompt;
                    } else {
                        toastr.error('{{ __('Failed to enhance prompt. Please try again.') }}');
                    }

                    if (spinner) spinner.classList.add('hidden');
                    if (generateIcon) generateIcon.classList.remove('hidden');
                }
            }));
        });
    </script>
@endPushOnce
