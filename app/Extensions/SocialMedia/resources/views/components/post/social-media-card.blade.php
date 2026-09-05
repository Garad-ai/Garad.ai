@push('css')
    <style>
        .lqd-social-media-card {
            --header-order: 0;
            --media-order: 1;
            --content-order: 2;
            --footer-order: 3;

            [data-platform-style] {
                display: none;
            }

            .lqd-social-media-card-header {
                order: var(--header-order);
            }

            .lqd-social-media-card-content {
                order: var(--content-order);
            }

            .lqd-social-media-card-media {
                order: var(--media-order);
                margin: 0;
                width: 100%;
                border-radius: 18px;
                overflow: hidden;
                background-color: hsl(var(--heading-foreground) / 8%);
            }

            .lqd-social-media-card-footer {
                order: var(--footer-order);
            }

            &[data-platform="facebook"] {
                --content-order: 1;
                --media-order: 2;

                [data-platform-style*="facebook"] {
                    display: inline-flex;
                }

                .lqd-social-media-card-content {
                    padding-top: 0;
                }

                .lqd-social-media-card-footer-bar-bottom {
                    padding-top: 28px;
                    padding-bottom: 28px;
                }
            }

            &[data-platform="instagram"] {
                --footer-order: 2;
                --content-order: 3;

                [data-platform-style*="instagram"] {
                    display: inline-flex;
                }

                .lqd-social-media-card-content {
                    padding-top: 0;
                    padding-bottom: 30px;
                }

                .lqd-social-media-card-footer-bar-bottom {
                    gap: 20px;
                }

                .lqd-social-media-card-action:last-child {
                    margin-inline-start: auto;
                }
            }

            &[data-platform="x"] {
                [data-platform-style*="x"] {
                    display: inline-flex;
                }

                .lqd-social-media-card-footer {
                    border-top: 1px solid hsl(var(--border));
                }

                .lqd-social-media-card-footer-bar-bottom {
                    padding-top: 27px;
                    padding-bottom: 27px;
                }
            }

            &[data-platform="linkedin"] {
                [data-platform-style*="linkedin"] {
                    display: inline-flex;
                }

                .lqd-social-media-card-footer {
                    border-top: 1px solid hsl(var(--border));
                }

                .lqd-social-media-card-action {
                    gap: 7px;
                }

                .lqd-social-media-card-footer-bar-bottom {
                    padding-top: 27px;
                    padding-bottom: 27px;
                }
            }

            &[data-platform="tiktok"] {
                aspect-ratio: 1 / 1.5;

                [data-platform-style*="tiktok"] {
                    display: inline-flex;
                }

                .lqd-social-media-card-header {
                    position: relative;
                    z-index: 2;
                }

                .lqd-social-media-card-media {
                    display: block;
                    width: 100%;
                    height: 100%;
                    position: absolute;
                    top: 0;
                    left: 0;
                    background-color: hsl(var(--heading-foreground) / 80%);
                    border-radius: 18px;
                }

                .lqd-social-media-card-media img,
                .lqd-social-media-card-video-container,
                .lqd-social-media-card-media video {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    object-position: center;
                }

                .lqd-social-media-card-content {
                    position: absolute;
                    bottom: 0;
                    inset-inline-start: 0;
                    inset-inline-end: 90px;
                    padding-bottom: 30px;
                    z-index: 2;
                    color: #fff;
                }

                .lqd-social-media-card-content .prose {
                    color: inherit;
                }

                .lqd-social-media-card-footer {
                    position: absolute;
                    bottom: 0;
                    inset-inline-end: 0;
                    z-index: 2;
                    color: #fff;
                }

                .lqd-social-media-card-footer-bar-bottom {
                    flex-direction: column;
                    gap: 27px;
                    padding: 30px 20px;
                }

                .lqd-social-media-card-action {
                    flex-direction: column;
                    align-content: center;
                    gap: 5px;
                    font-size: 13px;
                    text-align: center;
                }
            }

            &[data-story="true"] {
                position: relative;
                aspect-ratio: 9 / 16;
                max-height: 70vh;
                overflow: hidden;
                margin: 0 auto;
                padding: 0;
                background: black;

                .lqd-social-media-card-header {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 3;
                    background: linear-gradient(180deg, rgba(0, 0, 0, 0.5) 0%, transparent 100%);
                    padding: 16px;
                }

                .lqd-social-media-card-header .lqd-social-media-card-username,
                .lqd-social-media-card-header .lqd-social-media-card-date {
                    color: #fff;
                }

                .lqd-social-media-card-media {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    border-radius: 18px;
                    z-index: 1;
                }

                .lqd-social-media-card-media img,
                .lqd-social-media-card-video-container,
                .lqd-social-media-card-media video {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    object-position: center;
                }

                .lqd-social-media-card-content {
                    display: none;
                }

                .lqd-social-media-card-footer {
                    display: none;
                }

                .lqd-social-media-card-story-label {
                    display: flex;
                }
            }

            &[data-platform="youtube"] {
                display: flex;
                flex-direction: column;
                gap: 0;

                .lqd-social-media-card-header {
                    display: none;
                }

                .lqd-social-media-card-media {
                    aspect-ratio: 16 / 9;
                    background-color: #000;
                    display: block;
                    flex: 1 1 auto;
                }

                .lqd-social-media-card-media img,
                .lqd-social-media-card-video-container,
                .lqd-social-media-card-media video {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    background-color: #000;
                }

                .lqd-social-media-card-content {
                    padding: 24px 32px 28px;
                    border-top: 1px solid hsl(var(--border));
                }
            }

            &[data-platform="youtube-shorts"] {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                gap: 0;
                aspect-ratio: 9 / 16;
                padding: 0;
                overflow: hidden;
                max-height: 70vh;
                margin: 0 auto;
                min-width: min(100%, 360px);

                .lqd-social-media-card-header {
                    display: none;
                }

                .lqd-social-media-card-media {
                    flex: 1 1 auto;
                    width: 100%;
                    aspect-ratio: 9 / 16;
                    border-radius: 18px;
                    background-color: #000;
                    margin-inline: 0;
                    display: block;
                    position: relative;
                }

                .lqd-social-media-card-media img,
                .lqd-social-media-card-video-container,
                .lqd-social-media-card-media video {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .lqd-social-media-card-content {
                    display: none;
                }

                .lqd-social-media-card-shorts-overlay {
                    position: absolute;
                    inset: 0;
                    padding: 18px;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    pointer-events: none;
                    border-radius: inherit;
                }

                .lqd-social-media-card-shorts-pill {
                    align-self: flex-start;
                    background: rgba(0, 0, 0, 0.55);
                    color: #fff;
                    font-size: 0.75rem;
                    font-weight: 600;
                    padding: 0.25rem 0.85rem;
                    border-radius: 999px;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    margin: 0;
                    backdrop-filter: blur(12px);
                }

                .lqd-social-media-card-shorts-overlay-bottom {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.75rem;
                    background: linear-gradient(to top, black, transparent);
                    padding: 1.25rem;
                    margin: 0 -18px -18px;
                    border-radius: inherit;
                }

                .lqd-social-media-card-shorts-profile {
                    display: flex;
                    align-items: center;
                    gap: 0.6rem;
                    color: #fff;
                }

                .lqd-social-media-card-shorts-profile img {
                    width: 42px;
                    height: 42px;
                    border-radius: 999px;
                    object-fit: cover;
                    border: 2px solid rgba(255, 255, 255, 0.6);
                }

                .lqd-social-media-card-shorts-profile strong {
                    display: block;
                    font-size: 0.95rem;
                }

                .lqd-social-media-card-shorts-profile span {
                    font-size: 0.75rem;
                    opacity: 0.85;
                }

                .lqd-social-media-card-shorts-subscribe {
                    background-color: #ff0033;
                    color: #fff;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    padding: 0.4rem 1.4rem;
                    border-radius: 999px;
                    box-shadow: 0 8px 18px rgba(255, 0, 51, 0.4);
                    pointer-events: auto;
                }

                .lqd-social-media-card-shorts-meta {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                }

                .lqd-social-media-card-shorts-meta p {
                    font-size: 0.95rem;
                    color: hsl(var(--heading-foreground));
                    line-height: 1.45;
                }

                .lqd-social-media-card-shorts-stats {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: hsl(var(--heading-foreground) / 65%);
                }
            }

            &[data-platform="youtube"],
            &[data-platform="youtube-shorts"] {
                .lqd-social-media-card-footer {
                    display: none;
                }

                .lqd-social-media-card-youtube-meta {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }

                .lqd-social-media-card-youtube-title {
                    font-size: 1rem;
                    font-weight: 600;
                    color: hsl(var(--heading-foreground));
                    line-height: 1.4;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }

                .lqd-social-media-card-youtube-channel {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                }

                .lqd-social-media-card-youtube-channel-info {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }

                .lqd-social-media-card-youtube-channel-info img {
                    width: 40px;
                    height: 40px;
                    border-radius: 999px;
                    object-fit: cover;
                }

                .lqd-social-media-card-youtube-channel-meta {
                    display: flex;
                    flex-direction: column;
                    gap: 0.15rem;
                }

                .lqd-social-media-card-youtube-channel-meta span {
                    font-size: 0.75rem;
                    color: hsl(var(--heading-foreground) / 65%);
                }

                .lqd-social-media-card-youtube-subscribe {
                    background-color: #ff0033;
                    color: #fff;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    padding: 0.45rem 1.5rem;
                    border-radius: 999px;
                    box-shadow: 0 10px 24px rgba(255, 0, 51, 0.25);
                }

                .lqd-social-media-card-youtube-stats {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-size: 0.85rem;
                    color: hsl(var(--heading-foreground) / 65%);
                }

                .lqd-social-media-card-generic-content {
                    display: none;
                }
            }
        }
    </style>
@endpush

@php
    $activePlatform = $current_platform ?? 'facebook';
    $isYoutubePlatform = $activePlatform === 'youtube';
    $isYoutubeShortsPlatform = $activePlatform === 'youtube-shorts';
    $isYoutubePreview = $isYoutubePlatform || $isYoutubeShortsPlatform;
    $youtubePreviewTitleText = trim(strip_tags((string) ($content ?? '')));
    if ($youtubePreviewTitleText === '') {
        $youtubePreviewTitleText = __('Your video title will appear here');
    }
    $youtubePreviewTitleAttr = addslashes($youtubePreviewTitleText);
@endphp

<article
    class="lqd-social-media-card flex flex-col rounded-[18px] bg-background shadow-lg shadow-black/5"
    data-platform="{{ $current_platform ?? 'facebook' }}"
    :data-platform="currentPlatform"
    :data-story="isStory ? 'true' : 'false'"
>
    <header class="lqd-social-media-card-header flex items-center justify-between gap-3 px-5 py-5 xl:px-8">
        <div class="lqd-social-media-card-user-info flex items-center gap-4">
            <figure
                class="lqd-social-media-card-avatar shrink-0"
                data-platform-style="facebook,x,instagram,linkedin"
            >
                <img
                    class="rounded-full"
                    :src="platformPicture"
                    alt=""
                    width="42"
                    height="42"
                >
            </figure>
            <div class="lqd-social-media-card-user-meta">
                <h4
                    class="lqd-social-media-card-username mb-0 text-base font-medium"
                    data-platform-style="facebook,x,instagram,linkedin"
                    x-text="platformUsername"
                >
                </h4>
                <p
                    class="lqd-social-media-card-date mb-0 text-xs font-medium text-heading-foreground/50"
                    data-platform-style="facebook,x"
                >
                    {{ now()->format('F j, Y') }}
                </p>
            </div>
        </div>

        @foreach ($platforms as $platform)
            <figure
                class="inline-flex w-8"
                data-platform-style="{{ $platform->value }}"
            >
                @php
                    $image = 'vendor/social-media/icons/' . $platform->value . '.svg';
                    $image_dark_version = 'vendor/social-media/icons/' . $platform->value . '-mono-light.svg';
                    $darkImageExists = file_exists(public_path($image_dark_version));
                @endphp
                <img
                    @class(['w-full h-auto', 'dark:hidden' => $image_dark_version])
                    src="{{ asset($image) }}"
                    alt="{{ $platform->label() }}"
                />
                @if ($image_dark_version)
                    <img
                        class="hidden h-auto w-full dark:block"
                        src="{{ asset($image_dark_version) }}"
                        alt="{{ $platform->label() }}"
                    />
                @endif
            </figure>
        @endforeach
    </header>

    <figure
        @class([
            'lqd-social-media-card-media',
            'hidden' => !filled($image) && !filled($video),
        ])
        :class="{ 'hidden': !images?.length && !image && !video }"
    >
        {{-- Single image fallback --}}
        <img
            @class([
                'lqd-social-media-card-image w-full h-auto max-h-full',
                'hidden' => !filled($postImage ?? ''),
            ])
            src="{{ $postImage ?? '' }}"
            alt="{{ __('Social Media') }}"
            :class="{ 'hidden': images?.length > 1 || (!images?.length && !image) }"
            :src="images?.length ? images[0] : image"
            x-show="!images?.length || images?.length <= 1"
        >

        {{-- Carousel for multiple images --}}
        <div
            class="relative h-full"
            x-show="images?.length > 1"
            x-cloak
        >
            <template
                x-for="(img, idx) in images"
                :key="idx"
            >
                <img
                    class="lqd-social-media-card-image h-full w-full object-cover object-center"
                    :src="img"
                    alt=""
                    x-show="carouselIndex === idx"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                >
            </template>

            {{-- Carousel navigation arrows --}}
            <button
                class="absolute start-2 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white transition-opacity hover:bg-black/70"
                type="button"
                x-show="carouselIndex > 0"
                @click.prevent="carouselIndex--"
            >
                <x-tabler-chevron-left class="size-5" />
            </button>
            <button
                class="absolute end-2 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white transition-opacity hover:bg-black/70"
                type="button"
                x-show="carouselIndex < images.length - 1"
                @click.prevent="carouselIndex++"
            >
                <x-tabler-chevron-right class="size-5" />
            </button>

            {{-- Pagination dots --}}
            <div
                class="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-1.5"
                x-show="images?.length > 1"
            >
                <template
                    x-for="(_, idx) in images"
                    :key="'dot-' + idx"
                >
                    <button
                        class="size-2 rounded-full transition-all"
                        :class="carouselIndex === idx ? 'bg-white scale-110' : 'bg-white/50'"
                        type="button"
                        @click.prevent="carouselIndex = idx"
                    ></button>
                </template>
            </div>
        </div>

        {{-- Video player with custom controls --}}
        <div
            @class([
                'lqd-social-media-card-video-container relative max-h-full group',
                'hidden' => !filled($video),
            ])
            :class="{ hidden: !video }"
        >
            <video
                class="lqd-social-media-card-video max-h-full"
                src="{{ $video }}"
                :src="video"
                x-ref="previewVideo"
                preload="auto"
                :key="video"
                @pause="previewVideoPaused = true"
                @play="previewVideoPaused = false"
                x-init="$watch('video', videoSource => videoSource && $el.load())"
            ></video>
            <button
                class="lqd-social-media-card-video-play-button absolute left-1/2 top-1/2 z-2 inline-grid size-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-white/10 bg-white/10 text-white opacity-0 shadow-xl shadow-black/5 backdrop-blur-md transition group-hover:opacity-100"
                type="button"
                @click.prevent="previewVideoPaused ? $refs.previewVideo?.play() : $refs.previewVideo?.pause()"
            >
                <x-tabler-player-play
                    class="size-8 fill-current"
                    x-show="previewVideoPaused"
                />
                <x-tabler-player-pause
                    class="size-8 fill-current"
                    x-show="!previewVideoPaused"
                />
            </button>
        </div>
        <div
            class="lqd-social-media-card-shorts-overlay"
            @class(['hidden' => !$isYoutubeShortsPlatform])
            :class="{ hidden: currentPlatform !== 'youtube-shorts' }"
            x-cloak
        >
            <span class="lqd-social-media-card-shorts-pill">
                {{ __('Shorts') }}
            </span>
            <div class="lqd-social-media-card-shorts-overlay-bottom">
                <div class="lqd-social-media-card-shorts-profile overflow-hidden">
                    <img
                        alt="{{ __('Channel avatar') }}"
                        :src="platformPicture"
                        width="42"
                        height="42"
                    >
                    <div class="overflow-hidden">
                        <strong
                            class="w-full truncate"
                            x-text="platformUsername"
                        ></strong>
                        <span>{{ __('Swipe for more') }}</span>
                    </div>
                </div>
                <button
                    class="lqd-social-media-card-shorts-subscribe"
                    type="button"
                >
                    {{ __('Subscribe') }}
                </button>
            </div>
        </div>
    </figure>

    <div
        @class([
            'lqd-social-media-card-content px-5 xl:px-8 py-5 text-sm/5',
            'hidden' =>
                $isYoutubeShortsPlatform || (!$isYoutubePreview && !filled($content)),
        ])
        :class="{
            'hidden': currentPlatform === 'youtube-shorts' ||
                (!['youtube'].includes(currentPlatform) &&
                    (!content || !content?.trim()?.length))
        }"
    >
        <div
            class="lqd-social-media-card-youtube-meta flex flex-col gap-4"
            @class(['hidden' => !$isYoutubePlatform])
            :class="{ hidden: currentPlatform !== 'youtube' }"
            x-cloak
        >
            <p
                class="lqd-social-media-card-youtube-title"
                x-text="content?.trim()?.length ? content : '{{ $youtubePreviewTitleAttr }}'"
            >
                {{ $youtubePreviewTitleText }}
            </p>

            <div class="lqd-social-media-card-youtube-channel">
                <div class="lqd-social-media-card-youtube-channel-info">
                    <img
                        alt="{{ __('Channel avatar') }}"
                        :src="platformPicture"
                        width="40"
                        height="40"
                    >
                    <div class="lqd-social-media-card-youtube-channel-meta">
                        <span
                            class="text-sm font-semibold text-heading-foreground"
                            x-text="platformUsername"
                        ></span>
                        <span>{{ __('123K subscribers') }}</span>
                    </div>
                </div>

                <button
                    class="lqd-social-media-card-youtube-subscribe"
                    type="button"
                >
                    {{ __('Subscribe') }}
                </button>
            </div>

            <div class="lqd-social-media-card-youtube-stats">
                <span>{{ __('1.2K views') }}</span>
                <span>•</span>
                <span>{{ now()->format('M j, Y') }}</span>
            </div>
        </div>

        <div
            class="lqd-social-media-card-shorts-meta"
            @class(['hidden' => !$isYoutubeShortsPlatform])
            :class="{ hidden: currentPlatform !== 'youtube-shorts' }"
            x-cloak
        >
            <p x-text="content?.trim()?.length ? content : '{{ $youtubePreviewTitleAttr }}'">
                {{ $youtubePreviewTitleText }}
            </p>
            <div class="lqd-social-media-card-shorts-stats">
                <span>{{ __('4.8K likes') }}</span>
                <span>•</span>
                <span>{{ __('312 comments') }}</span>
                <span>•</span>
                <span>{{ __('Now playing') }}</span>
            </div>
        </div>

        <div
            class="lqd-social-media-card-generic-content prose m-0"
            x-html="lqdFormatString(content)"
            @class(['hidden' => $isYoutubePreview])
            :class="{ hidden: ['youtube', 'youtube-shorts'].includes(currentPlatform) }"
        >
            {{ $content }}
        </div>

        <a
            class="mt-4 block text-blue-700 underline"
            @if (!filled($link)) x-cloak @endif
            href="{{ $link }}"
            @class(['hidden' => $isYoutubePreview])
            :class="{ hidden: ['youtube', 'youtube-shorts'].includes(currentPlatform) || !link }"
            x-text="link"
            x-transition
        >
            {{ $link }}
        </a>
    </div>

    <footer class="lqd-social-media-card-footer font-medium text-heading-foreground/60">
        <div
            class="lqd-social-media-card-footer-bar-top flex w-full items-center justify-between gap-3 border-b px-5 py-4 text-sm xl:px-8"
            data-platform-style="facebook"
        >
            <div class="flex items-center gap-1.5">
                <span class="inline-grid size-[26px] place-items-center rounded-full bg-gradient-to-b from-[#48ADF8] to-[#1E5FED] text-white">
                    <svg
                        width="12"
                        height="12"
                        viewBox="0 0 12 12"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="currentColor"
                    >
                        <path
                            d="M2.4375 5.25C2.57812 5.25 2.71875 5.32031 2.83594 5.41406C2.92969 5.53125 3 5.67188 3 5.8125L3 11.4375C3 11.6016 2.92969 11.7422 2.83594 11.8359C2.71875 11.9531 2.57812 12 2.4375 12H0.5625C0.398437 12 0.257812 11.9531 0.164062 11.8359C0.046875 11.7422 0 11.6016 0 11.4375L0 5.8125C0 5.67188 0.046875 5.53125 0.164062 5.41406C0.257812 5.32031 0.398437 5.25 0.5625 5.25H2.4375ZM1.5 11.0625C1.64062 11.0625 1.78125 11.0156 1.89844 10.8984C1.99219 10.8047 2.0625 10.6641 2.0625 10.5C2.0625 10.3594 1.99219 10.2188 1.89844 10.1016C1.78125 10.0078 1.64062 9.9375 1.5 9.9375C1.33594 9.9375 1.19531 10.0078 1.10156 10.1016C0.984375 10.2188 0.9375 10.3594 0.9375 10.5C0.9375 10.6641 0.984375 10.8047 1.10156 10.8984C1.19531 11.0156 1.33594 11.0625 1.5 11.0625ZM9 1.89844C9 1.14844 8.78906 0.632813 8.36719 0.304688C8.08594 0.117188 7.73438 0 7.3125 0C7.10156 0 6.91406 0.1875 6.77344 0.515625C6.67969 0.703125 6.60938 0.984375 6.51562 1.33594V1.35938C6.42188 1.71094 6.35156 1.96875 6.30469 2.10938C6.21094 2.39062 6.07031 2.60156 5.92969 2.74219C5.67188 2.97656 5.36719 3.375 4.99219 3.91406C4.52344 4.57031 4.14844 5.0625 3.84375 5.39062C3.77344 5.4375 3.75 5.50781 3.75 5.57812L3.75 10.5938C3.75 10.6875 3.77344 10.7578 3.82031 10.8047C3.86719 10.8516 3.9375 10.875 4.03125 10.875C4.28906 10.875 4.6875 11.0156 5.22656 11.25C5.76562 11.4844 6.1875 11.6484 6.51562 11.7422C7.03125 11.9297 7.54688 12 8.0625 12H8.13281C8.8125 12 9.32812 11.9766 9.65625 11.9062C10.1953 11.8125 10.5703 11.625 10.7812 11.3203C10.9688 11.0391 11.0391 10.6875 10.9453 10.2656C11.1328 10.0547 11.25 9.77344 11.3438 9.44531C11.4141 9.11719 11.4141 8.8125 11.3203 8.50781C11.5078 8.25 11.625 7.94531 11.6719 7.59375C11.6953 7.26562 11.6484 6.9375 11.5312 6.63281H11.5547C11.6719 6.49219 11.7656 6.32812 11.8594 6.11719C11.9531 5.92969 12 5.71875 12 5.48438C12 5.13281 11.8594 4.82812 11.5781 4.54688C11.2969 4.26562 10.9688 4.125 10.5938 4.125H8.22656C8.25 3.98438 8.34375 3.72656 8.53125 3.39844C8.67188 3.11719 8.78906 2.90625 8.85938 2.74219C8.95312 2.46094 9 2.17969 9 1.89844Z"
                        />
                    </svg>
                </span>
                <span class="me-1.5 inline-grid size-[26px] place-items-center rounded-full bg-gradient-to-b from-[#F95E73] to-[#E3223C] text-white">
                    <svg
                        width="12"
                        height="10"
                        viewBox="0 0 12 10"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="currentColor"
                    >
                        <path
                            d="M10.5288 0.764162C10.9417 1.13578 11.2514 1.56934 11.4166 2.08548C11.5817 2.62226 11.6024 3.1384 11.4785 3.67518C11.3546 4.21197 11.1069 4.66617 10.7353 5.03779L6.75066 9.1669C6.60614 9.31142 6.44098 9.37335 6.27582 9.37335C6.09001 9.37335 5.92484 9.31142 5.80097 9.1669L1.81638 5.05843C1.42411 4.66617 1.17637 4.21197 1.05249 3.67518C0.928621 3.1384 0.969912 2.62226 1.13508 2.08548C1.30024 1.56934 1.58928 1.13578 2.02283 0.764162C2.39445 0.454479 2.80737 0.248024 3.28221 0.165442C3.73641 0.0828594 4.21126 0.12415 4.66546 0.268669C5.11967 0.433834 5.51193 0.68158 5.8629 1.03255L6.27582 1.44547L6.68873 1.03255C7.01905 0.68158 7.43197 0.433834 7.88617 0.268669C8.34037 0.12415 8.79457 0.0828594 9.26942 0.165442C9.72362 0.248024 10.1572 0.454479 10.5288 0.764162Z"
                        />
                    </svg>
                </span>
                35
            </div>

            <div class="flex items-center gap-3">
                <span>
                    11 {{ __('Comments') }}
                </span>
                <span>
                    23 {{ __('Shares') }}
                </span>
            </div>
        </div>

        <div class="lqd-social-media-card-footer-bar-bottom flex w-full items-center justify-between gap-3 px-5 py-5 xl:px-8">
            <figure
                class="lqd-social-media-card-avatar shrink-0"
                data-platform-style="tiktok"
            >
                <img
                    class="rounded-full"
                    :src="platformPicture"
                    alt=""
                    width="42"
                    height="42"
                >
            </figure>
            <div class="lqd-social-media-card-action lqd-social-media-card-action-like flex items-center gap-5">
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="instagram"
                    width="25"
                    height="22"
                    viewBox="0 0 25 22"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2.42801"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M12.425 20.6531C12.425 20.6531 1.9375 14.78 1.9375 7.64851C1.9375 6.38779 2.3743 5.166 3.17358 4.19104C3.97287 3.21607 5.08526 2.54814 6.3215 2.30089C7.55774 2.05364 8.84146 2.24235 9.95424 2.8349C11.067 3.42746 11.9401 4.38726 12.425 5.551V5.551C12.9099 4.38726 13.7831 3.42746 14.8958 2.8349C16.0086 2.24235 17.2923 2.05364 18.5286 2.30089C19.7648 2.54814 20.8772 3.21607 21.6765 4.19104C22.4758 5.166 22.9126 6.38779 22.9126 7.64851C22.9126 14.78 12.425 20.6531 12.425 20.6531Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="facebook"
                    width="17"
                    height="17"
                    viewBox="0 0 17 17"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M16.502 9.49609C16.5684 9.89453 16.6016 10.293 16.5352 10.6914C16.4688 11.123 16.3691 11.4883 16.1699 11.7871C16.2363 12.1855 16.2031 12.584 16.1035 12.9824C15.9707 13.3809 15.8047 13.7461 15.5723 14.0117C15.5723 14.9414 15.3398 15.6719 14.8086 16.2031C14.2773 16.7344 13.5137 17 12.5508 17H11.1895C10.5254 17 9.79492 16.9004 8.99805 16.7012C8.5332 16.6016 7.86914 16.4023 7.07227 16.1035L6.07617 15.7715C5.74414 15.7051 5.51172 15.6387 5.3125 15.6387V15.9375C5.3125 16.2363 5.17969 16.502 4.98047 16.7012C4.78125 16.9004 4.51563 17 4.25 17H1.0625C0.763672 17 0.498047 16.9004 0.298828 16.7012C0.0996094 16.502 0 16.2363 0 15.9375V7.4375C0 7.17188 0.0996094 6.90625 0.298828 6.70703C0.498047 6.50781 0.763672 6.375 1.0625 6.375H4.25C4.61523 6.375 4.91406 6.54102 5.11328 6.83984C5.21289 6.83984 5.3125 6.80664 5.37891 6.77344C5.57813 6.67383 5.81055 6.44141 6.14258 6.07617C6.3418 5.87695 6.64063 5.51172 7.03906 4.98047C7.50391 4.31641 7.86914 3.88477 8.10156 3.68555C8.23438 3.55273 8.33398 3.35352 8.43359 3.05469C8.5 2.88867 8.56641 2.62305 8.63281 2.22461C8.89844 0.763672 9.42969 0 10.1934 0C11.123 0 11.8203 0.265625 12.2852 0.796875C12.7168 1.32813 12.9492 2.0918 12.9492 3.08789C12.9492 3.51953 12.8828 3.95117 12.75 4.34961C12.6504 4.61523 12.5176 4.91406 12.3184 5.3125L12.252 5.41211H14.5762C15.2402 5.41211 15.8047 5.67773 16.2695 6.14258C16.7344 6.64063 17 7.20508 17 7.86914C17 8.5332 16.834 9.06445 16.502 9.49609ZM1.0625 15.9375H4.25V7.4375H1.0625V15.9375ZM15.1406 9.16406C15.4063 9.09766 15.6055 8.96484 15.7383 8.69922C15.8711 8.4668 15.9375 8.16797 15.9375 7.83594C15.9375 7.50391 15.8047 7.17188 15.5391 6.90625C15.2734 6.64063 14.9414 6.47461 14.5762 6.47461H10.8574C10.8574 6.17578 10.8906 5.87695 11.0234 5.57813C11.0898 5.41211 11.1895 5.14648 11.3555 4.81445C11.5215 4.48242 11.6543 4.2168 11.7207 4.01758C11.8203 3.71875 11.8867 3.41992 11.8867 3.08789C11.8867 2.49023 11.7871 2.02539 11.6543 1.75977C11.3887 1.29492 10.9238 1.0625 10.1934 1.0625C10.0605 1.19531 9.92773 1.39453 9.86133 1.66016C9.79492 1.82617 9.72852 2.0918 9.66211 2.42383C9.5625 2.92188 9.49609 3.28711 9.42969 3.48633C9.26367 3.88477 9.06445 4.2168 8.83203 4.44922C8.59961 4.68164 8.23438 5.11328 7.76953 5.74414C7.23828 6.44141 6.83984 6.90625 6.57422 7.17188C6.10938 7.63672 5.67773 7.83594 5.3125 7.83594V14.5762C5.57813 14.5762 5.91016 14.6426 6.30859 14.7422C6.54102 14.8086 6.93945 14.9414 7.50391 15.1406C8.23438 15.4063 8.83203 15.5723 9.23047 15.6719C9.92773 15.8711 10.5918 15.9375 11.1895 15.9375H12.5508C13.2148 15.9375 13.7461 15.7715 14.0781 15.4063C14.4434 15.0078 14.5762 14.4102 14.4434 13.5801C14.7422 13.4473 14.9414 13.1484 15.0742 12.7168C15.207 12.2852 15.1406 11.8867 14.9414 11.5215C15.1738 11.4219 15.3398 11.1895 15.4395 10.8574C15.5059 10.5254 15.5391 10.1934 15.4727 9.86133C15.4063 9.5293 15.3066 9.29688 15.1406 9.16406ZM3.45313 14.3438C3.45313 14.1445 3.35352 13.9453 3.2207 13.7793C3.05469 13.6465 2.85547 13.5469 2.65625 13.5469C2.42383 13.5469 2.22461 13.6465 2.0918 13.7793C1.92578 13.9453 1.85938 14.1445 1.85938 14.3438C1.85938 14.5762 1.92578 14.7754 2.0918 14.9082C2.22461 15.0742 2.42383 15.1406 2.65625 15.1406C2.85547 15.1406 3.05469 15.0742 3.2207 14.9082C3.35352 14.7754 3.45313 14.5762 3.45313 14.3438Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="linkedin"
                    width="24"
                    height="22"
                    viewBox="0 0 24 22"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M16.8756 9.5625H21.7506C21.9661 9.5625 22.1727 9.6481 22.3251 9.80048C22.4775 9.95285 22.5631 10.1595 22.5631 10.375V19.3125C22.5631 19.528 22.4775 19.7347 22.3251 19.887C22.1727 20.0394 21.9661 20.125 21.7506 20.125H16.8756M16.8756 9.5625V20.125M16.8756 9.5625L12.8131 1.4375C12.3863 1.4375 11.9637 1.52156 11.5694 1.68489C11.1751 1.84822 10.8168 2.08761 10.515 2.3894C10.2132 2.69119 9.97382 3.04947 9.81049 3.44378C9.64716 3.83809 9.5631 4.2607 9.5631 4.6875V7.125H3.27887C3.04845 7.125 2.82067 7.174 2.61064 7.26875C2.40061 7.3635 2.21313 7.50183 2.06064 7.67457C1.90815 7.84731 1.79414 8.0505 1.72618 8.27066C1.65821 8.49082 1.63784 8.72292 1.66642 8.95156L2.88517 18.7016C2.93429 19.0946 3.12527 19.4561 3.42219 19.7182C3.71911 19.9803 4.10155 20.125 4.49762 20.125H16.8756"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="x"
                    width="21"
                    height="19"
                    viewBox="0 0 21 19"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M15.3809 2.6391C14.1589 2.5791 12.7019 3.1491 11.4909 4.7991L10.6859 5.8891L9.8799 4.7991C8.6679 3.1491 7.2099 2.5791 5.9879 2.6391C4.7449 2.7091 3.6389 3.4191 3.0779 4.5491C2.5259 5.6691 2.4449 7.32909 3.5569 9.36909C4.6309 11.3391 6.8139 13.6391 10.6859 15.9791C14.5559 13.6391 16.7379 11.3391 17.8119 9.36909C18.9229 7.32909 18.8419 5.6691 18.2889 4.5491C17.7279 3.4191 16.6229 2.7091 15.3809 2.6391ZM19.5679 10.3291C18.2169 12.8091 15.5669 15.4491 11.1889 17.9991L10.6859 18.2991L10.1819 17.9991C5.8029 15.4491 3.1529 12.8091 1.7999 10.3291C0.439903 7.82909 0.389903 5.4691 1.2859 3.6591C2.1729 1.8691 3.9329 0.749096 5.8869 0.649096C7.5379 0.559096 9.2549 1.2091 10.6849 2.6591C12.1139 1.2091 13.8309 0.559096 15.4809 0.649096C17.4349 0.749096 19.1949 1.8691 20.0819 3.6591C20.9779 5.4691 20.9279 7.82909 19.5679 10.3291Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="tiktok"
                    width="36"
                    height="33"
                    viewBox="0 0 36 33"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M16.0611 31.818C17.0718 32.5395 18.4282 32.5395 19.4389 31.818C24.4905 28.2118 29.3281 24.063 32.6744 18.7538C34.3509 16.0939 35.0537 13.9429 35.3301 12.184C35.4416 11.5647 35.5 10.9257 35.5 10.2725C35.5 4.59918 31.097 0 25.6655 0C22.4197 0 19.5411 1.64254 17.75 4.17528C15.9589 1.64254 13.0803 0 9.8345 0C4.40304 0 0 4.59918 0 10.2725C0 10.9257 0.05836 11.5647 0.16994 12.184C0.44634 13.9429 1.14906 16.0939 2.82555 18.7538C6.1719 24.063 11.0095 28.2118 16.0611 31.818Z"
                    />
                    <path
                        d="M16.0611 27.5C17.0718 28.2216 18.4282 28.2216 19.4389 27.5C25 24.5 27 23.5 32.6744 18.7538C34.3509 16.0939 35.0537 13.9429 35.3301 12.184C35.4416 11.5647 35.5 10.9257 35.5 10.2725C35.5 4.59918 31.097 0 25.6655 0C22.4197 0 19.5411 1.64254 17.75 4.17528C15.9589 1.64254 13.0803 0 9.8345 0C4.40304 0 0 4.59918 0 10.2725C0 10.9257 0.05836 11.5647 0.16994 12.184C0.44634 13.9429 1.14906 16.0939 2.82555 18.7538C9 23.5 11 24.5 16.0611 27.5Z"
                    />
                </svg>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="facebook,linkedin"
                >
                    {{ __('Like') }}
                </span>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="tiktok"
                >
                    328.7K
                </span>
            </div>
            <div class="lqd-social-media-card-action lqd-social-media-card-action-comment flex items-center gap-5">
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="instagram"
                    width="23"
                    height="23"
                    viewBox="0 0 23 23"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2.42801"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M2.76499 16.5628C1.5158 14.4553 1.07885 11.9642 1.53617 9.5573C1.99349 7.1504 3.31362 4.99319 5.24873 3.49065C7.18385 1.98811 9.60086 1.24357 12.046 1.39681C14.4912 1.55005 16.7963 2.59052 18.5287 4.32291C20.2611 6.05529 21.3016 8.36044 21.4548 10.8056C21.608 13.2508 20.8635 15.6678 19.361 17.6029C17.8584 19.538 15.7012 20.8581 13.2943 21.3155C10.8874 21.7728 8.39637 21.3358 6.2888 20.0866V20.0866L2.80694 21.0725C2.66428 21.1142 2.51303 21.1168 2.36904 21.0799C2.22505 21.0431 2.09361 20.9682 1.98851 20.8631C1.88342 20.758 1.80852 20.6266 1.77169 20.4826C1.73486 20.3386 1.73744 20.1873 1.77916 20.0447L2.76499 16.5628Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="facebook"
                    width="17"
                    height="17"
                    viewBox="0 0 17 17"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M14.875 0C15.4395 0 15.9375 0.232422 16.3691 0.630859C16.7676 1.0625 17 1.56055 17 2.125V11.6875C17 12.2852 16.7676 12.7832 16.3691 13.1816C15.9375 13.6133 15.4395 13.8125 14.875 13.8125H10.0938L5.94336 16.9336C5.87695 17 5.77734 17 5.67773 17C5.57812 17 5.47852 16.9668 5.41211 16.9004C5.3457 16.834 5.3125 16.7344 5.3125 16.6016V13.8125H2.125C1.52734 13.8125 1.0293 13.6133 0.630859 13.1816C0.199219 12.7832 0 12.2852 0 11.6875V2.125C0 1.56055 0.199219 1.0625 0.630859 0.630859C1.0293 0.232422 1.52734 0 2.125 0H14.875ZM15.9375 11.6875V2.125C15.9375 1.85938 15.8047 1.59375 15.6055 1.39453C15.4062 1.19531 15.1406 1.0625 14.875 1.0625H2.125C1.82617 1.0625 1.56055 1.19531 1.36133 1.39453C1.16211 1.59375 1.0625 1.85938 1.0625 2.125V11.6875C1.0625 11.9863 1.16211 12.252 1.36133 12.4512C1.56055 12.6504 1.82617 12.75 2.125 12.75H6.375V15.2734L9.72852 12.75H14.875C15.1406 12.75 15.4062 12.6504 15.6055 12.4512C15.8047 12.252 15.9375 11.9863 15.9375 11.6875Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="linkedin"
                    width="22"
                    height="22"
                    viewBox="0 0 22 22"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M7.74999 9.37502H14.25M7.74999 12.625H14.25M2.61383 15.9764C1.40246 13.9343 0.978224 11.5202 1.42079 9.18751C1.86336 6.85477 3.14229 4.76383 5.01741 3.30732C6.89254 1.85081 9.23488 1.12893 11.6046 1.27722C13.9743 1.42551 16.2084 2.43377 17.8873 4.11269C19.5662 5.7916 20.5745 8.02566 20.7228 10.3954C20.8711 12.7651 20.1492 15.1074 18.6927 16.9826C17.2362 18.8577 15.1453 20.1366 12.8125 20.5792C10.4798 21.0218 8.06575 20.5976 6.02366 19.3862L2.65622 20.3482C2.5169 20.388 2.36947 20.3899 2.2292 20.3535C2.08893 20.3172 1.96094 20.244 1.85848 20.1415C1.75602 20.0391 1.68283 19.9111 1.64648 19.7708C1.61014 19.6305 1.61196 19.4831 1.65177 19.3438L2.61383 15.9764Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="x"
                    width="21"
                    height="21"
                    viewBox="0 0 21 21"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M0 8.62C0 4.2 3.584 0.620003 8.005 0.620003H12.371C16.861 0.620003 20.5 4.26 20.5 8.75C20.5 11.71 18.893 14.43 16.304 15.86L8.25 20.32V16.63H8.183C3.693 16.73 0 13.12 0 8.62ZM8.005 2.62C4.688 2.62 2 5.31 2 8.62C2 11.99 4.77 14.7 8.138 14.63L8.489 14.62H10.25V16.92L15.337 14.11C17.288 13.03 18.5 10.98 18.5 8.75C18.5 5.36 15.756 2.62 12.371 2.62H8.005Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="tiktok"
                    width="35"
                    height="34"
                    viewBox="0 0 35 34"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                >
                    <path
                        d="M17.8242 29.4976C17.7164 29.4992 17.6083 29.5 17.5 29.5C7.83502 29.5 0 23.0081 0 15C0 6.99187 7.83502 0.5 17.5 0.5C27.165 0.5 35 6.99187 35 15C35 17.0253 34.4989 18.9536 33.5938 20.7044C32.3794 23.1797 30.5051 25.3888 28.472 27.2945C25.3743 30.1981 21.7901 32.39 19.2378 33.6253C18.5627 33.9521 17.8242 33.4404 17.8242 32.7278V29.4976ZM11.5 15.75C11.5 17.1307 10.3807 18.25 9 18.25C7.61929 18.25 6.5 17.1307 6.5 15.75C6.5 14.3693 7.61929 13.25 9 13.25C10.3807 13.25 11.5 14.3693 11.5 15.75ZM17.5 18.25C18.8807 18.25 20 17.1307 20 15.75C20 14.3693 18.8807 13.25 17.5 13.25C16.1193 13.25 15 14.3693 15 15.75C15 17.1307 16.1193 18.25 17.5 18.25ZM28.5 15.75C28.5 17.1307 27.3807 18.25 26 18.25C24.6193 18.25 23.5 17.1307 23.5 15.75C23.5 14.3693 24.6193 13.25 26 13.25C27.3807 13.25 28.5 14.3693 28.5 15.75Z"
                    />
                    <path
                        d="M17.5 29.5C27.165 29.5 35 23.0081 35 15C35 6.99187 27.165 0.5 17.5 0.5C7.83502 0.5 0 6.99187 0 15C0 23.0081 7.83502 29.5 17.5 29.5ZM9 18.25C10.3807 18.25 11.5 17.1307 11.5 15.75C11.5 14.3693 10.3807 13.25 9 13.25C7.61929 13.25 6.5 14.3693 6.5 15.75C6.5 17.1307 7.61929 18.25 9 18.25ZM20 15.75C20 17.1307 18.8807 18.25 17.5 18.25C16.1193 18.25 15 17.1307 15 15.75C15 14.3693 16.1193 13.25 17.5 13.25C18.8807 13.25 20 14.3693 20 15.75ZM26 18.25C27.3807 18.25 28.5 17.1307 28.5 15.75C28.5 14.3693 27.3807 13.25 26 13.25C24.6193 13.25 23.5 14.3693 23.5 15.75C23.5 17.1307 24.6193 18.25 26 18.25Z"
                    />
                </svg>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="facebook,linkedin"
                >
                    {{ __('Comment') }}
                </span>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="tiktok"
                >
                    578
                </span>
            </div>
            <div class="lqd-social-media-card-action lqd-social-media-card-action-share flex items-center gap-5">
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="instagram"
                    width="25"
                    height="25"
                    viewBox="0 0 25 25"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2.42801"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M11.6328 13.2173L16.3732 8.47693M22.061 1.765L2.51227 7.27096C2.34586 7.31646 2.19752 7.41215 2.08746 7.545C1.9774 7.67784 1.91096 7.84139 1.8972 8.01336C1.88345 8.18532 1.92304 8.35735 2.01058 8.50601C2.09812 8.65466 2.22936 8.77272 2.38642 8.84409L11.3638 13.0915C11.5396 13.173 11.6808 13.3142 11.7623 13.4901L16.0097 22.4674C16.0811 22.6245 16.1992 22.7557 16.3478 22.8433C16.4965 22.9308 16.6685 22.9704 16.8405 22.9566C17.0124 22.9429 17.176 22.8764 17.3088 22.7664C17.4417 22.6563 17.5374 22.508 17.5829 22.3416L23.0888 2.79278C23.1305 2.65013 23.1331 2.49888 23.0963 2.35488C23.0595 2.21089 22.9846 2.07945 22.8795 1.97436C22.7744 1.86926 22.6429 1.79437 22.4989 1.75753C22.355 1.7207 22.2037 1.72328 22.061 1.765Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="facebook"
                    width="21"
                    height="17"
                    viewBox="0 0 21 17"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M19.6996 6.50862C19.9321 6.77428 20.0649 7.10636 20.0649 7.43843C20.0649 7.80371 19.9321 8.10257 19.6996 8.36823L13.8551 14.4784C13.5895 14.744 13.2574 14.8769 12.8921 14.8769C12.5268 14.8769 12.228 14.7772 11.9623 14.5116C11.6967 14.2459 11.5638 13.9471 11.5638 13.5486V10.6263C9.6378 10.6595 8.27631 10.7592 7.44612 10.9584C6.38349 11.2241 5.68614 11.7222 5.38727 12.4195C5.0552 13.1501 5.12162 14.2127 5.55331 15.6406C5.61972 15.9727 5.58652 16.2384 5.42048 16.504C5.25444 16.7697 5.02199 16.9025 4.72313 16.9689C4.42426 17.0353 4.1586 17.0021 3.89295 16.8029C3.09597 16.2384 2.46503 15.5742 2.00013 14.7772C1.26957 13.6482 0.9375 12.3863 0.9375 10.9916C0.9375 9.19841 1.43561 7.80371 2.46503 6.74108C3.32842 5.84448 4.65671 5.18033 6.38349 4.78185C7.71178 4.44977 9.43856 4.28374 11.5638 4.25053V1.32829C11.5638 0.963011 11.6967 0.664145 11.9623 0.398487C12.228 0.132829 12.5268 0 12.8921 0C13.2574 0 13.5895 0.132829 13.8551 0.398487L19.6996 6.50862ZM18.9358 7.63767C18.969 7.57126 19.0023 7.50484 19.0023 7.43843C19.0023 7.37201 18.969 7.3056 18.9358 7.23918L13.0914 1.12905C12.9917 1.06263 12.8921 1.06263 12.7925 1.09584C12.6597 1.16225 12.6265 1.22867 12.6265 1.32829V5.31316C10.4348 5.31316 8.74121 5.41279 7.54575 5.61203C5.81897 5.87769 4.49068 6.409 3.59408 7.17277C2.53145 8.06937 2.00013 9.33124 2.00013 10.9916C2.00013 12.1207 2.26579 13.1501 2.83031 14.0467C3.2288 14.7772 3.79332 15.4082 4.52388 15.9395C3.99257 14.0135 3.99257 12.5856 4.5903 11.5893C5.12162 10.7259 6.11783 10.1614 7.61216 9.86256C8.67479 9.66332 10.3352 9.56369 12.6265 9.56369V13.5486C12.6265 13.6814 12.6597 13.7478 12.7925 13.781C12.8921 13.8474 12.9917 13.8142 13.0914 13.7478L18.9358 7.63767Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="linkedin"
                    width="22"
                    height="18"
                    viewBox="0 0 22 18"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M15.875 11.4375L20.75 6.5625M20.75 6.5625L15.875 1.6875M20.75 6.5625H11C8.41414 6.5625 5.93419 7.58973 4.10571 9.41821C2.27723 11.2467 1.25 13.7266 1.25 16.3125"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="x"
                    width="25"
                    height="17"
                    viewBox="0 0 25 17"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M4.59216 0.349998L9.02416 4.49L7.66016 5.95L5.59216 4.02V12.47C5.59216 13.57 6.48816 14.47 7.59216 14.47H13.0922V16.47H7.59216C5.38316 16.47 3.59216 14.68 3.59216 12.47V4.02L1.52416 5.95L0.160156 4.49L4.59216 0.349998ZM16.5922 2.47H11.0922V0.469998H16.5922C18.8012 0.469998 20.5922 2.26 20.5922 4.47V12.92L22.6602 10.99L24.0242 12.45L19.5922 16.59L15.1602 12.45L16.5242 10.99L18.5922 12.92V4.47C18.5922 3.37 17.6962 2.47 16.5922 2.47Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="tiktok"
                    width="35"
                    height="28"
                    viewBox="0 0 35 28"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M33.9988 15.0845C34.4285 14.6965 34.4399 14.0257 34.0236 13.6233L20.7382 0.782948C20.1035 0.169495 19.0432 0.619278 19.0432 1.50199V6.8621C14.0772 6.78534 10.3463 8.08418 7.57374 10.0746C4.75779 12.0962 2.9636 14.807 1.858 17.4345C0.753415 20.0595 0.327736 22.6203 0.25995 24.3761C0.226522 25.242 0.278158 25.9566 0.402234 26.3933C0.433943 26.5048 0.476784 26.622 0.538368 26.7255C0.592582 26.8167 0.707269 26.9754 0.915654 27.0417C1.16639 27.1214 1.37921 27.0191 1.50256 26.9132C1.61389 26.8176 1.6887 26.6943 1.74047 26.5908L1.74705 26.5777L1.75234 26.5653L1.75284 26.5641L1.75411 26.5613L1.76432 26.5391C1.77418 26.518 1.79023 26.4845 1.81294 26.44C1.85837 26.3509 1.93042 26.2173 2.03287 26.049C2.23783 25.7123 2.56409 25.237 3.04199 24.6994C3.99659 23.6255 5.55921 22.2993 7.97901 21.3314C9.3564 20.7805 11.6523 20.5297 13.9033 20.4891C16.0514 20.4504 18.0549 20.6041 19.0432 20.822V26.3392C19.0432 27.2058 20.0703 27.6622 20.7134 27.0814L33.9988 15.0845Z"
                    />
                    <path
                        d="M29.906 13.6574C30.8657 12.5418 29.906 10.9506 29.906 10.9506L28.25 8.04687L20.7382 0.782948C20.1035 0.169495 19.0432 0.619278 19.0432 1.50199V6.8621C14.0772 6.78534 10.3463 8.08418 7.57374 10.0746C4.75779 12.0962 2.9636 14.807 1.858 17.4345C0.753415 20.0595 0.327736 22.6203 0.25995 24.3761C0.226522 25.242 0.278158 25.9566 0.402234 26.3933C0.433943 26.5048 0.476784 26.622 0.538368 26.7255C0.592582 26.8167 0.707269 26.9754 0.915654 27.0417C1.16639 27.1214 1.37921 27.0191 1.50256 26.9132C1.61389 26.8176 1.6887 26.6943 1.74047 26.5908L1.74705 26.5777L1.75234 26.5653L1.75284 26.5641L1.75411 26.5613L1.76432 26.5391C1.77418 26.518 1.79023 26.4845 1.81294 26.44C1.85837 26.3509 1.93042 26.2173 2.03287 26.049C2.23783 25.7123 2.56409 25.237 3.04199 24.6994C3.99659 23.6255 9.29321 19.8672 12.2932 18.3672C14.7932 16.8672 19.0432 15.8672 19.0432 15.8672V21.8672C19.0432 22.7337 20.0703 23.1901 20.7134 22.6094L29.906 13.6574Z"
                    />
                </svg>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="facebook,linkedin,tiktok"
                >
                    {{ __('Share') }}
                </span>
            </div>
            <div
                class="lqd-social-media-card-action lqd-social-media-card-action-bookmark flex items-center gap-5"
                data-platform-style="instagram,linkedin,x"
            >
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="instagram"
                    width="17"
                    height="24"
                    viewBox="0 0 17 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2.42801"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M15.7873 22.2421L8.57531 17.7346L1.36328 22.2421V2.409C1.36328 2.1699 1.45826 1.9406 1.62733 1.77154C1.79639 1.60247 2.02569 1.50749 2.26478 1.50749H14.8858C15.1249 1.50749 15.3542 1.60247 15.5233 1.77154C15.6924 1.9406 15.7873 2.1699 15.7873 2.409V22.2421Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="linkedin"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M11.2595 12.7405L15.8557 8.14428M21.3536 1.64385L2.42968 6.98137C2.27047 7.02627 2.12877 7.1187 2.0235 7.24629C1.91823 7.37389 1.85442 7.53057 1.84059 7.69541C1.82675 7.86024 1.86356 8.02537 1.94608 8.16873C2.02861 8.31208 2.15293 8.42683 2.30242 8.49764L10.9973 12.6163C11.1669 12.6966 11.3034 12.8331 11.3837 13.0027L15.5023 21.6976C15.5732 21.8471 15.6879 21.9714 15.8313 22.0539C15.9746 22.1364 16.1398 22.1732 16.3046 22.1594C16.4694 22.1456 16.6261 22.0818 16.7537 21.9765C16.8813 21.8712 16.9737 21.7295 17.0186 21.5703L22.3561 2.6464C22.3953 2.5074 22.3968 2.36045 22.3603 2.2207C22.3239 2.08095 22.2508 1.95345 22.1487 1.85132C22.0465 1.74919 21.919 1.67613 21.7793 1.63967C21.6395 1.6032 21.4926 1.60464 21.3536 1.64385Z"
                    />
                </svg>
                <svg
                    class="lqd-social-media-card-action-icon"
                    data-platform-style="x"
                    width="17"
                    height="21"
                    viewBox="0 0 17 21"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                >
                    <path
                        d="M0.339844 2.5C0.339844 1.12 1.45884 0 2.83984 0H13.8398C15.2208 0 16.3398 1.12 16.3398 2.5V20.94L8.33984 15.23L0.339844 20.94V2.5ZM2.83984 2C2.56384 2 2.33984 2.22 2.33984 2.5V17.06L8.33984 12.77L14.3398 17.06V2.5C14.3398 2.22 14.1158 2 13.8398 2H2.83984Z"
                    />
                </svg>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="facebook"
                >
                    {{ __('Bookmark') }}
                </span>
                <span
                    class="lqd-social-media-card-action-text"
                    data-platform-style="linkedin"
                >
                    {{ __('Send') }}
                </span>
            </div>
        </div>
    </footer>

    <div
        class="lqd-social-media-card-story-label absolute bottom-8 left-1/2 z-10 hidden -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full border border-white/10 bg-white/10 px-6 py-3 text-white shadow-xl shadow-black/10 backdrop-blur-md">
        <svg
            class="shrink-0"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <circle
                cx="12"
                cy="12"
                r="10"
            ></circle>
            <circle
                cx="12"
                cy="12"
                r="3"
            ></circle>
        </svg>
        {{ __('Story Preview') }}
    </div>
</article>
