<template x-teleport="body">
    <div
        class="lqd-modal-img group/modal invisible fixed start-0 top-0 z-[999] grid h-screen w-screen place-items-center px-5 opacity-0 [&.is-active]:visible [&.is-active]:opacity-100"
        x-data="aiVideoProVideoModal"
        :class="{ 'is-active': open }"
        @open-video-detail.window="openModal($event.detail.id)"
        @keyup.escape.window="closeModal()"
        @keydown.left.window="if (open) prevVideo()"
        @keydown.right.window="if (open) nextVideo()"
    >
        <div
            class="lqd-modal-img-backdrop absolute start-0 top-0 z-0 h-screen w-screen bg-black/50 opacity-0 backdrop-blur transition-opacity group-[&.is-active]/modal:opacity-100"
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
                            autoplay
                        ></video>
                    </div>
                </div>

                {{-- Details Sidebar --}}
                <div class="w-full p-6 md:w-1/2 md:px-10 lg:w-4/12 lg:py-7">
                    <p
                        class="text-2xs leading-[1.4em] text-foreground/70"
                        x-text="currentVideo?.prompt || '{{ __('No prompt') }}'"
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
                            @click="
								if (confirm('{{ __('Are you sure?') }}')) {
									window.location.href = '{{ LaravelLocalization::localizeUrl(route('dashboard.user.ai-video-pro.delete', '')) }}/' + currentVideo?.id;
								}
							"
                        >
                            <x-tabler-trash class="size-4" />
                        </x-button>
                    </div>

                    @if ($socialMediaRegistered && $connectedVideoPlatforms->isNotEmpty())
                        <div
                            class="relative mt-6"
                            x-data="{ shareOpen: false }"
                        >
                            <x-button
                                class="w-full"
                                size="lg"
                                variant="ghost-shadow"
                                hover-variant="primary"
                                type="button"
                                @click="shareOpen = !shareOpen"
                            >
                                {{ __('Share on Social Media') }}
                            </x-button>

                            <div
                                class="absolute start-0 top-full z-10 mt-1 w-full rounded-lg border border-card-border bg-card-background shadow-lg"
                                x-show="shareOpen"
                                x-transition
                                @click.outside="shareOpen = false"
                                x-cloak
                            >
                                @foreach ($connectedVideoPlatforms as $connectedPlatform)
                                    <a
                                        class="flex items-center gap-3 px-4 py-2.5 text-xs font-medium text-foreground transition-colors first:rounded-t-lg last:rounded-b-lg hover:bg-foreground/5"
                                        :href="'{{ route('dashboard.user.social-media.post.create', ['platform' => $connectedPlatform->platform]) }}' +
                                        '&prefill_video=' + encodeURIComponent(currentVideo?.video_url || ' ') +
                                        '&prefill_content=' + encodeURIComponent(currentVideo?.prompt || ' ')"
                                    >
                                        <img
                                            class="size-5 dark:hidden"
                                            src="{{ asset('vendor/social-media/icons/' . $connectedPlatform->platform . '.svg') }}"
                                            alt="{{ $connectedPlatform->platformLabel() }}"
                                        />
                                        <img
                                            class="hidden size-5 dark:block"
                                            src="{{ asset('vendor/social-media/icons/' . $connectedPlatform->platform . '-light.svg') }}"
                                            alt="{{ $connectedPlatform->platformLabel() }}"
                                        />
                                        <span>{{ $connectedPlatform->platformLabel() }}</span>
                                        <span class="ms-auto text-2xs opacity-50">{{ $connectedPlatform->username() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @includeIf('video-editor::partials.open-with-button', [
                        'url'    => 'currentVideo?.video_url',
                        'title'  => 'currentVideo?.prompt',
                        'width'  => 'currentVideo?.width',
                        'height' => 'currentVideo?.height',
                    ])

                    {{-- Metadata --}}
                    <div class="mt-10 space-y-3.5">
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0">{{ __('Date') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="formatDate(currentVideo?.created_at) || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium">
                            <p class="mb-0">{{ __('AI Model') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="currentVideo?.model || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.formatted_duration"
                        >
                            <p class="mb-0">{{ __('Duration') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="currentVideo?.formatted_duration || '{{ __('None') }}'"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.resolution"
                        >
                            <p class="mb-0">{{ __('Resolution') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="currentVideo?.resolution"
                            ></p>
                        </div>
                        <div
                            class="flex w-full items-center justify-between gap-1 py-1.5 text-2xs font-medium"
                            x-show="currentVideo?.aspect_ratio"
                        >
                            <p class="mb-0">{{ __('Ratio') }}</p>
                            <p
                                class="mb-0 opacity-50"
                                x-text="currentVideo?.aspect_ratio"
                            ></p>
                        </div>
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
            Alpine.data('aiVideoProVideoModal', () => ({
                open: false,
                currentIndex: -1,

                get currentVideo() {
                    return this.currentIndex >= 0 && this.currentIndex < this.videos.length ?
                        this.videos[this.currentIndex] :
                        null;
                },

                openModal(videoId) {
                    this.currentIndex = this.videos.findIndex(v => v.id === videoId);
                    if (this.currentIndex >= 0) {
                        this.open = true;
                        document.documentElement.classList.add('overflow-hidden');
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
