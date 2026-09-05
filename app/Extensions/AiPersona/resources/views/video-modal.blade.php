<template x-teleport="body">
    <div
        class="lqd-modal-img group/modal invisible fixed start-0 top-0 z-[999] grid h-screen w-screen place-items-center px-5 opacity-0 [&.is-active]:visible [&.is-active]:opacity-100"
        x-data="aiPersonaVideoModal"
        :class="{ 'is-active': open }"
        @open-video-detail.window="openModal($event.detail.id)"
        @persona-videos-updated.window="videos = $event.detail.videos || []"
        @keyup.escape.window="closeModal()"
        @keydown.left.window="if (open) prevVideo()"
        @keydown.right.window="if (open) nextVideo()"
    >
        <div
            class="lqd-modal-img-backdrop absolute start-0 top-0 z-0 h-screen w-screen bg-black/50 opacity-0 transition-opacity group-[&.is-active]/modal:opacity-100"
            @click="closeModal()"
        ></div>

        <div class="lqd-modal-img-content-wrap relative z-10 w-[min(1230px,100%)]">
            {{-- Close button --}}
            <a
                class="absolute -end-4 -top-4 z-10 flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-sm transition-all hover:bg-red-500 hover:text-white lg:-end-12 lg:top-0"
                @click.prevent="closeModal()"
                href="#"
            >
                <x-tabler-x class="size-4" />
            </a>

            <div
                class="lqd-modal-img-content relative flex h-full max-h-[calc(100vh-50px)] scale-[0.985] flex-wrap justify-between overflow-y-auto overscroll-contain rounded-xl bg-background opacity-0 shadow-2xl transition-all group-[&.is-active]/modal:translate-y-0 group-[&.is-active]/modal:scale-100 group-[&.is-active]/modal:opacity-100 md:h-[min(90vh,850px)] md:flex-nowrap">
                {{-- Video Section --}}
                <div class="lqd-modal-fig relative min-h-px w-full border-b p-6 md:w-1/2 md:border-b-0 md:border-e lg:sticky lg:top-0 lg:h-full lg:w-8/12">
                    <div class="flex size-full items-center justify-center">
                        <video
                            class="max-h-full max-w-full rounded-lg"
                            x-ref="modalVideo"
                            :src="currentVideo?.video_url"
                            controls
                            :autoplay="open"
                        ></video>
                    </div>
                </div>

                {{-- Details Sidebar --}}
                <div class="w-full p-6 md:w-1/2 md:px-10 lg:w-4/12 lg:py-7">
                    <p
                        class="text-2xs leading-[1.4em] text-foreground/70"
                        x-text="currentVideo?.input_text || '{{ __('No input text') }}'"
                    ></p>

                    {{-- Action buttons --}}
                    <div class="-ms-2 mt-4 flex items-center gap-0.5">
                        <x-button
                            class="size-9 bg-transparent hover:bg-foreground/5 hover:text-foreground"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            ::href="currentVideo?.video_url"
                            ::download="'video-' + currentVideo?.id + '.mp4'"
                            title="{{ __('Download') }}"
                        >
                            <x-tabler-download class="size-4" />
                        </x-button>
                        <x-button
                            class="size-9 bg-transparent text-red-600 hover:bg-red-50 hover:text-red-500"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            title="{{ __('Delete') }}"
                            ::disabled="deleting"
                            @click="deleteCurrent()"
                        >
                            <x-tabler-trash class="size-4" />
                        </x-button>
                    </div>

                    @includeIf('video-editor::partials.open-with-button', [
                        'url' => 'currentVideo?.video_url',
                        'title' => 'currentVideo?.input_text',
                        'width' => 'currentVideo?.width',
                        'height' => 'currentVideo?.height',
                        'wrapperClass' => 'mt-6',
                    ])

                    {{-- Metadata --}}
                    <div class="mt-10 space-y-3.5">
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0 w-[40%]">{{ __('Date') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="formatDate(currentVideo?.created_at) || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0 w-[40%]">{{ __('AI Model') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.model || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.formatted_duration"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Duration') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.formatted_duration || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.resolution"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Resolution') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.resolution"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.aspect_ratio"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Ratio') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.aspect_ratio"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.dimensions"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Dimensions') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.dimensions"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.voice_type"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Voice Type') }}</p>
                            <p
                                class="mb-0 grow text-end capitalize opacity-50"
                                x-text="currentVideo?.voice_type === 'audio' ? '{{ __('Audio upload') }}' : '{{ __('Text to speech') }}'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.voice_label"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Voice') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.voice_label"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.locale"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Locale') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.locale_label || currentVideo?.locale"
                            ></p>
                        </div>
                        {{-- <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.voice_emotion"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Emotion') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.voice_emotion"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.voice_speed != null"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Speed') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.voice_speed + 'x'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.voice_pitch != null"
                        >
                            <p class="mb-0 w-[40%]">{{ __('Pitch') }}</p>
                            <p
                                class="mb-0 grow text-end opacity-50"
                                x-text="currentVideo?.voice_pitch"
                            ></p>
                        </div> --}}
                    </div>
                </div>
            </div>

            {{-- Prev/Next Navigation Buttons (outside content, matching image modal) --}}
            <a
                class="absolute -start-2 top-[20vh] z-10 inline-flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-md transition-all hover:scale-110 hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-30 md:top-1/2 md:-translate-y-1/2 lg:-start-4"
                href="#"
                @click.prevent="prevVideo()"
                :aria-disabled="currentIndex <= 0"
            >
                <x-tabler-chevron-left class="size-5" />
            </a>
            <a
                class="absolute -end-2 top-[20vh] z-10 inline-flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-md transition-all hover:scale-110 hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-30 md:top-1/2 md:-translate-y-1/2 lg:-end-4"
                href="#"
                @click.prevent="nextVideo()"
                :aria-disabled="currentIndex >= videos.length - 1"
            >
                <x-tabler-chevron-right class="size-5" />
            </a>
        </div>
    </div>
</template>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiPersonaVideoModal', () => ({
                open: false,
                currentIndex: -1,
                videos: [],
                deleting: false,
                deleteUrl: '{{ route('dashboard.user.ai-persona.bulk-delete') }}',
                csrfToken: '{{ csrf_token() }}',

                get currentVideo() {
                    return this.currentIndex >= 0 && this.currentIndex < this.videos.length ?
                        this.videos[this.currentIndex] :
                        null;
                },

                openModal(videoId) {
                    this.currentIndex = this.videos.findIndex(v => v.id === videoId);
                    if (this.currentIndex >= 0) {
                        this.open = true;
                    } else {
                        toastr.info("{{ __('Video is not ready yet.') }}");
                    }
                },

                async deleteCurrent() {
                    if (this.deleting || !this.currentVideo) return;
                    if (!window.confirm("{{ __('Are you sure?') }}")) return;

                    this.deleting = true;

                    const id = this.currentVideo.id;
                    const formData = new FormData();
                    formData.append('_token', this.csrfToken);
                    formData.append('ids[]', id);

                    try {
                        const res = await fetch(this.deleteUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.status === 'error') {
                            toastr.error(data.message || "{{ __('Delete Failed') }}");
                            return;
                        }
                        this.closeModal();
                        toastr.success(data.message || "{{ __('Deleted Successfully') }}");
                        window.dispatchEvent(new CustomEvent('persona-videos-refresh'));
                    } catch (e) {
                        console.error('AI Persona: modal delete failed', e);
                        toastr.error("{{ __('Delete Failed') }}");
                    } finally {
                        this.deleting = false;
                    }
                },

                closeModal() {
                    this.open = false;
                    this.$nextTick(() => {
                        const videoEl = this.$refs.modalVideo;
                        if (videoEl) {
                            videoEl.pause();
                            videoEl.removeAttribute('src');
                        }
                    });
                },

                prevVideo() {
                    if (this.currentIndex > 0) {
                        this.currentIndex--;
                    }
                },

                nextVideo() {
                    if (this.currentIndex < this.videos.length - 1) {
                        this.currentIndex++;
                    }
                },

                formatDate(isoString) {
                    if (!isoString) return '';
                    const d = new Date(isoString);
                    return d.toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                }
            }));
        });
    </script>
@endpush
