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

    <tr
        class="social-media-agent-post-item"
        data-post-id="{{ $post->id }}"
        :class="{ 'animate-pulse pointer-events-none': currentTasks.has('approvePost') || currentTasks.has('rejectPost') }"
        x-data='socialMediaAgentPostItem({ postId: {{ $post->id }}, status: "{{ $post->status }}", media_urls: @json($post->media_urls), video_urls: @json($post->video_urls ?? []), video_status: "{{ $post->video_status ?? 'none' }}", video_request_id: "{{ $post->video_request_id }}", videoStatusEndpoint: "{{ route('dashboard.user.social-media.agent.video.status') }}", image_status: "{{ $post->image_status ?? 'none' }}", image_request_id: "{{ $post->image_request_id }}", imageStatusEndpoint: "{{ route('dashboard.user.social-media.agent.image.status') }}", agent_id: {{ $post->agent_id ?? 'null' }}, platform_id: {{ $post->platform_id }}, platform: @json($post->platform), post_type: "{{ $post->post_type }}", scheduled_at: "{{ $post->scheduled_at }}", published_at: "{{ $post->published_at }}", content: @json($post->content), editSidedrawerId: "#social-media-agent-sidedrawer" })'
    >
        <td>
            <div class="flex min-h-[36px] w-full items-center gap-2 overflow-hidden">
                {{-- placeholder --}}
                @if ($has_preview_media)
                    <div
                        class="aspect-video w-28 shrink-0"
                        x-init="$el.remove()"
                    ></div>
                @endif

                <template x-if="previewVideos.length">
                    <figure
                        class="relative z-1 grid aspect-video h-[36px] w-[38px] shrink-0 grid-cols-1 place-items-center overflow-hidden rounded shadow-sm shadow-black/5"
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
                                controls
                                playsinline
                                loop
                                preload="metadata"
                                :src="video_url"
                            ></video>
                        </template>
                        <div class="absolute bottom-1 left-1 inline-flex items-center gap-1 rounded-full bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                            <x-tabler-device-tv class="size-3" />
                            <span>@lang('Video')</span>
                        </div>
                    </figure>
                </template>

                <template x-if="!previewVideos.length && ['pending', 'generating'].includes(video_status)">
                    <div class="w-36">
                        @include('social-media-agent::components.video-generation-loader', [
                            'variant' => 'micro',
                            'class' => 'mb-0 w-full'
                        ])
                    </div>
                </template>

                <template x-if="!previewVideos.length && !previewImages.length && image_status === 'failed'">
                    <div class="flex w-36 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-red-500/40 bg-red-500/5 p-2 text-center">
                        <x-tabler-photo-off class="size-6 text-red-500/70" />
                        <x-button
                            size="none"
                            class="size-7 p-0"
                            variant="ghost-shadow"
                            hover-variant="danger"
                            @click.prevent="regeneratePostImage({{ $post->id }})"
                            ::disabled="currentTasks.has('regenerateImage-' + postId)"
                            ::class="{ 'opacity-50 pointer-events-none': currentTasks.has('regenerateImage-' + postId) }"
                            title="{{ __('Regenerate Image') }}"
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
                        </x-button>
                    </div>
                </template>

				<template x-if="!previewVideos.length && !previewImages.length && ['pending', 'generating', 'in_queue', 'in_progress'].includes(image_status)">
					<div class="lqd-loading-skeleton lqd-is-loading relative aspect-square overflow-hidden rounded w-[38px] h-[36px]">
						<div
							class="absolute size-full"
							data-lqd-skeleton-el
						></div>
					</div>
				</template>

                <template x-if="!previewVideos.length && previewImages.length">
                    <figure
                        class="relative z-1 grid aspect-video h-[36px] w-[38px] shrink-0 grid-cols-1 place-items-center overflow-hidden rounded shadow-sm shadow-black/5"
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
                        {{-- <template x-if="media_urls.length >= 2">
                            <div>
                                <button
                                    class="absolute start-1 top-1/2 inline-grid size-5 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                    title="{{ __('Previous Slide') }}"
                                    @click.prevent="prev()"
                                >
                                    <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                                </button>
                                <button
                                    class="absolute end-1 top-1/2 inline-grid size-5 -translate-y-1/2 place-items-center rounded-full bg-background text-foreground transition hover:scale-105 active:scale-95"
                                    title="{{ __('Next Slide') }}"
                                    @click.prevent="next()"
                                >
                                    <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                                </button>

                                <div
                                    class="absolute bottom-1 left-1/2 z-2 inline-flex -translate-x-1/2 gap-1 rounded-full border border-background/10 bg-background/10 p-0.5 backdrop-blur">
                                    <template x-for="(media_url, index) in media_urls">
                                        <button
                                            class="relative inline-flex size-1.5 rounded-full bg-white/50 transition before:absolute before:-inset-x-1 before:-inset-y-1 hover:bg-white/80 active:scale-95 [&.active]:w-2.5 [&.active]:bg-white"
                                            @click.prevent="currentIndex = index"
                                            :class="{ active: currentIndex === index }"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template> --}}
                    </figure>
                </template>

                <p
                    class="m-0 w-full max-w-[min(35vw,350px)] truncate text-sm font-medium empty:hidden"
                    x-text="content"
                >{{ $post->content }}</p>
            </div>
        </td>

        <td class="min-w-40">
            <p
                @class([
                    'm-0 inline-flex items-center gap-1.5 self-start rounded-full border px-2 py-1 text-[12px] font-medium leading-tight',
                    'text-blue-600' => $post->status === 'draft',
                    'text-yellow-700 dark:text-yellow-600' => $post->status === 'scheduled',
                    'text-green-600' => $post->status === 'published',
                    'text-red-600' => $post->status === 'failed',
                ])
                :class="{
                    'text-blue-600': status === 'draft',
                    'text-yellow-700 dark:text-yellow-600': status === 'scheduled',
                    'text-green-600': status === 'published',
                    'text-red-600': status === 'failed',
                }"
            >
                <span x-html="statusState.icon">
                    {{-- blade-formatter-disable --}}
					@if ($post->status === 'published')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"> <path d="M5 12l5 5l10 -10"></path> </svg>
					@elseif($post->status === 'failed')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 3.34a10 10 0 1 1 -15 8.66l.005 -.324a10 10 0 0 1 14.995 -8.336m-5 11.66a1 1 0 0 0 -1 1v.01a1 1 0 0 0 2 0v-.01a1 1 0 0 0 -1 -1m0 -7a1 1 0 0 0 -1 1v4a1 1 0 0 0 2 0v-4a1 1 0 0 0 -1 -1"/></svg>
					@elseif($post->status === 'draft')
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -12" /><path d="M7 8h10" /><path d="M7 12h10" /><path d="M7 16h10" /></svg>
					@else
						<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>
					@endif
					{{-- blade-formatter-enable --}}
                </span>

                <span x-text="statusState.label">
                    {{ Str::headline($post->status) }}
                </span>
            </p>
        </td>

        <td class="min-w-36">
            @php
                $date_to_show = $post->status === 'published' && $post->published_at ? $post->published_at : $post->scheduled_at ?? '';

                if ($date_to_show) {
                    $carbon_date = \Carbon\Carbon::parse($date_to_show);
                    $formatted_date = $carbon_date->translatedFormat('M j, Y');
                    $formatted_time = $carbon_date->format('H:i');
                } else {
                    $formatted_date = '';
                    $formatted_time = '';
                }
            @endphp
            <p class="m-0 text-2xs">
                <span
                    class="text-heading-foreground"
                    x-text="formattedDate.date"
                >
                    {{ $formatted_date }}
                </span>
                <span
                    class="opacity-50"
                    x-text="formattedDate.time"
                >
                    {{ $formatted_time }}
                </span>
            </p>
        </td>

		<td>
			{{ optional($post->publishing_type)->value ? Str::headline($post->publishing_type?->value) : __('Post') }}
		</td>

        <td>
            <div class="flex items-center gap-1.5 text-xs">
                <img
                    class="dark:hidden"
                    width="20"
                    height="20"
                    src="{{ $platform_image }}"
                    :src="`/vendor/social-media/icons/${platform.platform}.svg`"
                    alt="{{ $post->content }}"
                >
                <img
                    class="hidden dark:block"
                    width="20"
                    height="20"
                    src="{{ $platform_image_dark }}"
                    :src="`/vendor/social-media/icons/${platform.platform}-mono-light.svg`"
                    alt="{{ $post->content }}"
                >
                <span x-text="platform.credentials.username">
                    {{ $platform_username }}
                </span>
            </div>
        </td>

        <td class="min-w-52">
            <div class="flex items-center justify-end gap-2">
                @if ($post->status === 'draft')
                    <x-button
                        class="size-9 p-0"
                        size="none"
                        variant="ghost-shadow"
                        hover-variant="success"
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
                        class="size-9 p-0"
                        size="none"
                        variant="ghost-shadow"
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

                @if ($post->status === 'published' && $published_link)
                    <x-button
                        class="size-9 p-0"
                        size="none"
                        variant="ghost-shadow"
                        title="{{ __('View') }}"
                        href="{{ $published_link }}"
                        target="_blank"
                    >
                        <x-tabler-eye class="size-4" />
                    </x-button>
                @endif

                @if ($post->status !== 'published')
                    <x-button
                        class="size-9 p-0"
                        size="none"
                        variant="ghost-shadow"
                        title="{{ __('Edit') }}"
                        href="#"
                        @click.prevent="openEditSidedrawer({ query: 'id={{ $post->id }}', taskKey: 'fetchingPost-{{ $post->id }}' })"
                        ::class="{ 'opacity-50 pointer-events-none': currentTasks.has('fetchingPost-{{ $post->id }}') }"
                    >
                        <x-tabler-loader-2
                            class="size-4 animate-spin"
                            x-show="currentTasks.has('fetchingPost-{{ $post->id }}')"
                            x-cloak
                        />
                        <x-tabler-pencil-minus
                            class="size-4"
                            x-show="!currentTasks.has('fetchingPost-{{ $post->id }}')"
                        />
                    </x-button>

                    <x-dropdown.dropdown
                        anchor="end"
                        offsetY="10px"
                    >
                        <x-slot:trigger
                            class="size-9 p-0"
                            size="none"
                            variant="ghost-shadow"
                        >
                            <span class="sr-only">
                                @lang('More')
                            </span>
                            <x-tabler-dots class="size-4" />
                        </x-slot:trigger>

                        <x-slot:dropdown
                            class="min-w-[150px] rounded-lg bg-background p-2 shadow-lg"
                        >
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-foreground/5"
                                href="#"
                                @click.prevent="$dispatch('social-media-agent:duplicate-post', { id: {{ $post->id }} })"
                            >
                                @lang('Duplicate')
                            </a>
                            <a
                                class="block rounded border-b px-2 py-1.5 text-2xs font-medium text-heading-foreground transition-colors last:border-b-0 hover:bg-red-500 hover:text-white"
                                href="#"
                                @click.prevent="rejectPost({{ $post->id }}); toggle('collapse');"
                            >
                                @lang('Delete')
                            </a>
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5">
            <h4 class="social-media-agent-post-item group m-0 flex w-full flex-shrink-0 flex-grow-0 basis-auto text-lg">
                @lang('No posts were found.')
            </h4>
        </td>
    </tr>
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
                    scheduled_at,
                    published_at,
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
                    scheduled_at,
                    published_at,
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
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 6a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2l0 -12" /><path d="M7 8h10" /><path d="M7 12h10" /><path d="M7 16h10" /></svg>',
                            css: 'bg-blue-500/15 text-blue-700 dark:text-blue-400'
                        },
                        'scheduled': {
                            label: '{{ __('Scheduled') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 7v5l3 3"/></svg>',
                            css: 'bg-yellow-500/15 text-yellow-700 dark:text-yellow-400'
                        },
                        'published': {
                            label: '{{ __('Published') }}',
                            icon: '<svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"> <path d="M5 12l5 5l10 -10"></path> </svg>',
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
                            && ['pending', 'generating'].includes(this.video_status ?? 'none')
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

                    // Image polling methods
                    shouldPollImage() {
                        const hasNoImages = !Array.isArray(this.media_urls) || this.media_urls.length === 0;
                        return hasNoImages
                            && ['pending', 'generating', 'in_queue', 'in_progress'].includes(this.image_status ?? 'none')
                            && !!this.image_request_id
                            && !!this.imageStatusEndpoint;
                    },

                    evaluateImagePolling() {
                        if (this.shouldPollImage()) {
                            this.startImagePolling();
                        } else {
                            this.stopImagePolling();
                        }
                    },

                    startImagePolling() {
                        if (this.imagePollingTimer) {
                            return;
                        }

                        this.imagePollingAttempts = 0;
                        this.scheduleImagePolling();
                    },

                    scheduleImagePolling() {
                        this.stopImagePolling();

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
                            const res = await fetch(url, {
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });

                            const data = await res.json();

                            return data;
                        } catch (error) {
                            if (!suppressErrors) {
                                toastr.error(error.message ?? '{{ __('Failed fetching image status') }}');
                            }

                            return null;
                        }
                    },

                    get formattedDate() {
                        const locale = navigator.languages.length ? navigator.languages[0] : 'en-US';
                        const dateToShow = this.status === 'published' && this.published_at ? this.published_at : this.scheduled_at ? this.scheduled_at :
                            '';

                        if (!dateToShow) return '';

                        const date = new Date(dateToShow);
                        const formattedDate = new Intl.DateTimeFormat(locale, {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        }).format(date);
                        const formattedTime = new Intl.DateTimeFormat(locale, {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        }).format(date);

                        return {
                            date: formattedDate,
                            time: formattedTime
                        };
                    }
                }))
            });
        })
        ();
    </script>
@endPushOnce
