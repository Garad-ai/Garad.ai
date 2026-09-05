@extends('panel.layout.app', ['disable_tblr' => true])

@section('title', __('AI Persona'))
@section('titlebar_subtitle', __('Create studio-quality videos with AI Persona and voiceovers in 130+ languages.'))
@section('titlebar_pretitle', '')

@push('css')
    <style>
        @media (min-width: 992px) {
            .lqd-page-content-wrap {
                overflow: unset !important;
            }
        }
    </style>
@endpush

@section('titlebar_actions')
    <x-button
        href="#lqd-ai-videos-wrap"
        variant="ghost-shadow"
        x-data="{ getAiPersona: () => Alpine.$data(document.querySelector('.lqd-ai-persona')) }"
        @click="if( getAiPersona().showCreateWindow ) { $event.preventDefault(); getAiPersona().toggleCreateWindow(false); $nextTick(() => { document.querySelector('#lqd-ai-videos-wrap').scrollIntoView({ behavior: 'smooth' }) }) }"
    >
        {{ __('View Videos') }}
    </x-button>

    <x-button
        href="#"
        x-data="{}"
        @click.prevent="$dispatch('toggle-persona-create-window')"
    >
        <x-tabler-plus class="size-4" />
        {{ __('New AI Persona') }}
    </x-button>
@endsection

@section('content')
    <div class="py-10">
        <div
            class="lqd-ai-persona grid grid-cols-1 place-items-start"
            x-data="liquidAIPersona"
            @toggle-persona-create-window.window="toggleCreateWindow($event.detail.state)"
        >
            <div
                class="col-start-1 col-end-1 row-start-1 row-end-1 w-full min-w-0 max-w-full"
                x-show="!showCreateWindow"
                x-transition.opacity
            >
                <x-card
                    class="lqd-ai-avatar-card relative mb-12 overflow-hidden bg-[#F4E3FD] shadow-[0_2px_2px_hsla(0,0%,0%,0.1)] dark:bg-primary/5 lg:before:absolute lg:before:end-0 lg:before:top-0 lg:before:z-0 lg:before:h-full lg:before:w-4/12 lg:before:bg-gradient-to-r lg:before:from-transparent lg:before:to-[#9A6EE3] dark:lg:before:to-primary/20"
                    class:body="flex flex-wrap justify-between gap-y-6"
                    variant="solid"
                    size="none"
                >
                    <div class="w-full self-center px-10 pb-10 pt-5 lg:w-6/12 lg:p-14">
                        <h3 class="mb-8 leading-6">
                            {{ __('Create engaging videos with AI Persona and voiceovers in 130+ languages.') }}
                        </h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button
                                href="#"
                                @click.prevent="$dispatch('toggle-persona-create-window')"
                            >
                                <x-tabler-plus class="size-4" />
                                {{ __('New AI Persona') }}
                            </x-button>
                            <x-button
                                class="bg-heading-background text-heading-foreground hover:bg-primary hover:text-primary-foreground"
                                href="#lqd-ai-videos-wrap"
                            >
                                {{ __('Browse Videos') }}
                            </x-button>
                        </div>
                    </div>
                    <div
                        class="flex w-full self-end max-lg:order-first max-lg:mx-auto max-lg:justify-center max-lg:pt-5 max-lg:[mask-image:linear-gradient(180deg,rgba(255,255,255,1)_60%,rgba(255,255,255,0)_100%)] lg:w-6/12 lg:justify-end lg:pe-12">
                        <figure>
                            <img
                                width="295"
                                height="218"
                                src="{{ custom_theme_url('/assets/img/misc/ai-avatar.png') }}"
                                alt="{{ __('AI Persona') }}"
                            >
                        </figure>
                    </div>
                </x-card>

                <div
                    class="lqd-ai-videos-wrap"
                    id="lqd-ai-videos-wrap"
                    x-data="liquidAIPersonaVideos"
                    @persona-video-queued.window="queueVideo($event.detail.videoId)"
                    @persona-videos-refresh.window="refresh()"
                >
                    <div
                        class="lqd-loading-skeleton lqd-is-loading"
                        x-show="loading"
                    >
                        <div class="mb-5 flex items-center justify-between border-b py-2.5">
                            <span
                                class="inline-block h-3 w-16 rounded-full !bg-foreground/10"
                                data-lqd-skeleton-el
                            ></span>
                            <span
                                class="inline-block h-3 w-20 rounded-full !bg-foreground/10"
                                data-lqd-skeleton-el
                            ></span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 xl:grid-cols-5">
                            @for ($i = 0; $i < 5; $i++)
                                <div
                                    class="relative aspect-square overflow-hidden rounded-md !bg-foreground/10"
                                    data-lqd-skeleton-el
                                ></div>
                            @endfor
                        </div>
                    </div>

                    {{-- videos-section.blade.php rendered server-side, injected here --}}
                    <div
                        x-ref="container"
                        x-show="!loading"
                        x-cloak
                    ></div>

                    @include('ai-persona::video-modal')
                </div>
            </div>

            @include('ai-persona::create-section')
            @include('ai-persona::upload-modal')
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('liquidAIPersona', () => ({
                avatars: @json($avatars),
                voices: @json($voices),
                locales: @json($locales),
                voicesById: {},
                localesByCode: {},
                showCreateWindow: false,
                isLoading: false,
                audioPlayer: null,
                uploadModalActiveTab: 'upload',
                dragOverUploadForm: false,
                uploadModalData: null,
                uploadPreview: null,
                uploadFileName: null,
                creating: false,
                scriptGeneration: false,
                avatarsSearch: {
                    visible: false,
                    query: '',
                },
                voicesSearch: '',
                localesSearch: '',
                pageSize: 50,
                avatarsVisible: 50,
                voicesVisible: 50,
                audioPreviewPlaying: null,

                generateForm: {
                    avatar_style: 'normal',
                    matting: false,
                    caption: false,
                    voice_id: '',
                    input_text: '',
                    circle_background_color: '#ffffff',
                    voice_type: 'text',
                    voice_speed: 1,
                    voice_pitch: 0,
                    voice_emotion: '',
                    locale: '',
                    audio_file_name: '',
                    use_custom_dimensions: false,
                    dimension_width: 1920,
                    dimension_height: 1080,
                },

                uploadForm: {
                    name: '',
                    age: 'Young Adult',
                    gender: 'Woman',
                    ethnicity: 'Unspecified',
                    orientation: 'square',
                    pose: 'half_body',
                    style: 'Realistic',
                    appearance: '',
                    file: null,
                },

                creatingAvatar: false,
                myAvatars: [],
                myAvatarsPollTimer: null,
                avatarsStoreUrl: '{{ route('dashboard.user.ai-persona.avatars.store') }}',
                avatarsIndexUrl: '{{ route('dashboard.user.ai-persona.avatars.index') }}',
                avatarsCheckUrl: '{{ route('dashboard.user.ai-persona.avatars.check') }}',
                avatarsDeleteUrl: '{{ url('dashboard/user/ai-persona-avatars') }}',

                init() {
                    this.voicesById = Object.fromEntries(
                        (this.voices || [])
                        .filter(v => v && v.voice_id)
                        .map(v => [v.voice_id, v])
                    );
                    this.localesByCode = Object.fromEntries(
                        (this.locales || [])
                        .filter(l => l && (l.locale || l.language_code))
                        .map(l => [l.locale || l.language_code, l])
                    );
                    this.$watch('avatarsSearch.query', () => {
                        this.avatarsVisible = this.pageSize;
                    });
                    this.$watch('voicesSearch', () => {
                        this.voicesVisible = this.pageSize;
                    });
                    this.loadMyAvatars();
                },

                get activeVoiceLabel() {
                    const activeVoice = this.voices.find(v => v.voice_id === this.generateForm.voice_id);

                    if (!activeVoice) {
                        return "{{ __('Select a voice') }}";
                    }

                    return `${activeVoice.name} - ${activeVoice.language} - ${activeVoice.gender}`;
                },

                get filteredVoices() {
                    const query = (this.voicesSearch || '').trim().toLowerCase();
                    if (!query) return this.voices;

                    return this.voices.filter(voice => {
                        const haystack = [
                            voice.name,
                            voice.language,
                            voice.gender,
                        ].filter(Boolean).join(' ').toLowerCase();
                        return haystack.includes(query);
                    });
                },

                get visibleAvatars() {
                    return this.filteredAvatars.slice(0, this.avatarsVisible);
                },

                get visibleVoices() {
                    return this.filteredVoices.slice(0, this.voicesVisible);
                },

                get filteredLocales() {
                    const query = (this.localesSearch || '').trim().toLowerCase();
                    if (!query) return this.locales;

                    return this.locales.filter(locale => {
                        const haystack = [
                            locale.label,
                            locale.value,
                            locale.language,
                            locale.locale,
                            locale.language_code,
                        ].filter(Boolean).join(' ').toLowerCase();
                        return haystack.includes(query);
                    });
                },

                get activeLocaleLabel() {
                    const code = this.generateForm.locale;
                    if (!code) return "{{ __('Default (voice language)') }}";
                    const locale = this.localesByCode[code];
                    return locale?.label || locale?.value || code;
                },

                loadMoreAvatars() {
                    this.avatarsVisible = Math.min(
                        this.avatarsVisible + this.pageSize,
                        this.filteredAvatars.length,
                    );
                },

                loadMoreVoices() {
                    this.voicesVisible = Math.min(
                        this.voicesVisible + this.pageSize,
                        this.filteredVoices.length,
                    );
                },

                get filteredAvatars() {
                    const query = (this.avatarsSearch.query || '').trim().toLowerCase();
                    if (!query) return this.avatars;

                    return this.avatars.filter(avatar => {
                        const voice = avatar.default_voice_id ? this.voicesById[avatar.default_voice_id] : null;
                        const tags = Array.isArray(avatar.tags) ? avatar.tags.join(' ') : (avatar.tags || '');
                        const haystack = [
                            avatar.avatar_name,
                            avatar.gender,
                            avatar.type,
                            tags,
                            voice?.name,
                            voice?.language,
                            voice?.gender,
                        ].filter(Boolean).join(' ').toLowerCase();
                        return haystack.includes(query);
                    });
                },

                toggleCreateWindow(state) {
                    this.showCreateWindow = state !== undefined ? state : !this.showCreateWindow;
                },

                getUploadModalData() {
                    if (this.uploadModalData) {
                        return this.uploadModalData;
                    }

                    const uploadModalEl = document.querySelector('#ai-persona-upload-modal');

                    this.uploadModalData = Alpine.$data(uploadModalEl);

                    return this.uploadModalData;
                },

                toggleUploadModal(state) {
                    this.getUploadModalData().toggleModal(state);
                },

                async handleCreateFormSubmit(event) {
                    if (this.creating) return;

                    if (!this.generateForm.avatar_id) {
                        toastr.error("{{ __('Please select an avatar.') }}");
                        return;
                    }

                    const form = event.target;

                    if (this.generateForm.voice_type === 'text') {
                        if (!this.generateForm.voice_id) {
                            toastr.error("{{ __('Please select a voice.') }}");
                            return;
                        }

                        if (!this.generateForm.input_text || !this.generateForm.input_text.trim()) {
                            toastr.error("{{ __('Please enter a script.') }}");
                            return;
                        }
                    } else if (this.generateForm.voice_type === 'audio') {
                        const audioInput = form.querySelector('input[name="audio_file"]');
                        if (!audioInput || !audioInput.files || !audioInput.files[0]) {
                            toastr.error("{{ __('Please upload an audio file.') }}");
                            return;
                        }
                    }

                    const formData = new FormData(form);

                    this.creating = true;

                    try {
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || data.status === 'error') {
                            const message = data.message ||
                                (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
                                "{{ __('Video generation failed.') }}";
                            toastr.error(message);
                            return;
                        }

                        toastr.success(data.message || "{{ __('Video Created Successfully') }}");

                        if (data.video_id) {
                            window.dispatchEvent(new CustomEvent('persona-video-queued', {
                                detail: {
                                    videoId: data.video_id
                                },
                            }));
                        }

                        this.toggleCreateWindow(false);
                    } catch (e) {
                        console.error('AI Persona: failed to create video', e);
                        toastr.error("{{ __('Video generation failed.') }}");
                    } finally {
                        this.creating = false;
                    }
                },

                // File handling
                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.processFile(file);
                    }
                },

                handleAudioFileSelect(event) {
                    const file = event.target.files[0];
                    if (!file) {
                        this.generateForm.audio_file_name = '';
                        return;
                    }

                    if (file.size > 25 * 1024 * 1024) {
                        toastr.error("{{ __('Audio must be 25MB or smaller.') }}");
                        event.target.value = '';
                        this.generateForm.audio_file_name = '';
                        return;
                    }

                    this.generateForm.audio_file_name = file.name;
                },

                handleFileDrop(event) {
                    this.dragOver = false;
                    const file = event.dataTransfer.files[0];
                    if (file) {
                        this.processFile(file);
                    }
                },

                processFile(file) {
                    if (!['image/png', 'image/jpeg'].includes(file.type)) {
                        toastr.error("{{ __('Please upload a PNG or JPG image.') }}");
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) {
                        toastr.error("{{ __('Image must be 10MB or smaller.') }}");
                        return;
                    }

                    this.uploadForm.file = file;
                    this.uploadFileName = file.name;

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.uploadPreview = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },

                resetUpload() {
                    this.uploadPreview = null;
                    this.uploadFileName = null;
                    this.uploadForm.file = null;
                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }
                },

                resetAvatarForm() {
                    this.uploadPreview = null;
                    this.uploadFileName = null;
                    this.uploadForm = {
                        name: '',
                        age: 'Young Adult',
                        gender: 'Woman',
                        ethnicity: 'Unspecified',
                        orientation: 'square',
                        pose: 'half_body',
                        style: 'Realistic',
                        appearance: '',
                        file: null,
                    };
                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }
                },

                async handleAvatarFormSubmit() {
                    if (this.creatingAvatar) return;

                    const isUpload = this.uploadModalActiveTab === 'upload';

                    if (!this.uploadForm.name) {
                        toastr.error("{{ __('Please enter a name for your avatar.') }}");
                        return;
                    }

                    if (isUpload && !this.uploadForm.file) {
                        toastr.error("{{ __('Please select an image to upload.') }}");
                        return;
                    }

                    if (!isUpload && !this.uploadForm.appearance) {
                        toastr.error("{{ __('Please describe your avatar.') }}");
                        return;
                    }

                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('name', this.uploadForm.name);

                    if (isUpload) {
                        formData.append('source', 'upload');
                        formData.append('file', this.uploadForm.file);
                    } else {
                        formData.append('source', 'ai');
                        formData.append('age', this.uploadForm.age);
                        formData.append('gender', this.uploadForm.gender);
                        formData.append('ethnicity', this.uploadForm.ethnicity);
                        formData.append('orientation', this.uploadForm.orientation);
                        formData.append('pose', this.uploadForm.pose);
                        formData.append('style', this.uploadForm.style);
                        formData.append('appearance', this.uploadForm.appearance);
                    }

                    this.creatingAvatar = true;

                    try {
                        const res = await fetch(this.avatarsStoreUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || data.status === 'error') {
                            const message = data.message ||
                                (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
                                "{{ __('Avatar creation failed.') }}";
                            toastr.error(message);
                            return;
                        }

                        toastr.success(data.message || "{{ __('Avatar submitted successfully.') }}");

                        if (data.avatar) {
                            this.myAvatars = [data.avatar, ...this.myAvatars.filter(a => a.id !== data.avatar.id)];
                            this.startAvatarsPolling();
                        }

                        this.resetAvatarForm();
                        this.toggleUploadModal(false);
                    } catch (e) {
                        console.error('AI Persona: failed to create avatar', e);
                        toastr.error("{{ __('Avatar creation failed.') }}");
                    } finally {
                        this.creatingAvatar = false;
                    }
                },

                async loadMyAvatars() {
                    try {
                        const res = await fetch(this.avatarsIndexUrl, {
                            headers: {
                                Accept: 'application/json'
                            },
                        });
                        const data = await res.json();
                        this.myAvatars = data.avatars || [];
                        this.startAvatarsPolling();
                    } catch (e) {
                        console.error('AI Persona: failed to load avatars', e);
                    }
                },

                startAvatarsPolling() {
                    this.stopAvatarsPolling();
                    const hasPending = this.myAvatars.some(a => a.status !== 'ready' && a.status !== 'failed');
                    if (!hasPending) return;
                    this.myAvatarsPollTimer = setInterval(() => this.pollAvatars(), 7000);
                },

                stopAvatarsPolling() {
                    if (this.myAvatarsPollTimer) {
                        clearInterval(this.myAvatarsPollTimer);
                        this.myAvatarsPollTimer = null;
                    }
                },

                async pollAvatars() {
                    const pending = this.myAvatars.filter(a => a.status !== 'ready' && a.status !== 'failed');
                    if (pending.length === 0) {
                        this.stopAvatarsPolling();
                        return;
                    }

                    try {
                        const params = new URLSearchParams();
                        pending.forEach(a => params.append('ids[]', a.id));
                        const res = await fetch(`${this.avatarsCheckUrl}?${params.toString()}`, {
                            headers: {
                                Accept: 'application/json'
                            },
                        });
                        const data = await res.json();
                        const updates = data.avatars || [];

                        updates.forEach(updated => {
                            const existing = this.myAvatars.find(a => a.id === updated.id);
                            const wasReady = existing?.status === 'ready';
                            Object.assign(existing || {}, updated);

                            if (!wasReady && updated.status === 'ready' && updated.heygen_avatar_id) {
                                this.avatars = [{
                                    avatar_id: updated.heygen_avatar_id,
                                    avatar_name: updated.name,
                                    gender: 'Custom',
                                    type: 'custom',
                                    preview_image_url: updated.preview_image_url,
                                    preview_video_url: null,
                                    default_voice_id: null,
                                    tags: [],
                                    is_custom: true,
                                }, ...this.avatars];
                                toastr.success("{{ __('Your avatar is ready to use.') }}");
                            }

                            if (updated.status === 'failed') {
                                toastr.error(updated.error_message || "{{ __('Avatar creation failed.') }}");
                            }
                        });

                        const stillPending = this.myAvatars.some(a => a.status !== 'ready' && a.status !== 'failed');
                        if (!stillPending) {
                            this.stopAvatarsPolling();
                        }
                    } catch (e) {
                        console.error('AI Persona: avatar poll failed', e);
                    }
                },

                async deleteMyAvatar(avatarId) {
                    if (!window.confirm("{{ __('Delete this avatar?') }}")) return;

                    try {
                        const res = await fetch(`${this.avatarsDeleteUrl}/${avatarId}`, {
                            method: 'DELETE',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || data.status === 'error') {
                            toastr.error(data.message || "{{ __('Delete Failed') }}");
                            return;
                        }

                        const removed = this.myAvatars.find(a => a.id === avatarId);
                        this.myAvatars = this.myAvatars.filter(a => a.id !== avatarId);

                        if (removed?.heygen_avatar_id) {
                            this.avatars = this.avatars.filter(a => a.avatar_id !== removed.heygen_avatar_id);
                            if (this.generateForm.avatar_id === removed.heygen_avatar_id) {
                                this.generateForm.avatar_id = null;
                            }
                        }

                        toastr.success(data.message || "{{ __('Avatar deleted.') }}");
                    } catch (e) {
                        console.error('AI Persona: failed to delete avatar', e);
                        toastr.error("{{ __('Delete Failed') }}");
                    }
                },

                async enhanceScript() {
                    if (this.scriptGeneration) return;

                    this.scriptGeneration = true;

                    try {
                        const formData = new FormData();
                        formData.append('_token', '{{ csrf_token() }}');
                        formData.append('script', this.generateForm.input_text || '');

                        const res = await fetch('{{ route('dashboard.user.ai-persona.enhance-script') }}', {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.success) {
                            toastr.error(data.message || "{{ __('Failed to enhance script.') }}");
                            return;
                        }

                        if (data.enhanced_script) {
                            this.generateForm.input_text = data.enhanced_script;
                        }
                    } catch (e) {
                        console.error('AI Persona: failed to enhance script', e);
                        toastr.error("{{ __('Failed to enhance script.') }}");
                    } finally {
                        this.scriptGeneration = false;
                    }
                },

                playPreview(voice_id) {
                    if (!voice_id) {
                        alert("{{ __('Please select a voice to preview.') }}");
                        return;
                    }

                    if (this.audioPlayer) {
                        this.audioPlayer.pause();
                        this.audioPlayer.currentTime = 0;
                    }

                    this.isLoading = true;
                    this.audioPlayer = new Audio(this.voices.find(v => v.voice_id === voice_id)?.preview_audio);
                    this.audioPreviewPlaying = voice_id;

                    this.audioPlayer.play()
                        .then(() => {
                            this.isLoading = false;
                        })
                        .catch((error) => {
                            console.error("Audio playback error:", error);
                            toastr.error("{{ __('Failed to play the selected audio.') }}");
                            this.audioPreviewPlaying = null;
                            this.isLoading = false;
                        });

                    this.audioPlayer.addEventListener('ended', () => {
                        this.audioPreviewPlaying = null;
                        this.isLoading = false;
                    });
                },
            }));

            Alpine.data('liquidAIPersonaVideos', () => ({
                loading: true,
                hasVideos: false,
                videos: [],
                pendingIds: [],
                pendingVideos: [],
                selectedToday: [],
                selectedPrevious: [],
                pollTimer: null,
                polling: false,
                videosUrl: '{{ route('dashboard.user.ai-persona.videos') }}',
                checkUrl: '{{ route('dashboard.user.ai-persona.check') }}',
                bulkDeleteUrl: '{{ route('dashboard.user.ai-persona.bulk-delete') }}',
                csrfToken: '{{ csrf_token() }}',
                deleting: false,

                get totalSelected() {
                    return this.selectedToday.length + this.selectedPrevious.length;
                },

                init() {
                    this.load();
                },

                async load() {
                    try {
                        await this.fetchVideos();
                    } catch (e) {
                        console.error('AI Persona: failed to load videos', e);
                    } finally {
                        this.loading = false;
                        this.startPolling();
                    }
                },

                async fetchVideos() {
                    const res = await fetch(this.videosUrl, {
                        headers: {
                            Accept: 'application/json'
                        }
                    });
                    const data = await res.json();

                    const serverPendingIds = data.pendingIds || [];
                    this.hasVideos = !!data.hasVideos;
                    this.videos = data.videos || [];
                    this.$refs.container.innerHTML = data.html || '';

                    // Drop Alpine shimmers for items the server now renders itself
                    this.pendingVideos = this.pendingVideos.filter(id => !serverPendingIds.includes(id));
                    // Poll for both server-rendered pending and still-unacknowledged AJAX-queued videos
                    this.pendingIds = [...new Set([...serverPendingIds, ...this.pendingVideos])];

                    window.dispatchEvent(new CustomEvent('persona-videos-updated', {
                        detail: {
                            videos: this.videos
                        },
                    }));
                },

                async refresh() {
                    try {
                        await this.fetchVideos();
                        this.startPolling();
                    } catch (e) {
                        console.error('AI Persona: failed to refresh videos', e);
                    }
                },

                startPolling() {
                    this.stopPolling();
                    if (this.pendingIds.length === 0) return;
                    this.pollTimer = setInterval(() => this.poll(), 5000);
                },

                stopPolling() {
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                },

                async poll() {
                    if (this.polling) return;
                    if (this.pendingIds.length === 0) {
                        this.stopPolling();
                        return;
                    }
                    this.polling = true;
                    const params = new URLSearchParams();
                    this.pendingIds.forEach(id => params.append('ids[]', id));
                    try {
                        const res = await fetch(`${this.checkUrl}?${params.toString()}`, {
                            headers: {
                                Accept: 'application/json'
                            }
                        });
                        const {
                            data
                        } = await res.json();
                        const completedIds = (data || []).map(item => item.divId.replace(/^video-/, ''));
                        if (completedIds.length > 0) {
                            this.pendingIds = this.pendingIds.filter(pid => !completedIds.includes(pid));
                            this.pendingVideos = this.pendingVideos.filter(pid => !completedIds.includes(pid));
                            await this.refresh();
                        }
                        if (this.pendingIds.length === 0) this.stopPolling();
                    } catch (e) {
                        console.error('AI Persona: polling failed', e);
                    } finally {
                        this.polling = false;
                    }
                },

                queueVideo(videoId) {
                    if (!videoId) return;
                    if (!this.pendingVideos.includes(videoId)) {
                        this.pendingVideos.push(videoId);
                    }
                    if (!this.pendingIds.includes(videoId)) {
                        this.pendingIds.push(videoId);
                    }
                    this.hasVideos = true;
                    this.startPolling();
                },

                cancelSelection() {
                    this.selectedToday = [];
                    this.selectedPrevious = [];
                },

                async bulkDelete() {
                    if (this.deleting) return;

                    const ids = [...this.selectedToday, ...this.selectedPrevious]
                        .filter(Boolean)
                        .map(String);

                    if (ids.length === 0) return;

                    if (!window.confirm("{{ __('Are you sure you want to delete the selected videos?') }}")) {
                        return;
                    }

                    this.deleting = true;

                    try {
                        const formData = new FormData();
                        formData.append('_token', this.csrfToken);
                        ids.forEach(id => formData.append('ids[]', id));

                        const res = await fetch(this.bulkDeleteUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || data.status === 'error') {
                            const message = data.message ||
                                (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
                                "{{ __('Delete Failed') }}";
                            toastr.error(message);
                            return;
                        }

                        const deleted = (data.deleted || []).map(String);

                        this.selectedToday = this.selectedToday.filter(id => !deleted.includes(String(id)));
                        this.selectedPrevious = this.selectedPrevious.filter(id => !deleted.includes(String(id)));
                        this.pendingVideos = this.pendingVideos.filter(id => !deleted.includes(String(id)));

                        await this.refresh();

                        if (data.status === 'partial') {
                            toastr.warning(data.message);
                        } else {
                            toastr.success(data.message || "{{ __('Deleted Successfully') }}");
                        }
                    } catch (e) {
                        console.error('AI Persona: bulk delete failed', e);
                        toastr.error("{{ __('Delete Failed') }}");
                    } finally {
                        this.deleting = false;
                    }
                },

                destroy() {
                    this.stopPolling();
                },
            }));
        });
    </script>
@endpush
