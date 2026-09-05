@php
    $tabs = [
        'url' => [
            'title' => __('Website'),
        ],
        'pdf' => [
            'title' => __('File'),
        ],
        'text' => [
            'title' => __('Text'),
        ],
    ];
    $title = __('Voice Chatbot Training');
@endphp

@extends('panel.layout.settings')
@section('title', $title)
@section('titlebar_actions', '')

@section('settings')
    @if (!empty($item))
        <h2 class="mb-9 font-semibold">
            @lang('Configure a chatbot that interacts with your users, ensuring it delivers accurate information.')
        </h2>
        <form
            class="mb-4 flex flex-col flex-wrap gap-5"
            id="configure_bot_form"
            method="post"
            action="{{ route('dashboard.admin.voice-chatbot.update') }}"
            x-ref="form"
            x-data="elevenlabsVoiceChatbot"
        >
            @method('PUT')
            @csrf

            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Welcome Message') }}"
                    placeholder="{{ __('Enter welcome message') }}"
                    name="welcome_message"
                    size="lg"
                    value="{{ $item->welcome_message }}"
                />

                <template
                    x-for="(error, index) in formErrors.welcome_message"
                    :key="'error-' + index"
                >
                    <div class="mt-2 text-2xs/5 font-medium text-red-500">
                        <p x-text="error"></p>
                    </div>
                </template>
            </div>

            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Chatbot Instructions') }}"
                    placeholder="{{ __('Explain chatbot role') }}"
                    name="instruction"
                    size="lg"
                    type="textarea"
                    rows="5"
                >
                    {{ $item->instruction }}
                </x-forms.input>

                <template
                    x-for="(error, index) in formErrors.instruction"
                    :key="'error-' + index"
                >
                    <div class="mt-2 text-2xs/5 font-medium text-red-500">
                        <p x-text="error"></p>
                    </div>
                </template>
            </div>

            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    class="max-sm:text-[9px]"
                    label="{{ __('Voice') }}"
                    name="voice_id"
                    value="{{ $item->voice_id }}"
                    size="lg"
                    type="select"
                >
                    @foreach ($voices as $voice)
                        <option
                            value="{{ $voice->voice_id }}"
                            {{ $voice->voice_id == $item->voice_id ? 'selected' : '' }}
                        >
                            {{ $voice->name . ' (' . implode(', ', (array) $voice->labels) . ')' }}
                        </option>
                    @endforeach
                </x-forms.input>

                <template
                    x-for="(error, index) in formErrors.voice_id"
                    :key="'error-' + index"
                >
                    <div class="mt-2 text-2xs/5 font-medium text-red-500">
                        <p x-text="error"></p>
                    </div>
                </template>
            </div>

            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('Language') }}"
                    name="language"
                    value="{{ $item->language }}"
                    size="lg"
                    type="select"
                >
                    @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                        @if (in_array($localeCode, explode(',', $settings_two->languages), true))
                            <option
                                value="{{ $localeCode }}"
                                {{ $item->language == $localeCode ? 'selected' : '' }}
                            >
                                {{ $properties['name'] }}
                            </option>
                        @endif
                    @endforeach
                </x-forms.input>
                <template
                    x-for="(error, index) in formErrors.language"
                    :key="'error-' + index"
                >
                    <div class="mt-2 text-2xs/5 font-medium text-red-500">
                        <p x-text="error"></p>
                    </div>
                </template>
            </div>

            <x-button
                class="!mt-8 w-full"
                data-form="#configure_bot_form"
                size="lg"
                type="button"
                @click.prevent="submitForm"
                ::disabled="submitting"
            >
                {{ __('Submit') }}
            </x-button>
        </form>

        <h2 class="my-9 font-semibold">
            @lang('Simply select the source and GaradAI will do the rest to train your HIR in seconds.')
        </h2>

        <div x-data="trainVoiceChatbotData">
            <nav
                class="mb-14 flex flex-col justify-between gap-2 rounded-xl bg-foreground/5 px-2.5 py-1.5 font-medium leading-snug sm:flex-row sm:rounded-full">
                @foreach ($tabs as $tab => $tabData)
                    <button
                        class="'rounded-xl sm:rounded-full' grow px-5 py-2.5 text-foreground transition-colors hover:bg-foreground/5 [&.lqd-is-active]:bg-white [&.lqd-is-active]:text-black [&.lqd-is-active]:shadow-[0_2px_13px_rgba(0,0,0,0.1)]"
                        type="button"
                        @click="setActiveTab('{{ $tab }}')"
                        :class="{ 'lqd-is-active': activeTab === '{{ $tab }}' }"
                    >
                        @lang($tabData['title'])
                    </button>
                @endforeach
            </nav>

            <div>
                @include('elevenlabs-voice-chat::particles.web-site-tab')
                @include('elevenlabs-voice-chat::particles.pdf-tab')
                @include('elevenlabs-voice-chat::particles.text-tab')
            </div>
        </div>

        <div class="crawler-spinner fixed inset-0 z-50 mt-5 hidden bg-background/65 text-center backdrop-blur-sm">
            <div class="container">
                <div class="flex min-h-screen flex-col items-center justify-center py-7">
                    <div class="flex w-full flex-col items-center gap-11 md:w-5/12 lg:w-3/12">
                        <h5 class="text-lg">
                            @lang('Almost Done!')
                        </h5>
                        <x-tabler-loader-2
                            class="mx-auto size-28 animate-spin"
                            role="status"
                        />
                        <div class="space-y-3">
                            <p class="font-heading text-2xl font-bold text-heading-foreground">
                                @lang('Training HIR...')
                            </p>
                            <p>
                                @lang('We’re training your custom HIR with the related resources. This may take a few minutes.')
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @push('script')
            <script src="{{ custom_theme_url('assets/js/panel/admin.voice-chatbot.js?v=' . time()) }}"></script>

            <script>
                (() => {
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('trainVoiceChatbotData', () => ({
                            activeTab: 'url',
                            fetching: false,
                            uploading: false,
                            embeddings: [],
                            editingItem: {},
                            activeChatbot: @json($item),
                            init() {
                                this.$data.trainVoiceChatbotData = this;
                            },
                            setActiveTab(tab) {
                                if (tab === this.activeTab) return;

                                this.activeTab = tab;
                            },
                            toggleSelectAll(event) {
                                const btn = event.currentTarget;
                                const checkboxes = document.getElementsByName('embedding-item');
                                const relevantCheckboxes = Array.from(checkboxes).filter(el => el
                                    .getAttribute('data-type') === this.activeTab);

                                const allChecked = relevantCheckboxes.every(el => el.checked);

                                relevantCheckboxes.forEach(el => el.checked = !allChecked);

                                if (relevantCheckboxes.some(el => el.checked)) {
                                    btn.classList.add('has-selected');
                                } else {
                                    btn.classList.remove('has-selected');
                                }
                            },
                            async fetchEmbeddings() {
                                if (!this.activeChatbot || this.fetching) return;

                                this.fetching = true;

                                const res = await fetch(
                                    `{{ route('dashboard.admin.voice-chatbot.train.data') }}?id=${this.activeChatbot.id}`
                                );

                                if (!res.ok) {
                                    this.fetching = false;
                                    toastr.error(
                                        '{{ $app_is_demo ? __('This feature is disabled in Demo version.') : __('Failed to fetch embeddings') }}'
                                    );
                                    return;
                                }

                                const data = await res.json();
                                const embeddings = data.data;

                                if (!embeddings) {
                                    this.fetching = false;
                                    toastr.error('{{ __('Failed to fetch embeddings') }}');
                                    return;
                                }

                                this.embeddings = embeddings;

                                this.fetching = false;
                            },
                            async trainEmbeddings(event) {
                                if (this.fetching) return;

                                this.fetching = true;

                                const form = event.target;
                                const checkboxes = form.elements['embedding-item'];
                                const embdeddingData = {
                                    id: this.activeChatbot.id,
                                    data: (checkboxes.length ? Array.from(checkboxes) : [
                                        checkboxes
                                    ]).filter(el => el.checked).map(el => el
                                        .value),
                                };

                                const res = await fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    },
                                    body: JSON.stringify(embdeddingData),
                                });

                                if (!res.ok) {
                                    this.fetching = false;
                                    const errorData = await res.json();
                                    toastr.error(errorData.message ||
                                        '{{ __('Failed to train embeddings') }}');
                                    return;
                                }

                                const data = await res.json();
                                const embeddings = data.data;

                                if (!embeddings) {
                                    this.fetching = false;
                                    toastr.error('{{ __('Failed to train embeddings') }}');
                                    return;
                                }

                                let trainedAll = true;

                                this.embeddings = this.embeddings.map(embedding => {
                                    const newEmbedding = embeddings.find(e => e.id === embedding
                                        .id);

                                    if (newEmbedding) {
                                        if (newEmbedding.status == 'Not Trained') {
                                            trainedAll = false;
                                        }
                                        return newEmbedding;
                                    }

                                    return embedding;
                                });

                                if (trainedAll) {
                                    toastr.success('{{ __('Training done successfully') }}');
                                } else {
                                    toastr.info('{{ __('Training failed') }}');
                                }

                                this.fetching = false;
                            },
                            async deleteEmbedding(embeddingId) {
                                if (!embeddingId || this.fetching) return;

                                this.fetching = true;

                                if (!confirm(
                                        `{{ __('Are you sure you want to delete this embedding?') }}`
                                    )) {
                                    this.fetching = false;
                                    return;
                                }

                                const res = await fetch(
                                    '{{ route('dashboard.admin.voice-chatbot.train.delete') }}', {
                                        method: 'DELETE',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        },
                                        body: JSON.stringify({
                                            id: this.activeChatbot.id,
                                            data: [embeddingId],
                                        }),
                                    });

                                if (!res.ok) {
                                    this.fetching = false;
                                    toastr.error(
                                        '{{ $app_is_demo ? __('This feature is disabled in Demo version.') : __('Failed to delete embedding') }}'
                                    );
                                    return;
                                }

                                const data = await res.json();

                                if (data.status === 200 && data.message) {
                                    toastr.success(data.message);
                                }

                                this.embeddings = this.embeddings.filter(embedding => embedding.id !==
                                    embeddingId);

                                this.fetching = false;
                            },
                            setEditingItem(id) {
                                this.editingItem = this.embeddings.find(embedding => embedding.id === id) ||
                                {};
                            },

                            // Start Train URL Tab
                            async addUrl(event) {
                                if (this.fetching) return;

                                this.fetching = true;

                                const form = event.target;
                                const formData = new FormData(form);

                                formData.append('id', this.activeChatbot.id);

                                const res = await fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: formData,
                                });

                                if (!res.ok) {
                                    this.fetching = false;
                                    console.log('add');
                                    toastr.error(
                                        '{{ $app_is_demo ? __('This feature is disabled in Demo version.') : __('Failed to fetch urls') }}'
                                    );
                                    return;
                                }

                                const data = await res.json();
                                const websites = data.data;

                                if (!websites) {
                                    this.fetching = false;
                                    toastr.error('{{ __('Failed to fetch urls') }}');
                                    return;
                                }

                                this.embeddings = this.embeddings
                                    .filter(embedding => embedding.type !== 'url')
                                    .concat(websites);

                                this.fetching = false;
                            },
                            // End Train URL Tab

                            // Start Train File Tab
							async uploadFile(event) {
								this.uploading = this.fetching = true;

								const form = event.target;
								const formData = new FormData(form);

								// Try getting files from original input first
								let files = [];
								const fileInput = form.elements.file;

								if (fileInput && fileInput.files && fileInput.files.length > 0) {
									files = Array.from(fileInput.files);
								} else if (window.selectedMediaData && window.selectedMediaData.has('file')) {
									// Fallback: Use cached media data from Media Manager
									const items = window.selectedMediaData.get('file');
									if (items?.length > 0) {
										for (const [index, item] of items.entries()) {
											try {
												const response = await fetch(item.url);
												const blob = await response.blob();
												const file = new File([blob], item.title || `file-${index}`, {
													type: blob.type,
													lastModified: Date.now()
												});
												formData.append('file', file);
											} catch (error) {
												console.error(`Failed to load file from URL: ${item.url}`, error);
											}
										}
									}
								}

								// If no files found at all
								if (!formData.getAll('file').length) {
									this.uploading = this.fetching = false;
									toastr.error('{{ __('Please select a file') }}');
									return;
								}

								// Append chatbot ID
								formData.append('id', this.activeChatbot.id);

								// Submit
								const res = await fetch(form.action, {
									method: 'POST',
									headers: {
										'X-CSRF-TOKEN': '{{ csrf_token() }}',
										'Accept': 'application/json',
									},
									body: formData,
								});

								if (!res.ok) {
									this.uploading = this.fetching = false;
									toastr.error(
										'{{ $app_is_demo ? __("This feature is disabled in Demo version.") : __("Failed to upload file") }}'
									);
									return;
								}

								const data = await res.json();
								const filesResponse = data.data;

								if (!filesResponse) {
									this.uploading = this.fetching = false;
									toastr.error('{{ __("Failed to upload file") }}');
									return;
								}

								this.embeddings = this.embeddings
									.filter(embedding => embedding.type !== 'file')
									.concat(filesResponse);

								// Reset UI
								this.$refs.fileName.innerText = this.$refs.fileName.getAttribute('data-original-text');
								this.$refs.fileName.value = null;
								this.$refs.fileName.files = new DataTransfer().files;

								this.uploading = this.fetching = false;

								// Optional: Clear selected media after successful upload
								window.selectedMediaData.delete('file');
							},
                            // End Train File Tab

                            // Start Train Text & QA Tab
                            async addText(event, textOrQA = 'text') {
                                this.fetching = true;

                                const form = event.target;
                                const formData = new FormData(form);
                                const title = formData.get(textOrQA === 'text' ? 'title' : 'question');
                                const content = formData.get(textOrQA === 'text' ? 'content' :
                                    'answer');

                                if (!title || !content || !title.trim() || !content.trim()) {
                                    toastr.error('{{ __('Please fill in the title and content') }}');
                                    return;
                                }

                                formData.append('id', this.activeChatbot.id);

                                const res = await fetch(form.action, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: formData,
                                });

                                if (!res.ok) {
                                    this.fetching = false;
                                    toastr.error(
                                        '{{ $app_is_demo ? __('This feature is disabled in Demo version.') : __('Failed to add the new item') }}'
                                    );
                                    return;
                                }

                                const data = await res.json();
                                const items = data.data;

                                if (!items) {
                                    this.fetching = false;
                                    toastr.error('{{ __('Failed to add the new item') }}');
                                    return;
                                }

                                this.embeddings = this.embeddings
                                    .filter(embedding => embedding.type !== textOrQA)
                                    .concat(items);

                                event.target.reset();
                                event.target.elements[textOrQA === 'text' ? 'title' : 'question']
                                    .focus();

                                this.fetching = false;
                            },
                        }));
                    });
                })();
            </script>
        @endpush
    @else
        <div class="flex flex-1 items-center justify-center">
            <h2 class="text-center">
                {{ __('Something went wrong! Please check if you configured Elevenlabs API Key correctly and credits.') }}
            </h2>
        </div>
    @endif
@endsection
