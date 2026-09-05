<template id="social-media-agent-chat-post-card-template">
    <template x-if="post">
        <div class="flex grow flex-col rounded-[10px] border p-3.5 transition">
            <div class="mb-2.5 flex items-center justify-between gap-1">
                <div class="flex items-center gap-1 text-2xs text-heading-foreground">
                    <img
                        class="m-0 dark:hidden"
                        width="20"
                        height="20"
                        :src="`/vendor/social-media/icons/${post.platform?.platform}.svg`"
                    >
                    <img
                        class="m-0 hidden dark:block"
                        width="20"
                        height="20"
                        :src="`/vendor/social-media/icons/${post.platform?.platform}-mono-light.svg`"
                    >
                    <span x-text="post.platform?.credentials?.username"></span>
                </div>
                <span class="text-3xs opacity-55">
                    <span
                        class="capitalize"
                        x-text="post.publishing_type"
                    ></span>
                    <span class="mx-1 align-text-top text-xl leading-[0]">.</span>
                    <span
                        class="capitalize"
                        x-text="post.status"
                    ></span>
                </span>
            </div>

            <template x-if="post.video_urls?.length">
                <figure
                    class="relative z-1 mb-2.5 mt-0 grid aspect-video w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                    x-data="{
                        totalSlides: post.video_urls?.length ?? 0,
                        currentIndex: 0,
                        prev() {
                            this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                        },
                        next() {
                            this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                        }
                    }"
                >
                    <template x-for="(video_url, index) in post.video_urls">
                        <video
                            class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 m-0 size-full object-cover"
                            x-show="currentIndex == index"
                            x-cloak
                            x-transition.opacity
                            controls
                            playsinline
                            preload="metadata"
                            :src="video_url"
                        ></video>
                    </template>
                    <template x-if="post.video_urls.length >= 2">
                        <div>
                            <button
                                class="absolute start-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                title="{{ __('Previous Slide') }}"
                                @click.prevent="prev()"
                            >
                                <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                            </button>
                            <button
                                class="absolute end-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                title="{{ __('Next Slide') }}"
                                @click.prevent="next()"
                            >
                                <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                            </button>

                            <div
                                class="absolute bottom-5 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1.5 rounded-full border border-background/10 bg-background/10 p-1 backdrop-blur"
                            >
                                <template x-for="(video_url, index) in post.video_urls">
                                    <button
                                        class="relative inline-flex size-2 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-4 [&.active]:bg-white"
                                        @click.prevent="currentIndex = index"
                                        :class="{ active: currentIndex === index }"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </figure>
            </template>

            <template x-if="post.media_urls.length">
                <figure
                    class="relative z-1 mb-2.5 mt-0 grid aspect-video w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                    x-data="{
                        totalSlides: post.media_urls?.length ?? 0,
                        currentIndex: 0,
                        prev() {
                            this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                        },
                        next() {
                            this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                        }
                    }"
                >

                    <template x-for="(media_url, index) in post.media_urls">
                        <img
                            class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 m-0 size-full object-cover"
                            x-show="currentIndex == index"
                            x-cloak
                            x-transition.opacity
                            :src="media_url"
                        >
                    </template>
                    <template x-if="post.media_urls.length >= 2">
                        <div>
                            <button
                                class="absolute start-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                title="{{ __('Previous Slide') }}"
                                @click.prevent="prev()"
                            >
                                <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                            </button>
                            <button
                                class="absolute end-5 top-1/2 inline-grid size-8 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                title="{{ __('Next Slide') }}"
                                @click.prevent="next()"
                            >
                                <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                            </button>

                            <div
                                class="absolute bottom-5 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1.5 rounded-full border border-background/10 bg-background/10 p-1 backdrop-blur"
                            >
                                <template x-for="(media_url, index) in post.media_urls">
                                    <button
                                        class="relative inline-flex size-2 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-4 [&.active]:bg-white"
                                        @click.prevent="currentIndex = index"
                                        :class="{ active: currentIndex === index }"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </figure>
            </template>

            <template x-if="(!post.video_urls?.length) && ['pending', 'generating'].includes(post.video_status)">
                @include('social-media-agent::components.video-generation-loader', ['class' => 'mb-2.5'])
            </template>

            <template x-if="(!post.video_urls?.length) && post.video_status === 'failed'">
                <div class="mb-2.5 rounded-lg border border-destructive/20 bg-destructive/10 px-3 py-2 text-2xs text-destructive">
                    <div class="flex items-center gap-2 font-semibold">
                        <x-tabler-alert-triangle class="size-4" />
                        <span>@lang('Video generation failed')</span>
                    </div>
                    <p class="m-0 text-3xs text-destructive/80">
                        @lang('Open the editor to try regenerating the video or upload one manually.')
                    </p>
                </div>
            </template>

            <p
                class="mb-4 text-2xs/[1.4em] opacity-65"
                x-html="post.content"
            ></p>

            <template x-if="post.scheduled_at">
                <p class="mb-4 text-3xs opacity-55">
                    <span
                        x-text="new Date(post.scheduled_at).toLocaleString(navigator.languages.length ? navigator.languages[0] : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(',', '')"
                    ></span>
                </p>
            </template>

            <div class="mt-auto">
                <x-button
                    class="w-full p-2 text-2xs font-medium text-heading-foreground no-underline"
                    href="#"
                    variant="outline"
                    hover-variant="primary"
                    @click.prevent="openEditSidedrawer({ query: `id=${post.id}`, taskKey: `fetchingPost-${post.id}`, autoNavigateOnPostUpdates: false })"
                    ::class="{ 'opacity-50 pointer-events-none': currentTasks.has(`fetchingPost-${post.id}`) }"
                >
                    <x-tabler-loader-2
                        class="size-4 animate-spin"
                        x-show="currentTasks.has(`fetchingPost-${post.id}`)"
                        x-cloak
                    />
                    @lang('Schedule')
                </x-button>
            </div>
        </div>
    </template>

    <template x-if="!currentTasks.has('fetchingPost') && !post">
        <div class="flex grow flex-col rounded-[10px] border p-3.5 transition">
            <p class="m-0 text-xs font-medium">
                @lang('Post was removed')
            </p>
        </div>
    </template>
</template>
