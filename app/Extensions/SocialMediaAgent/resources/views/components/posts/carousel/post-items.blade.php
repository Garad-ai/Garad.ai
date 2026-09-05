@php
    use Illuminate\Support\Str;
@endphp

@forelse($posts as $post)
    @php
        $platform_enum = $post->getPlatformEnum();

        $platform_image = asset('vendor/social-media/icons/' . $platform_enum?->value . '.svg');
        $platform_image_dark = asset('vendor/social-media/icons/' . $platform_enum?->value . '-mono-light.svg');
        $platform_username = $post->platform?->username();

        $social_post = $post->socialPost;
        $post_metrics = $social_post?->post_metrics ?? [];
        $impressions =
            data_get($post_metrics, 'view_count') ??
            (data_get($post_metrics, 'impression_count') ?? (data_get($post_metrics, 'impressions') ?? ($social_post?->post_engagement_count ?? 0)));
        $engagement = $social_post?->post_engagement_count ?? (int) data_get($post_metrics, 'like_count', 0) + (int) data_get($post_metrics, 'comment_count', 0);
        $is_trending = ($social_post?->post_engagement_rate ?? 0) >= 15 || $impressions >= 1000;
        $published_link = $social_post?->link ?: ($social_post?->id ? route('dashboard.user.social-media.post.show', $social_post->id) : null);
        $has_preview_media = (is_array($post->video_urls) && count($post->video_urls) > 0)
            || (is_array($post->media_urls) && count($post->media_urls) > 0);
    @endphp

    <div
        class="social-media-agent-post-item group flex w-full flex-shrink-0 flex-grow-0 basis-auto flex-col px-5 py-2 sm:w-1/2 lg:w-1/4"
        data-post-id="{{ $post->id }}"
        :class="{ 'animate-pulse pointer-events-none': currentTasks.has('approvePost') || currentTasks.has('rejectPost') }"
        x-data='socialMediaAgentPostItem({ postId: {{ $post->id }}, status: "{{ $post->status }}", media_urls: @json($post->media_urls), video_urls: @json($post->video_urls ?? []), video_status: "{{ $post->video_status ?? 'none' }}", video_request_id: "{{ $post->video_request_id }}", videoStatusEndpoint: "{{ route('dashboard.user.social-media.agent.video.status') }}", image_status: "{{ $post->image_status ?? 'none' }}", image_request_id: "{{ $post->image_request_id }}", imageStatusEndpoint: "{{ route('dashboard.user.social-media.agent.image.status') }}", agent_id: {{ $post->agent_id ?? 'null' }}, platform_id: {{ $post->platform_id }}, platform: @json($post->platform), post_type: "{{ $post->post_type }}", publishing_type: "{{ optional($post->publishing_type)->value ?? 'post' }}", scheduled_at: "{{ $post->scheduled_at }}", content: @json($post->content), editSidedrawerId: "#social-media-agent-sidedrawer" })'
    >
        <div class="flex grow flex-col rounded-[10px] border p-3.5 transition">
            <div class="mb-2.5 flex items-center justify-between gap-1">
                <div class="flex items-center gap-1 overflow-hidden text-2xs text-heading-foreground">
                    <img
                        class="shrink-0 dark:hidden"
                        width="20"
                        height="20"
                        src="{{ $platform_image }}"
                        :src="`/vendor/social-media/icons/${platform.platform}.svg`"
                        alt="{{ $post->content }}"
                    >
                    <img
                        class="hidden shrink-0 dark:block"
                        width="20"
                        height="20"
                        src="{{ $platform_image_dark }}"
                        :src="`/vendor/social-media/icons/${platform.platform}-mono-light.svg`"
                        alt="{{ $post->content }}"
                    >
                    <span
                        class="w-full truncate"
                        x-text="platform.credentials.username"
                        title="{{ $platform_username }}"
                    >
                        {{ $platform_username }}
                    </span>
                </div>
                <span class="whitespace-nowrap text-3xs opacity-55">
                    <span
                        class="capitalize"
                        x-text="publishing_type"
                    >
                        {{ optional($post->publishing_type)->value ? Str::headline($post->publishing_type?->value) : __('Post') }}
                    </span>
                    <span class="mx-1 align-text-top text-xl leading-[0]">.</span>
                    <span x-text="statusState.label">
                        {{ Str::headline($post->status) }}
                    </span>
                </span>
            </div>

            {{-- placeholder --}}
            @if ($has_preview_media)
                <div
                    class="mb-3 aspect-video"
                    x-init="$el.remove()"
                ></div>
            @endif

            <template x-if="previewVideos.length">
                <figure
                    class="relative z-1 mb-3 grid aspect-video w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                    x-data="{
                        totalSlides: previewVideos?.length ?? 0,
                        currentIndex: 0,
                        prev() {
                            this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                        },
                        next() {
                            this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                        }
                    }"
                >
                    <template x-for="(video_url, index) in previewVideos">
                        <video
                            class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover"
                            x-show="currentIndex == index"
                            x-cloak
                            x-transition.opacity
                            muted
                            autoplay
                            playsinline
                            loop
                            controls
                            preload="metadata"
                            :src="video_url"
                        ></video>
                    </template>
                    <template x-if="video_urls.length >= 2">
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
                                class="absolute bottom-5 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1.5 rounded-full border border-background/10 bg-background/10 p-1 backdrop-blur">
                                <template x-for="(video_url, index) in previewVideos">
                                    <button
                                        class="relative inline-flex size-2 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-4 [&.active]:bg-white"
                                        @click.prevent="currentIndex = index"
                                        :class="{ active: currentIndex === index }"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">
                        <x-tabler-device-tv class="size-4" />
                        <span>@lang('Video')</span>
                    </div>
                </figure>
            </template>

            <template x-if="!previewVideos.length && ['pending', 'generating'].includes(video_status)">
                @include('social-media-agent::components.video-generation-loader', ['class' => 'mb-3'])
            </template>

            <template x-if="!previewVideos.length && !previewImages.length && image_status === 'failed'">
                <div class="relative z-1 mb-3 flex aspect-video w-full flex-col items-center justify-center gap-3 overflow-hidden rounded-lg border border-dashed border-red-500/40 bg-red-500/5 p-6 text-center shadow-sm shadow-black/5">
                    <x-tabler-photo-off class="size-12 text-red-500/70" />
                    <div>
                        <p class="mb-1 text-sm font-semibold text-red-700 dark:text-red-400">
                            @lang('Image Generation Failed')
                        </p>
                        <p class="mb-3 text-xs text-red-600/80 dark:text-red-400/80">
                            @lang('Something went wrong while generating the image.')
                        </p>
                        <x-button
                            size="sm"
                            variant="ghost-shadow"
                            hover-variant="danger"
                            @click.prevent="regeneratePostImage({{ $post->id }})"
                            ::disabled="currentTasks.has('regenerateImage-' + postId)"
                            ::class="{ 'opacity-50 pointer-events-none': currentTasks.has('regenerateImage-' + postId) }"
                        >
                            <x-tabler-loader-2
                                class="size-4 animate-spin"
                                x-show="currentTasks.has('regenerateImage-' + postId)"
                                x-cloak
                            />
                            <x-tabler-refresh
                                class="size-4"
                                x-show="!currentTasks.has('regenerateImage-' + postId)"
                            />
                            @lang('Regenerate Image')
                        </x-button>
                    </div>
                </div>
            </template>

            <template x-if="!previewVideos.length && !previewImages.length && ['pending', 'generating', 'in_queue', 'in_progress'].includes(image_status)">
				<div class="lqd-loading-skeleton lqd-is-loading relative aspect-video overflow-hidden rounded-lg w-full">
					<div
						class="absolute size-full"
						data-lqd-skeleton-el
					></div>
				</div>
            </template>


            <template x-if="!previewVideos.length && previewImages.length">
                <figure
                    class="relative z-1 mb-3 grid aspect-video w-full grid-cols-1 place-items-center overflow-hidden rounded-lg shadow-sm shadow-black/5"
                    x-data="{
                        totalSlides: previewImages?.length ?? 0,
                        currentIndex: 0,
                        prev() {
                            this.currentIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.totalSlides - 1;
                        },
                        next() {
                            this.currentIndex = this.currentIndex < this.totalSlides - 1 ? this.currentIndex + 1 : 0;
                        }
                    }"
                >
                    <template x-for="(media_url, index) in previewImages">
                        <img
                            class="absolute start-0 top-0 col-start-1 col-end-1 row-start-1 row-end-1 size-full object-cover"
                            x-show="currentIndex == index"
                            x-cloak
                            x-transition.opacity
                            :src="media_url"
                        >
                    </template>
                    <template x-if="media_urls.length >= 2">
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
                                class="absolute bottom-5 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1.5 rounded-full border border-background/10 bg-background/10 p-1 backdrop-blur">
                                <template x-for="(media_url, index) in media_urls">
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

            <p
                @class([
                    'mb-3.5 inline-flex items-center gap-1.5 rounded-md px-2.5 py-[3px] text-[12px] font-medium self-start',
                    'bg-blue-500/15 text-blue-700 dark:text-blue-300' =>
                        $post->status === 'draft',
                    'bg-yellow-500/15 text-yellow-700 dark:text-yellow-300' =>
                        $post->status === 'scheduled',
                    'bg-green-500/15 text-green-700 dark:text-green-300' =>
                        $post->status === 'published',
                    'bg-red-500/15 text-red-700 dark:text-red-300' =>
                        $post->status === 'failed',
                ])
                :class="statusState.css"
            >
                <span x-text="statusState.label">
                    {{ Str::headline($post->status) }}
                </span>
                <span x-html="statusState.icon">
                    {{-- blade-formatter-disable --}}
					@if ($post->status === 'published')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"> <path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -14.995 8.984l-.005 -.324l.005 -.324a10 10 0 0 1 14.995 -8.336zm-1.293 5.953a1 1 0 0 0 -1.32 -.083l-.094 .083l-3.293 3.292l-1.293 -1.292l-.094 -.083a1 1 0 0 0 -1.403 1.403l.083 .094l2 2l.094 .083a1 1 0 0 0 1.226 0l.094 -.083l4 -4l.083 -.094a1 1 0 0 0 -.083 -1.32z"/></svg>
					@elseif($post->status === 'failed')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>
					@elseif($post->status === 'draft')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/><path d="M9 15l4 0"/></svg>
					@else
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>
					@endif
					{{-- blade-formatter-enable --}}
                </span>
            </p>

            @if (filled($post->content))
                <p
                    class="mb-3 text-2xs/[1.4em] opacity-65"
                    x-text="content"
                >
                    {{ $post->content }}
                </p>
            @endif

            <p class="mb-4 text-3xs opacity-55">
                @if ($post->status === 'published')
                    {{ $post->published_at }}
                @else
                    <span
                        x-text="scheduled_at ? new Date(scheduled_at).toLocaleString(navigator.languages.length ? navigator.languages[0] : 'en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).replace(',', '') : ''"
                    >
                        {{ $post->scheduled_at }}
                    </span>
                @endif
            </p>

            @if ($post->status === 'published' && $is_trending)
                <p class="mb-4 flex items-center gap-2 text-3xs font-medium text-foreground/55">
                    <svg
                        class="shrink-0"
                        width="17"
                        height="17"
                        viewBox="0 0 17 17"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M8.33333 16.6667C6.94444 16.6667 5.67014 16.3611 4.51042 15.75C3.35069 15.1389 2.38889 14.3194 1.625 13.2917L5.04167 9.875L7.54167 11.9583L11.6667 7.83333V10H13.3333V5H8.33333V6.66667H10.5L7.45833 9.70833L4.95833 7.625L0.75 11.8333C0.513889 11.3056 0.329861 10.7465 0.197917 10.1562C0.0659722 9.56597 0 8.95833 0 8.33333C0 7.18055 0.21875 6.09722 0.65625 5.08333C1.09375 4.06944 1.6875 3.1875 2.4375 2.4375C3.1875 1.6875 4.06944 1.09375 5.08333 0.65625C6.09722 0.21875 7.18055 0 8.33333 0C9.48611 0 10.5694 0.21875 11.5833 0.65625C12.5972 1.09375 13.4792 1.6875 14.2292 2.4375C14.9792 3.1875 15.5729 4.06944 16.0104 5.08333C16.4479 6.09722 16.6667 7.18055 16.6667 8.33333C16.6667 9.48611 16.4479 10.5694 16.0104 11.5833C15.5729 12.5972 14.9792 13.4792 14.2292 14.2292C13.4792 14.9792 12.5972 15.5729 11.5833 16.0104C10.5694 16.4479 9.48611 16.6667 8.33333 16.6667Z"
                            fill="#1B8646"
                        />
                    </svg>
                    @lang('Trending Post')
                </p>
            @endif

            <hr>

            <div class="mb-5 grid grid-cols-1 sm:grid-cols-2">
                <div>
                    <p class="mb-1 text-3xs font-medium text-foreground/65">
                        @lang('Impressions')
                    </p>
                    <p class="m-0 font-medium text-heading-foreground">
                        {{ $impressions }}
                    </p>
                </div>
                <div>
                    <p class="mb-1 text-3xs font-medium text-foreground/65">
                        @lang('Engagement')
                    </p>
                    <p class="m-0 font-medium text-heading-foreground">
                        {{ $engagement }}%
                    </p>
                </div>
            </div>

            <div class="mb-5 flex items-center gap-1">
                @if ($post->status === 'draft')
                    <x-button
                        class="size-6 bg-transparent text-heading-foreground hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                        size="none"
                        title="{{ __('Approve') }}"
                        @click.prevent="editingPostInitialStatus = 'draft'; approvePost({{ $post->id }})"
                        ::disabled="currentTasks.has('approvePost')"
                        x-show="status !== 'approved' && status !== 'scheduled'"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M11.425 5.7625C11.5128 5.85039 11.5621 5.96953 11.5621 6.09375C11.5621 6.21797 11.5128 6.33711 11.425 6.425L7.05 10.8C6.96211 10.8878 6.84297 10.9371 6.71875 10.9371C6.59453 10.9371 6.47539 10.8878 6.3875 10.8L4.5125 8.925C4.4297 8.83614 4.38463 8.71861 4.38677 8.59717C4.38891 8.47574 4.43811 8.35987 4.52399 8.27399C4.60987 8.1881 4.72574 8.13891 4.84718 8.13677C4.96862 8.13462 5.08614 8.1797 5.175 8.2625L6.71875 9.80547L10.7625 5.7625C10.8504 5.67472 10.9695 5.62541 11.0938 5.62541C11.218 5.62541 11.3371 5.67472 11.425 5.7625ZM15.9375 7.96875C15.9375 9.54482 15.4701 11.0855 14.5945 12.396C13.7189 13.7064 12.4744 14.7278 11.0183 15.3309C9.56216 15.9341 7.95991 16.0919 6.41413 15.7844C4.86834 15.4769 3.44845 14.718 2.334 13.6035C1.21955 12.4891 0.460597 11.0692 0.153121 9.52338C-0.154355 7.97759 0.0034526 6.37534 0.606588 4.91924C1.20972 3.46314 2.2311 2.21859 3.54155 1.34298C4.85201 0.467359 6.39268 0 7.96875 0C10.0814 0.00248113 12.1069 0.84284 13.6008 2.33673C15.0947 3.83063 15.935 5.85607 15.9375 7.96875ZM15 7.96875C15 6.5781 14.5876 5.21868 13.815 4.0624C13.0424 2.90611 11.9443 2.0049 10.6595 1.47272C9.3747 0.940543 7.96095 0.801301 6.59702 1.0726C5.2331 1.34391 3.98025 2.01357 2.99691 2.99691C2.01357 3.98024 1.34391 5.23309 1.07261 6.59702C0.801305 7.96095 0.940547 9.3747 1.47273 10.6595C2.0049 11.9443 2.90612 13.0424 4.0624 13.815C5.21868 14.5876 6.5781 15 7.96875 15C9.83292 14.9979 11.6201 14.2565 12.9383 12.9383C14.2565 11.6201 14.9979 9.83292 15 7.96875Z"
                            />
                        </svg>
                    </x-button>
                @endif

                @if ($post->status !== 'published')
                    <x-button
                        class="size-6 bg-transparent text-heading-foreground hover:text-primary-foreground"
                        ::class="{ 'opacity-50 pointer-events-none': currentTasks.has('regeneratePost-' + postId) }"
                        size="none"
                        @click.prevent="regeneratePostContent({{ $post->id }})"
                        title="{{ __('Regenerate Post Content') }}"
                    >
                        <x-tabler-loader-2
                            class="size-4 animate-spin"
                            x-show="currentTasks.has('regeneratePost-' + postId)"
                            x-cloak
                        />
                        <svg
                            width="13"
                            height="15"
                            viewBox="0 0 13 15"
                            fill="currentColor"
                            xmlns="http://www.w3.org/2000/svg"
                            x-show="!currentTasks.has('regeneratePost-' + postId)"
                        >
                            <path
                                d="M5.41665 14.4454C3.85362 14.2425 2.56009 13.5539 1.53606 12.3797C0.512021 11.2056 0 9.83008 0 8.25317C0 7.40594 0.167201 6.59425 0.501604 5.81808C0.836007 5.04192 1.30449 4.36003 1.90704 3.77242L2.79804 4.66344C2.28629 5.13031 1.90035 5.67224 1.64021 6.28923C1.38006 6.90621 1.24998 7.56086 1.24998 8.25317C1.24998 9.47539 1.6402 10.5526 2.42065 11.4847C3.2011 12.4169 4.19977 12.9871 5.41665 13.1955V14.4454ZM7.08331 14.4615V13.2115C8.2863 12.9679 9.28149 12.3848 10.0689 11.4623C10.8563 10.5397 11.25 9.47004 11.25 8.25317C11.25 6.86428 10.7639 5.68372 9.79165 4.7115C8.81942 3.73928 7.63887 3.25317 6.24998 3.25317H5.95508L7.0801 4.37819L6.20192 5.25637L3.57373 2.62819L6.20192 0L7.0801 0.878187L5.95508 2.00319H6.24998C7.99356 2.00319 9.47112 2.60896 10.6826 3.8205C11.8942 5.03203 12.5 6.50958 12.5 8.25317C12.5 9.82153 11.9866 11.1901 10.9599 12.3589C9.93319 13.5277 8.64099 14.2286 7.08331 14.4615Z"
                            />
                        </svg>
                    </x-button>
                @endif

                <x-dropdown.dropdown offsetY="10px">
                    <x-slot:trigger
                        class="size-6 bg-transparent text-heading-foreground hover:bg-primary hover:text-primary-foreground"
                        size="none"
                    >
                        <span class="sr-only">
                            @lang('More')
                        </span>
                        <x-tabler-dots class="size-4" />
                    </x-slot:trigger>

                    <x-slot:dropdown
                        class="min-w-[150px] rounded-lg bg-background p-2 shadow-lg"
                    >
                        @if ($post->status === 'published' && $published_link)
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-foreground/5"
                                href="{{ $published_link }}"
                                target="_blank"
                            >
                                @lang('View')
                            </a>
                        @endif
                        @if ($post->status !== 'published')
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-foreground/5"
                                href="#"
                                @click.prevent="$dispatch('social-media-agent:duplicate-post', { id: {{ $post->id }} })"
                            >
                                @lang('Duplicate')
                            </a>
                        @endif
                        @if ($post->status !== 'published')
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-red-500 hover:text-white"
                                href="#"
                                @click.prevent="rejectPost({{ $post->id }}); toggle('collapse');"
                            >
                                @lang('Delete')
                            </a>
                        @endif
                    </x-slot:dropdown>
                </x-dropdown.dropdown>
            </div>

            <div class="mt-auto pb-4">
                @if ($post->status === 'published' && $published_link)
                    <x-button
                        class="w-full"
                        href="{{ $published_link }}"
                        target="_blank"
                        variant="outline"
                        hover-variant="primary"
                    >
                        @lang('View')
                    </x-button>
                @else
                    <x-button
                        class="w-full"
                        href="#"
                        variant="outline"
                        hover-variant="primary"
                        @click.prevent="openEditSidedrawer({ query: 'id={{ $post->id }}', taskKey: 'fetchingPost-{{ $post->id }}' })"
                        ::class="{ 'opacity-50 pointer-events-none': currentTasks.has('fetchingPost-{{ $post->id }}') }"
                    >
                        <x-tabler-loader-2
                            class="size-4 animate-spin"
                            x-show="currentTasks.has('fetchingPost-{{ $post->id }}')"
                            x-cloak
                        />
                        @lang('Edit')
                    </x-button>
                @endif
            </div>
        </div>
    </div>
@empty
    <h4 class="social-media-agent-post-item group flex w-full flex-shrink-0 flex-grow-0 basis-auto px-5 py-2 text-lg">
        @lang('No posts were found.')
    </h4>
@endforelse

@if ($posts->hasMorePages())
    <span
        class="hidden"
        data-next-page-url="{{ $posts->nextPageUrl() }}"
        aria-hidden="true"
    ></span>
@endif

@pushOnce('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('socialMediaAgentPostItem', ({
                    postId,
                    status,
                    media_urls,
                    video_urls = [],
                    video_status = 'none',
                    video_request_id = null,
                    videoStatusEndpoint = '',
                    image_status = 'none',
                    image_request_id = null,
                    imageStatusEndpoint = '',
                    agent_id = null,
                    platform_id,
                    platform,
                    post_type,
                    publishing_type = 'post',
                    scheduled_at,
                    content,
                    editSidedrawerId
                }) => ({
                    postId,
                    status,
                    media_urls,
                    video_urls,
                    video_status,
                    video_request_id,
                    image_status,
                    image_request_id,
                    agent_id,
                    platform_id,
                    platform,
                    post_type,
                    publishing_type,
                    scheduled_at,
                    content,
                    editSidedrawerId,
                    videoPollingTimer: null,
                    videoPollingDelay: 6000,
                    videoPollingAttempts: 0,
                    videoPollingMaxAttempts: 40,
                    imagePollingTimer: null,
                    imagePollingDelay: 6000,
                    imagePollingAttempts: 0,
                    imagePollingMaxAttempts: 40,
                    videoExtensions: ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'],
                    statusStates: {
                        'draft': {
                            label: '{{ __('Draft') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M9 7l6 0"/><path d="M9 11l6 0"/><path d="M9 15l4 0"/></svg>',
                            css: 'bg-blue-500/15 text-blue-700 dark:text-blue-400'
                        },
                        'scheduled': {
                            label: '{{ __('Scheduled') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>',
                            css: 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400'
                        },
                        'published': {
                            label: '{{ __('Published') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -14.995 8.984l-.005 -.324l.005 -.324a10 10 0 0 1 14.995 -8.336zm-1.293 5.953a1 1 0 0 0 -1.32 -.083l-.094 .083l-3.293 3.292l-1.293 -1.292l-.094 -.083a1 1 0 0 0 -1.403 1.403l.083 .094l2 2l.094 .083a1 1 0 0 0 1.226 0l.094 -.083l4 -4l.083 -.094a1 1 0 0 0 -.083 -1.32z" /></svg>',
                            css: 'bg-green-500/15 text-green-700 dark:text-green-400'
                        },
                        'failed': {
                            label: '{{ __('Failed') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>',
                            css: 'bg-red-500/15 text-red-700 dark:text-red-400'
                        },
                    },

                    get statusState() {
                        return this.statusStates[this.status]
                    },

                    init() {
                        console.log('[Image Polling Debug] Post ID:', this.postId, {
                            image_request_id: this.image_request_id,
                            image_status: this.image_status,
                            media_urls: this.media_urls,
                            imageStatusEndpoint: this.imageStatusEndpoint
                        });

                        this.evaluateVideoPolling();
                        this.$watch('video_status', () => this.evaluateVideoPolling());
                        this.$watch('video_urls', () => this.evaluateVideoPolling());
                        this.$watch('media_urls', () => this.evaluateVideoPolling());
                        this.$watch('video_request_id', () => this.evaluateVideoPolling());

                        this.evaluateImagePolling();
                        this.$watch('image_status', () => this.evaluateImagePolling());
                        this.$watch('media_urls', () => this.evaluateImagePolling());
                        this.$watch('image_request_id', () => this.evaluateImagePolling());

                        if (this.$cleanup) {
                            this.$cleanup(() => {
                                this.stopVideoPolling();
                                this.stopImagePolling();
                            });
                        }
                    },

                    get previewVideos() {
                        const explicitVideos = Array.isArray(this.video_urls) ? this.video_urls.filter(url => !!url) : [];
                        if (explicitVideos.length) {
                            return explicitVideos;
                        }

                        const mediaVideos = Array.isArray(this.media_urls)
                            ? this.media_urls.filter(url => this.isVideoUrl(url))
                            : [];

                        return mediaVideos;
                    },

                    get previewImages() {
                        const media = Array.isArray(this.media_urls) ? this.media_urls.filter(url => !!url) : [];

                        if (this.previewVideos.length) {
                            return media.filter(url => !this.isVideoUrl(url));
                        }

                        return media;
                    },

                    isVideoUrl(url) {
                        if (typeof url !== 'string') {
                            return false;
                        }

                        const cleanUrl = url.split('?')[0].split('#')[0].toLowerCase();

                        return this.videoExtensions.some(ext => cleanUrl.endsWith('.' + ext));
                    },

                    shouldPollVideo() {
                        const hasVideo = this.previewVideos.length > 0;

                        return !hasVideo
                            && ['pending', 'generating', 'in_queue'].includes(this.video_status ?? 'none')
                            && !!this.video_request_id
                            && !!this.videoStatusEndpoint;
                    },

                    evaluateVideoPolling() {
                        if (this.shouldPollVideo()) {
                            this.startVideoPolling();
                        } else {
                            this.stopVideoPolling();
                        }
                    },

                    startVideoPolling() {
                        if (this.videoPollingTimer) {
                            return;
                        }

                        this.videoPollingAttempts = 0;
                        this.scheduleVideoPolling();
                    },

                    scheduleVideoPolling() {
                        this.stopVideoPolling();

                        this.videoPollingTimer = setTimeout(() => {
                            this.pollVideoStatus();
                        }, this.videoPollingDelay);
                    },

                    stopVideoPolling() {
                        if (!this.videoPollingTimer) {
                            return;
                        }

                        clearTimeout(this.videoPollingTimer);
                        this.videoPollingTimer = null;
                    },

                    async pollVideoStatus() {
                        if (!this.video_request_id || !this.videoStatusEndpoint) {
                            return;
                        }

                        this.videoPollingAttempts += 1;

                        const data = await this.fetchVideoStatus();

                        if (data?.success) {
                            if (data.post) {
                                this.applyPostUpdate(data.post);
                            } else if (data.status) {
                                this.video_status = data.status;
                            }
                        } else if (data?.status === 'failed') {
                            this.video_status = 'failed';
                        }

                        if (this.shouldPollVideo() && this.videoPollingAttempts < this.videoPollingMaxAttempts) {
                            this.scheduleVideoPolling();
                        } else {
                            this.stopVideoPolling();
                        }
                    },

                    async fetchVideoStatus({ suppressErrors = true } = {}) {
                        if (!this.video_request_id || !this.videoStatusEndpoint) {
                            return null;
                        }

                        try {
                            const url = `${this.videoStatusEndpoint}?request_id=${encodeURIComponent(this.video_request_id)}`;
                            const res = await fetch(url, {
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });

                            const data = await res.json();

                            return data;
                        } catch (error) {
                            if (!suppressErrors) {
                                toastr.error(error.message ?? '{{ __('Failed fetching video status') }}');
                            }

                            return null;
                        }
                    },

                    // Image polling methods
                    shouldPollImage() {
                        const hasNoImages = !Array.isArray(this.media_urls) || this.media_urls.length === 0;
                        const shouldPoll = hasNoImages
                            && ['pending', 'generating', 'in_queue', 'in_progress'].includes(this.image_status ?? 'none')
                            && !!this.image_request_id
                            && !!this.imageStatusEndpoint;

                        console.log('[Image Polling] shouldPollImage:', shouldPoll, {
                            hasNoImages,
                            image_status: this.image_status,
                            image_request_id: this.image_request_id,
                            imageStatusEndpoint: this.imageStatusEndpoint,
                            media_urls: this.media_urls
                        });

                        return shouldPoll;
                    },

                    evaluateImagePolling() {
                        const should = this.shouldPollImage();
                        console.log('[Image Polling] evaluateImagePolling - shouldPoll:', should);

                        if (should) {
                            console.log('[Image Polling] Starting image polling...');
                            this.startImagePolling();
                        } else {
                            this.stopImagePolling();
                        }
                    },

                    startImagePolling() {
                        if (this.imagePollingTimer) {
                            console.log('[Image Polling] Already polling, timer exists');
                            return;
                        }

                        console.log('[Image Polling] ✅ Starting NEW polling timer');
                        this.imagePollingAttempts = 0;
                        this.scheduleImagePolling();
                    },

                    scheduleImagePolling() {
                        this.stopImagePolling();

                        console.log('[Image Polling] ⏰ Scheduled next poll in', this.imagePollingDelay, 'ms');

                        this.imagePollingTimer = setTimeout(() => {
                            this.pollImageStatus();
                        }, this.imagePollingDelay);
                    },

                    stopImagePolling() {
                        if (!this.imagePollingTimer) {
                            return;
                        }

                        clearTimeout(this.imagePollingTimer);
                        this.imagePollingTimer = null;
                    },

                    async pollImageStatus() {
                        if (!this.image_request_id || !this.imageStatusEndpoint) {
                            return;
                        }

                        this.imagePollingAttempts += 1;

                        console.log('[Image Polling] Polling attempt', this.imagePollingAttempts, 'for request:', this.image_request_id);

                        const data = await this.fetchImageStatus();

                        if (data?.success) {
                            if (data.post) {
                                this.applyPostUpdate(data.post);
                            } else if (data.status) {
                                this.image_status = data.status;
                            }
                        } else if (data?.status === 'failed') {
                            this.image_status = 'failed';
                        }

                        if (this.shouldPollImage() && this.imagePollingAttempts < this.imagePollingMaxAttempts) {
                            this.scheduleImagePolling();
                        } else {
                            this.stopImagePolling();
                        }
                    },

                    async fetchImageStatus({ suppressErrors = true } = {}) {
                        if (!this.image_request_id || !this.imageStatusEndpoint) {
                            return null;
                        }

                        try {
                            const url = `${this.imageStatusEndpoint}?request_id=${encodeURIComponent(this.image_request_id)}`;
                            console.log('[Image Polling] 🌐 Fetching:', url);

                            const res = await fetch(url, {
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });

                            const data = await res.json();
                            console.log('[Image Polling] 📦 Response:', data);

                            return data;
                        } catch (error) {
                            if (!suppressErrors) {
                                toastr.error(error.message ?? '{{ __('Failed fetching image status') }}');
                            }

                            return null;
                        }
                    },

                    async regeneratePostImage(postId) {
						@if(\App\Helpers\Classes\Helper::appIsDemo())
							toastr.error('{{ __('This action is disabled in the demo.') }}');
							return;
						@endif

                        if (!this.agent_id) {
                            toastr.error('{{ __('Agent ID is required to regenerate image.') }}');
                            return;
                        }

                        const taskKey = `regenerateImage-${postId}`;

                        if (this.currentTasks.has(taskKey)) {
                            return;
                        }

                        this.currentTasks.add(taskKey);

                        try {
                            const res = await fetch('{{ route('dashboard.user.social-media.agent.api.posts.generate-image') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    content: this.content,
                                    platform_id: this.platform_id,
                                    agent_id: this.agent_id,
                                    post_id: postId,
                                })
                            });

                            const data = await res.json();

                            if (!res.ok || !data.success) {
                                throw new Error(data.message || '{{ __('Failed to regenerate image.') }}');
                            }

                            toastr.success(data.message || '{{ __('Image regeneration started successfully.') }}');

                            // Update post with response data
                            if (data.post) {
                                this.applyPostUpdate(data.post);
                            } else {
                                // Fallback: Update image status to show loader
                                this.image_status = data.status || 'pending';
                                this.image_request_id = data.request_id || null;
                            }

                            // Start polling for the new image
                            this.evaluateImagePolling();
                        } catch (error) {
                            toastr.error(error.message || '{{ __('Failed to regenerate image.') }}');
                        } finally {
                            this.currentTasks.delete(taskKey);
                        }
                    },

                    applyPostUpdate(post) {
                        const clone = { ...post };

                        ['status', 'media_urls', 'video_urls', 'video_status', 'video_request_id', 'image_status', 'image_request_id', 'platform_id', 'platform', 'post_type', 'scheduled_at', 'published_at', 'content'].forEach(prop => {
                            if (Object.prototype.hasOwnProperty.call(clone, prop)) {
                                this[prop] = clone[prop];
                            }
                        });
                    },
                }))
            });
        })
        ();
    </script>
@endPushOnce
