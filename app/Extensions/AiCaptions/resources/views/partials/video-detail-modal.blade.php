<template x-teleport="body">
    <div
        class="lqd-modal-img group/modal invisible fixed start-0 top-0 z-[999] grid h-screen w-screen place-items-center px-5 opacity-0 [&.is-active]:visible [&.is-active]:opacity-100"
        x-data="aiCaptionsVideoModal"
        :class="{ 'is-active': open }"
        @open-caption-detail.window="openModal($event.detail.id)"
        @keyup.escape.window="closeModal()"
    >
        <div
            class="absolute start-0 top-0 z-0 h-screen w-screen bg-black/50 opacity-0 backdrop-blur transition-opacity group-[&.is-active]/modal:opacity-100"
            @click="closeModal()"
        ></div>

        <div class="relative z-10 w-[min(1100px,100%)]">
            <a
                class="absolute -end-4 -top-4 z-10 flex size-9 items-center justify-center rounded-full bg-background text-inherit shadow-sm transition-all hover:bg-red-500 hover:text-white"
                @click.prevent="closeModal()"
                href="#"
            >
                <x-tabler-x class="size-4" />
            </a>

            <div class="relative flex max-h-[calc(100vh-80px)] flex-wrap overflow-y-auto rounded-xl bg-background shadow-2xl md:flex-nowrap">
                <div class="w-full p-6 md:w-7/12 md:border-e">
                    <div class="flex size-full items-center justify-center">
                        <video
                            class="max-h-full max-w-full rounded-lg"
                            x-ref="modalVideo"
                            :src="currentVideo?.video_url"
                            controls
                            autoplay
                        ></video>
                    </div>
                </div>

                <div class="w-full p-6 md:w-5/12">
                    <h4
                        class="text-base font-semibold"
                        x-text="currentVideo?.title || '{{ __('Captioned video') }}'"
                    ></h4>

                    <div class="-ms-2 mt-4 flex items-center gap-0.5">
                        <x-button
                            class="size-9 bg-transparent hover:bg-foreground/5 hover:text-foreground"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            ::href="currentVideo?.video_url"
                            ::download="'captions-' + currentVideo?.id + '.mp4'"
                            title="{{ __('Download') }}"
                        >
                            <x-tabler-download class="size-4" />
                        </x-button>
                        <x-button
                            class="size-9 bg-transparent hover:bg-foreground/5 hover:text-foreground"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            title="{{ __('Rename') }}"
                            @click="renameCurrent()"
                        >
                            <x-tabler-pencil class="size-4" />
                        </x-button>
                        <x-button
                            class="size-9 bg-transparent text-red-600 hover:bg-red-50 hover:text-red-500"
                            variant="ghost"
                            hover-variant="none"
                            size="none"
                            type="button"
                            title="{{ __('Delete') }}"
                            @click="
                                if (confirm('{{ __('Are you sure?') }}')) {
                                    window.location.href = '{{ LaravelLocalization::localizeUrl(route('dashboard.user.ai-captions.delete', '')) }}/' + currentVideo?.id;
                                }
                            "
                        >
                            <x-tabler-trash class="size-4" />
                        </x-button>
                    </div>

                    @includeIf('video-editor::partials.open-with-button', [
                        'url'   => 'currentVideo?.video_url',
                        'title' => 'currentVideo?.title',
                    ])

                    <div class="mt-10 space-y-3.5">
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0">{{ __('Template') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="currentVideo?.template_name || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.formatted_duration"
                        >
                            <p class="mb-0">{{ __('Duration') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="currentVideo?.formatted_duration"
                            ></p>
                        </div>
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0">{{ __('Date') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="formatDate(currentVideo?.created_at) || '{{ __('None') }}'"
                            ></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiCaptionsVideoModal', () => ({
                open: false,
                currentVideo: null,

                openModal(videoId) {
                    const root = document.querySelector('.lqd-ai-captions');
                    const data = root && typeof Alpine !== 'undefined' ? Alpine.$data(root) : null;
                    if (!data) return;
                    this.currentVideo = data.videos.find((v) => v.id === videoId) || null;
                    if (this.currentVideo) {
                        this.open = true;
                        document.documentElement.classList.add('overflow-hidden');
                    }
                },

                async renameCurrent() {
                    if (!this.currentVideo) return;

                    const current = this.currentVideo.title || '';
                    const next = window.prompt('{{ __('Rename video') }}', current);
                    if (next === null) return;

                    const trimmed = next.trim();
                    if (trimmed === '' || trimmed === current) return;

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const url = '{{ LaravelLocalization::localizeUrl(route('dashboard.user.ai-captions.rename', '')) }}/' + this.currentVideo.id;
                    const body = new FormData();
                    body.append('title', trimmed);

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                            body,
                        });

                        if (!res.ok) {
                            const msg = '{{ __('Could not rename the video.') }}';
                            (window.toastr?.error || window.alert)(msg);
                            return;
                        }

                        const json = await res.json().catch(() => ({}));
                        const newTitle = json.title || trimmed;
                        this.currentVideo = { ...this.currentVideo, title: newTitle };

                        const root = document.querySelector('.lqd-ai-captions');
                        const data = root && typeof Alpine !== 'undefined' ? Alpine.$data(root) : null;
                        if (data && Array.isArray(data.videos)) {
                            data.videos = data.videos.map((v) => v.id === this.currentVideo.id ? { ...v, title: newTitle } : v);
                        }
                    } catch (err) {
                        (window.toastr?.error || window.alert)(err?.message || '{{ __('Network error.') }}');
                    }
                },

                closeModal() {
                    this.open = false;
                    document.documentElement.classList.remove('overflow-hidden');
                    this.$nextTick(() => {
                        const videoEl = this.$refs.modalVideo;
                        if (videoEl) {
                            videoEl.pause();
                            videoEl.removeAttribute('src');
                        }
                    });
                },

                formatDate(isoString) {
                    if (!isoString) return '';
                    const d = new Date(isoString);
                    return d.toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    });
                },
            }));
        });
    </script>
@endpush
