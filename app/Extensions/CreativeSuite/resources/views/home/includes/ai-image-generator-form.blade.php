@php
    $style = $style ?? 'default';
@endphp

<form
    @class([
        'lqd-adv-img-editor-form',
        'relative flex min-h-16 w-full flex-wrap rounded-xl bg-background/60 p-4 font-medium shadow-lg shadow-black/5 dark:bg-input-background/10 sm:text-2xs lg:flex-nowrap lg:rounded-full lg:p-0' =>
            $style === 'default',
        'flex flex-col' => $style === 'expanded',
    ])
    method="POST"
    action="{{ route('dashboard.user.openai.output') }}"
    enctype="multipart/form-data"
    x-data="advancedImageEditorForm"
    @submit.prevent="onFormSubmit"
>
    @csrf

    @if ($style === 'expanded')
        <x-forms.input
            class:container="grow max-sm:w-full"
            class="h-[90px] resize-none rounded-none !border-none !bg-transparent px-6 py-4 !outline-none !ring-0 placeholder:text-foreground/50 disabled:opacity-50 sm:text-sm"
            id="description"
            type="textarea"
            name="description"
            placeholder="{{ __('Describe your idea or select a pre-defined prompt') }}"
            x-model="prompt"
            x-ref="promptInput"
            placeholder="{{ __('e.g. Create a clean, cozy Instagram Story for a coffee shop promotion (“Buy 1 Get 1 Free”). Use cozy coffee visuals and bold readable text)') }}"
            required
            @keydown.enter="if ( !$event.shiftKey ) { $event.preventDefault(); $el.form.requestSubmit(); }"
        />

        <div class="flex grow items-center justify-between gap-3 px-5 pb-2.5">
            <div class="flex h-14 grow items-center gap-3 py-2 max-md:overflow-x-auto max-md:overflow-y-hidden">
                @if (setting('user_prompt_library') == null || setting('user_prompt_library'))
                    <x-button
                        class="placeitem-center inline-grid size-[38px] shrink-0"
                        title="{{ __('Browse pre-defined prompts') }}"
                        variant="outline"
                        hover-variant="primary"
                        size="none"
                        @click.prevent="togglePromptLibraryShow"
                    >
                        <svg
                            width="19"
                            height="20"
                            viewBox="0 0 19 20"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M1 16.0212C1.1795 15.9071 1.37075 15.8109 1.57375 15.7327C1.77675 15.6546 1.99575 15.6155 2.23075 15.6155H3.3845V3H2.23075C1.88208 3 1.58975 3.1215 1.35375 3.3645C1.11792 3.60733 1 3.89608 1 4.23075V16.0212ZM2.23075 20C1.61108 20 1.08442 19.7868 0.65075 19.3605C0.216917 18.9343 0 18.4167 0 17.8078V4.23075C0 3.61108 0.216917 3.08442 0.65075 2.65075C1.08442 2.21692 1.61108 2 2.23075 2H9.5V3H4.3845V15.6155H11.6155V11.5H12.6155V16.6155H2.23075C1.89608 16.6155 1.60733 16.7294 1.3645 16.9573C1.1215 17.1851 1 17.4674 1 17.8043C1 18.1411 1.1215 18.4246 1.3645 18.6548C1.60733 18.8849 1.89608 19 2.23075 19H15V10.5H16V20H2.23075ZM13.5 10.5C13.5 9.106 13.9848 7.92417 14.9545 6.9545C15.9242 5.98483 17.106 5.5 18.5 5.5C17.106 5.5 15.9242 5.01517 14.9545 4.0455C13.9848 3.07583 13.5 1.894 13.5 0.5C13.5 1.894 13.0152 3.07583 12.0455 4.0455C11.0758 5.01517 9.894 5.5 8.5 5.5C9.894 5.5 11.0758 5.98483 12.0455 6.9545C13.0152 7.92417 13.5 9.106 13.5 10.5Z"
                            />
                        </svg>
                    </x-button>
                @endif

                <x-dropdown.dropdown
                    class:dropdown-dropdown="backdrop-blur-lg rounded-dropdown"
                    anchor="start"
                    offsetY="8px"
                >
                    <x-slot:trigger
                        class="min-h-[38px] min-w-[38px] whitespace-nowrap px-4 text-2xs font-medium outline-2 outline-foreground/5 group-[&.lqd-is-active]/dropdown:bg-primary group-[&.lqd-is-active]/dropdown:text-primary-foreground group-[&.lqd-is-active]/dropdown:outline-primary max-sm:whitespace-nowrap"
                        variant="outline"
                    >
                        <span x-text="generatorLabel">
                            {{ $generators[0]['label'] }}
                        </span>
                    </x-slot:trigger>

                    <x-slot:dropdown
                        class="max-h-60 min-w-[min(240px,100vw)] !transform-none space-y-1 overflow-y-auto rounded-lg border-none bg-background/50 p-2 shadow-xl shadow-black/10"
                    >
                        <p class="m-0 px-4 py-2 text-2xs font-medium opacity-50">
                            {{ __('Select AI Model') }}
                        </p>

                        @foreach ($generators as $generator)
                            <button
                                class="flex w-full items-center justify-between gap-2 rounded-lg px-4 py-3 text-start text-xs transition hover:bg-foreground/5 [&.selected]:bg-foreground/5"
                                type="button"
                                @click.prevent="generator = '{{ $generator['value'] }}'; toggle('collapse')"
                                :class="{ 'selected': generator === '{{ $generator['value'] }}' }"
                            >
                                {{ $generator['label'] }}
                                <template x-if="generator === '{{ $generator['value'] }}'">
                                    <x-tabler-check class="size-5 shrink-0" />
                                </template>
                            </button>
                        @endforeach
                    </x-slot:dropdown>
                </x-dropdown.dropdown>

                <input
                    id="image_generator"
                    type="hidden"
                    name="image_generator"
                    x-model="generator"
                >
            </div>

            <x-button
                class="inline-grid size-11 shrink-0 rounded-full bg-black p-0 text-white hover:bg-primary hover:text-primary-foreground disabled:opacity-50 dark:bg-white dark:text-black"
                type="submit"
                title="{{ __('Generate') }}"
                ::disabled="generatingImage"
            >
                <svg
                    class="col-start-1 col-end-1 row-start-1 row-end-1"
                    width="15"
                    height="12"
                    viewBox="0 0 15 12"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                    x-show="!generatingImage"
                >
                    <path d="M0 12V7.5L6 6L0 4.5V0L14.25 6L0 12Z" />
                </svg>
                <x-tabler-loader-2
                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-5 animate-spin"
                    x-show="generatingImage"
                    x-cloak
                />
            </x-button>
        </div>
    @else
        <x-forms.input
            class:container="flex border-e border-heading-foreground/5 min-w-32 max-sm:w-full min-h-16"
            class="h-auto appearance-none border-none bg-transparent py-0 pe-8 ps-8 text-heading-foreground/90 lg:rounded-s-full"
            id="image_generator"
            type="select"
            name="image_generator"
            x-model="generator"
        >
            @foreach ($generators as $generator)
                <option
                    class="bg-background"
                    value="{{ $generator['value'] }}"
                >{{ $generator['label'] }}</option>
            @endforeach
        </x-forms.input>

        <div class="flex min-h-16 grow ps-5">
            @if (setting('user_prompt_library') == null || setting('user_prompt_library'))
                <x-button
                    class="size-8 shrink-0 justify-center self-center text-center hover:translate-y-0 hover:scale-105 hover:shadow-none"
                    title="{{ __('Browse pre-defined prompts') }}"
                    variant="none"
                    size="none"
                    @click.prevent="togglePromptLibraryShow"
                >
                    <svg
                        width="19"
                        height="20"
                        viewBox="0 0 19 20"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M1 16.0212C1.1795 15.9071 1.37075 15.8109 1.57375 15.7327C1.77675 15.6546 1.99575 15.6155 2.23075 15.6155H3.3845V3H2.23075C1.88208 3 1.58975 3.1215 1.35375 3.3645C1.11792 3.60733 1 3.89608 1 4.23075V16.0212ZM2.23075 20C1.61108 20 1.08442 19.7868 0.65075 19.3605C0.216917 18.9343 0 18.4167 0 17.8078V4.23075C0 3.61108 0.216917 3.08442 0.65075 2.65075C1.08442 2.21692 1.61108 2 2.23075 2H9.5V3H4.3845V15.6155H11.6155V11.5H12.6155V16.6155H2.23075C1.89608 16.6155 1.60733 16.7294 1.3645 16.9573C1.1215 17.1851 1 17.4674 1 17.8043C1 18.1411 1.1215 18.4246 1.3645 18.6548C1.60733 18.8849 1.89608 19 2.23075 19H15V10.5H16V20H2.23075ZM13.5 10.5C13.5 9.106 13.9848 7.92417 14.9545 6.9545C15.9242 5.98483 17.106 5.5 18.5 5.5C17.106 5.5 15.9242 5.01517 14.9545 4.0455C13.9848 3.07583 13.5 1.894 13.5 0.5C13.5 1.894 13.0152 3.07583 12.0455 4.0455C11.0758 5.01517 9.894 5.5 8.5 5.5C9.894 5.5 11.0758 5.98483 12.0455 6.9545C13.0152 7.92417 13.5 9.106 13.5 10.5Z"
                        />
                    </svg>
                </x-button>
            @endif

            <x-forms.input
                class:container="grow flex"
                class="h-auto rounded-none border-none bg-transparent px-2 placeholder:text-heading-foreground/90 focus:ring-0 max-sm:text-[11px]"
                id="description"
                name="description"
                placeholder="{{ __('Describe your idea or select a pre-defined prompt') }}"
                x-model="prompt"
                x-ref="promptInput"
            />
        </div>

        <div class="ms-auto flex min-h-16 items-center gap-6 max-sm:w-full max-sm:flex-col-reverse max-sm:flex-wrap sm:pe-8">
            <x-button
                class="gap-3 text-2xs lowercase text-heading-foreground/90 underline group-hover/btn-wrap:underline-offset-2 group-focus/btn-wrap:underline-offset-2"
                variant="link"
                @click.prevent="switchView('editor')"
                x-show="!generatingImage"
            >
                <span x-text="!nodes.length ? '{{ __('or start with a blank canvas') }}' : '{{ __('or continue editing') }}'">
                    {{ __('or start with a blank canvas') }}
                </span>
            </x-button>

            <x-button
                class="inline-grid size-10 place-items-center bg-gradient-to-br from-gradient-from via-gradient-via to-gradient-to max-sm:w-full"
                type="submit"
                size="none"
                title="{{ __('Generate Image') }}"
                ::disabled="generatingImage"
            >
                <svg
                    class="col-start-1 col-end-1 row-start-1 row-end-1"
                    width="14"
                    height="14"
                    viewBox="0 0 14 14"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    x-show="!generatingImage"
                >
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M2.85637 0.654433C3.04651 -0.214165 4.28328 -0.219571 4.481 0.647332L4.49082 0.690566C4.49756 0.720292 4.50384 0.748056 4.51058 0.77677C4.75709 1.82667 5.60676 2.6289 6.67036 2.81393C7.57548 2.9714 7.57548 4.27074 6.67036 4.42821C5.60109 4.61423 4.74804 5.42404 4.50669 6.4822L4.481 6.59481C4.28328 7.46172 3.04651 7.4563 2.85637 6.58771L2.83522 6.49108C2.6027 5.42891 1.75069 4.61298 0.679451 4.42661C-0.223876 4.26946 -0.223883 2.97268 0.679451 2.81553C1.74696 2.62981 2.59677 1.8189 2.83276 0.762152L2.84848 0.690608L2.85637 0.654433ZM10.6352 3.61651C10.8194 3.53901 11.0172 3.49908 11.217 3.49908C11.4168 3.49908 11.6146 3.53901 11.7988 3.61651C11.9823 3.69375 12.1486 3.80675 12.288 3.94894L12.2895 3.9504L13.5557 5.22658C13.6955 5.36569 13.8066 5.53101 13.8824 5.71309C13.9586 5.89588 13.9978 6.09194 13.9978 6.28996C13.9978 6.48798 13.9586 6.68404 13.8824 6.86682C13.8065 7.04896 13.6955 7.21433 13.5556 7.35347L13.5541 7.35496L7.35665 13.5924C7.2733 13.6763 7.16293 13.7279 7.04513 13.7381L4.04513 13.9981C3.89803 14.0109 3.75281 13.958 3.6484 13.8536C3.544 13.7491 3.49108 13.6039 3.50382 13.4568L3.76382 10.4568C3.77403 10.339 3.82566 10.2287 3.90954 10.1453L10.1473 3.94757C10.2864 3.80603 10.4522 3.6935 10.6352 3.61651Z"
                    />
                </svg>
                <x-tabler-refresh
                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-5 animate-spin"
                    x-show="generatingImage"
                    x-cloak
                />
            </x-button>
        </div>

    @endif

    <input
        id="image_ratio"
        hidden
        name="image_ratio"
    >

    <input
        type="hidden"
        name="post_type"
        value="ai_image_generator"
    />

    <input
        type="hidden"
        name="image_mood"
        value=""
    />

    <input
        type="hidden"
        name="image_style"
        value=""
    />

    <input
        type="hidden"
        name="quality"
        value="standard"
    />

    <input
        type="hidden"
        name="image_lighting"
        value=""
    />

    <input
        type="hidden"
        name="size"
        value="1024x1024"
    />

    <input
        id="image_resolution"
        type="hidden"
        name="image_resolution"
        value="{{ $settings_two->stablediffusion_default_model === \App\Domains\Entity\Enums\EntityEnum::STABLE_DIFFUSION_XL_1024_V_1_0->value ? '640x1536' : '896x512' }}"
    />

    <input
        id="type"
        type="hidden"
        name="type"
        value="text-to-image"
    />

    <input
        type="hidden"
        name="image_number_of_images"
        value="1"
    />

    <input
        type="hidden"
        name="negative_prompt"
        value=""
    />

    <input
        id="model"
        type="hidden"
        name="model"
        value=""
    />

    <input
        id="description_flux_pro"
        type="hidden"
        name="description_flux_pro"
        value=""
    />

    <input
        id="description_ideogram"
        type="hidden"
        name="description_ideogram"
        value=""
    />

    <input
        id="stable_description"
        type="hidden"
        name="stable_description"
        value=""
    />

    <input
        type="hidden"
        name="style_preset"
        value=""
    />

    <input
        type="hidden"
        name="sampler"
        value=""
    />

    <input
        type="hidden"
        name="clip_guidance_preset"
        value=""
    />

    <input
        type="hidden"
        name="openai_id"
        value="{{ $openai->id }}"
    />
</form>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('advancedImageEditorForm', () => ({
                generators: @json($generators),
                generator: '{{ $generators[0]['value'] }}',
                pickerModalOpen: false,
                uploadingImages: [],
                lastUploadingImages: [],
                generatingImage: false,
                supportImageModels: [
                    'stable_diffusion',
                    'gpt-image-1',
                    'flux-pro-kontext',
                    'nano-banana',
                    'nano-banana-pro',
                    'nano-banana-2',
                    'xai/grok-imagine-image'
                ],

                get generatorLabel() {
                    const found = this.generators.find(gen => gen.value === this.generator);
                    return found ? found.label : '';
                },

                init() {
                    this.$nextTick(() => {
                        this.$dispatch('generator-changed', { generator: this.generator });
                    });
                    this.$watch('generator', (value) => {
                        this.$dispatch('generator-changed', { generator: value });
                    });
                },

                handleDragOver(event) {
                    this.$refs.dropArea.classList.add('drag-over');
                },
                handleDragLeave(event) {
                    this.$refs.dropArea.classList.remove('drag-over');
                },
                handleUploadingMultiImages(files) {
                    const filesArray = Array.from(files);
                    const existingFiles = Array.from(this.lastUploadingImages);
                    const dataTransfer = new DataTransfer();

                    existingFiles.forEach(file => {
                        dataTransfer.items.add(file);
                    });

                    filesArray.forEach(file => {
                        if (!file.type.startsWith('image/')) {
                            toastr.error('Please upload a valid image file.');
                            this.clearImageInputs();
                            return;
                        }

                        if (existingFiles.findIndex(existingFile => existingFile.name === file.name && existingFile.size === file.size) === -1) {
                            dataTransfer.items.add(file);
                        }
                    });

                    this.$refs.formFileInput.files = dataTransfer.files;
                    this.lastUploadingImages = dataTransfer.files;

                    this.uploadingImages = Array.from(dataTransfer.files).map(file => ({
                        src: URL.createObjectURL(file),
                        name: file.name
                    }));

                    return dataTransfer.files;
                },
                handleFileChange(event) {
                    let files = event.dataTransfer ? event.dataTransfer.files : event.target?.files;

                    this.$refs.dropArea.classList.remove('drag-over');

                    if (!files?.length) return;

                    if (event.dataTransfer) {
                        this.$refs.formFileInput.files = files;
                    }

                    files = this.handleUploadingMultiImages(files);

                    this.handleFiles(files);
                },
                handleFiles(files) {
                    this.$refs.promptInput?.focus();

                    if (this.supportImageModels.includes(this.generator)) return;

                    this.generator = 'stable_diffusion';

                    toastr.info('{{ __('Image generator changed to Stable Diffusion') }}');
                },
                clearImageInputs() {
                    this.uploadingImages = [];
                    this.lastUploadingImages = [];

                    this.$refs.formFileInput.value = '';
                },

                onFormSubmit() {
                    Alpine.store('appLoadingIndicator').show();

                    const imageGeneratorValue = $('#image_generator').val();
                    const descriptionInput = $('#description');
                    const templateInput = $('#template_description');
                    const promptInput = $('#prompt_description');
                    const imageGenerator = $('#image_generator');
                    const fileInput = $('#image_src')[0];
                    const image = fileInput?.files[0];

                    if (templateInput.val()) {
                        descriptionInput.val(`${templateInput.val()} content: ${descriptionInput.val()}`);
                        if (imageGeneratorValue === 'stable_diffusion') {
                            $('#stable_description').val(descriptionInput.val());
                        } else if (imageGeneratorValue === 'openai') {
                            descriptionInput.attr('name', 'description');
                        } else if (imageGeneratorValue === 'ideogram') {
                            $('#image_resolution').val("1x1");
                            $('#description_ideogram').val(descriptionInput.val());
                            $('#model').val("ideogram-v2");
                        } else {
                            $('#image_resolution').val("1x1");
                            $('#description_flux_pro').val(descriptionInput.val());
                            $('#model').val(imageGeneratorValue);
                        }
                    } else if (promptInput.val()) {
                        descriptionInput.val(`${promptInput.val()} content: ${descriptionInput.val()}`);
                        if (imageGeneratorValue === 'stable_diffusion') {
                            $('#stable_description').val(descriptionInput.val());
                        } else if (imageGeneratorValue === 'openai') {
                            descriptionInput.attr('name', 'description');
                        } else if (imageGeneratorValue === 'ideogram') {
                            $('#image_resolution').val("1x1");
                            $('#description_ideogram').val(descriptionInput.val());
                            $('#model').val("ideogram-v2");
                        } else {
                            $('#image_resolution').val("1x1");
                            $('#description_flux_pro').val(descriptionInput.val());
                            $('#model').val(imageGeneratorValue);
                        }
                    } else {
                        if (image) {
                            $('#image_resolution').val("640x1536");
                            $('#type').val("image-to-image");

                            let gene = imageGenerator.val();

                            if (!this.supportImageModels.includes(gene)) {
                                imageGenerator.val("stable_diffusion");

                                $('#stable_description').val(descriptionInput.val());
                            }
                        } else {
                            if (imageGeneratorValue === 'stable_diffusion') {
                                $('#stable_description').val(descriptionInput.val());
                            } else if (imageGeneratorValue === 'openai') {
                                descriptionInput.attr('name', 'description');
                            } else if (imageGeneratorValue === 'ideogram') {
                                $('#image_resolution').val("1x1");
                                $('#description_ideogram').val(descriptionInput.val());
                                $('#model').val("ideogram-v2");
                            } else {
                                $('#image_resolution').val("1x1");
                                $('#description_flux_pro').val(descriptionInput.val());
                                $('#model').val(imageGeneratorValue);
                            }
                        }
                    }

                    let formData = new FormData(this.$el);

                    this.generatingImage = true;

                    $.ajax({
                        type: "POST",
                        url: "{{ route('dashboard.user.openai.output') }}",
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: (data) => {
                            this.handleResponse(data);
                        },
                        error: (data) => {
                            this.handleResponse(data);
                        },
                        complete: () => {
                            Alpine.store('appLoadingIndicator').hide();
                            this.generatingImage = false;
                        }
                    });
                },
                handleResponse(data) {
                    if (data.status === 'success') {
                        toastr.success('{{ __('Image Generated Successfully') }}');

                        const images = data.images;

                        if (!images?.length) {
                            return
                        }

                        images.forEach(image => {
                            this.addNodeToStage({
                                type: 'Image',
                                attrs: {
                                    width: 300,
                                    height: 300,
                                    x: 'center',
                                    y: 'middle',
                                    fillSource: image.output_url
                                }
                            });
                        });

                        this.$nextTick(() => {
                            this.currentView !== 'editor' && this.switchView('editor');
                            this.container.focus()
                            this.activeFillTab = 'image';
                        });
                    } else {
                        if (data.responseJSON?.errors) {
                            $.each(data.responseJSON.errors, function(index, value) {
                                toastr.error(value);
                            });
                        } else {
                            toastr.error(data.responseJSON?.message ?? data.message);
                        }
                    }
                }
            }))
        });
    </script>
@endpush
