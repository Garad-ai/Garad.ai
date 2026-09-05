@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
    use App\Helpers\Classes\Helper;

    $meta_titles = [
        'company_id' => __('Company'),
        'product_id' => __('Product'),
        'campaign_id' => __('Campaign'),
        'tone' => __('Tone'),
        'link' => __('URL'),
    ];

    $post_metrics = [];

    if ($post->social_media_platform === PlatformEnum::instagram) {
        $post_metrics = [
            'like_count' => [
                'label' => __('Like Count'),
                'value' => data_get($post->post_metrics, 'like_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],

            'comment_count' => [
                'label' => __('Comment Count'),
                'value' => data_get($post->post_metrics, 'comment_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
        ];
    }

    if ($post->social_media_platform == PlatformEnum::facebook) {
        $post_metrics = [
            'like_count' => [
                'label' => __('Like Count'),
                'value' => data_get($post->post_metrics, 'like_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],

            'comment_count' => [
                'label' => __('Comment Count'),
                'value' => data_get($post->post_metrics, 'comment_count', Helper::generateNumberForDemo()) ?: '---',
            ],
            'share_count' => [
                'label' => __('Share Count'),
                'value' => data_get($post->post_metrics, 'share_count', Helper::generateNumberForDemo()) ?: '---',
            ],

            'view_count' => [
                'label' => __('View Count'),
                'value' => data_get($post->post_metrics, 'view_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
        ];
    }

    if ($post->social_media_platform == PlatformEnum::x) {
        $post_metrics = [
            'retweet_count' => [
                'label' => __('Retweet Count'),
                'value' => data_get($post->post_metrics, 'retweet_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
            'reply_count' => [
                'label' => __('Reply Count'),
                'value' => data_get($post->post_metrics, 'reply_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
            'like_count' => [
                'label' => __('Like Count'),
                'value' => data_get($post->post_metrics, 'like_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
            'quote_count' => [
                'label' => __('Quote Count'),
                'value' => data_get($post->post_metrics, 'quote_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
            'bookmark_count' => [
                'label' => __('Bookmark Count'),
                'value' => data_get($post->post_metrics, 'bookmark_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
            'impression_count' => [
                'label' => __('Impression Count'),
                'value' => data_get($post->post_metrics, 'impression_count', Helper::generateNumberForDemo()) ?: Helper::generateNumberForDemo(),
            ],
        ];
    }
@endphp

<div
    class="flex w-full flex-wrap justify-between gap-x-4 gap-y-5"
    x-init="prevPostUrl = '{{ $prev_post_id ? route('dashboard.user.social-media.post.show', $prev_post_id) : null }}';
    nextPostUrl = '{{ $next_post_id ? route('dashboard.user.social-media.post.show', $next_post_id) : null }}';"
>
    <div class="w-full md:w-6/12">
        <p @class([
            'lqd-post-content-type inline-grid w-auto mb-4 place-items-center justify-self-start whitespace-nowrap text-[12px] font-medium leading-none',
            'text-green-500' => $post['status'] === 'published',
            'text-yellow-700' => $post['status'] === 'scheduled',
        ])>
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-flex items-center gap-1.5 rounded-full border px-2 py-1"
                x-show="loadingState === 'loaded'"
                x-transition.opacity
            >
                @if ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::published)
                    <x-tabler-check class="size-4" />
                @elseif ($post['status'] === \App\Extensions\SocialMedia\System\Enums\StatusEnum::scheduled)
                    <x-tabler-clock class="size-4" />
                @else
                    <x-tabler-circle-dashed class="size-4" />
                @endif
                @lang(str()->title($post->status->value))
            </span>
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block h-[26px] w-24 animate-pulse rounded-full bg-heading-foreground/5"
                x-show="loadingState === 'loading'"
                x-transition.opacity
            ></span>
        </p>

        <p class="lqd-post-content-date mb-4 grid place-items-center text-xs">
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block justify-self-start opacity-50"
                x-show="loadingState === 'loaded'"
                x-transition.opacity
            >
                {{ date('M j Y', strtotime($post->created_at)) }}, {{ date('H:i', strtotime($post->created_at)) }}
            </span>
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block h-[1lh] w-1/2 animate-pulse justify-self-start rounded bg-heading-foreground/5"
                x-show="loadingState === 'loading'"
                x-transition.opacity
            ></span>
        </p>

        <p class="lqd-post-content-content mb-7 grid place-items-start text-2xs/4 font-medium text-heading-foreground">
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block"
                x-show="loadingState === 'loaded'"
                x-transition.opacity
                x-html='lqdFormatString(@json($post['content']))'
            >
                {{ $post['content'] }}
            </span>
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block h-[3lh] w-full animate-pulse rounded bg-heading-foreground/5"
                x-show="loadingState === 'loading'"
                x-transition.opacity
            ></span>
        </p>

        <h3 class="mb-3">@lang('Post Details')</h3>

        <div class="lqd-post-content-meta grid grid-cols-2 gap-x-3 gap-y-6">
            @foreach (['company_id', 'product_id', 'campaign_id', 'tone', 'link'] as $meta)
                <div class="contents text-2xs/4 font-medium text-heading-foreground">
                    <p class="m-0 flex flex-wrap items-center gap-1">
                        {{ $meta_titles[$meta] ?? __($meta) }}

                        @if ($meta === 'link' && isset($post[$meta]))
                            <span
                                class="inline-flex shrink-0"
                                x-show="loadingState === 'loaded'"
                                x-transition
                            >
                                <x-button
                                    class="size-6 shrink-0"
                                    size="none"
                                    title="{{ __('Copy Link') }}"
                                    @click.prevent="navigator.clipboard.writeText('{{ $post[$meta] }}'); toastr.success('{{ __('Link copied to clipboard') }}');"
                                >
                                    <x-tabler-link class="size-4" />
                                </x-button>
                            </span>
                        @endif
                    </p>
                    <p class="m-0 grid place-items-center">
                        <span
                            class="col-start-1 col-end-1 row-start-1 row-end-1 justify-self-start opacity-60"
                            x-show="loadingState === 'loaded'"
                            x-transition.opacity
                        >
                            @if (isset($post[$meta]))
                                {{ $meta === 'link' ? $post[$meta] : str()->title($post[$meta]) }}
                            @else
                                ---
                            @endif
                        </span>
                        <span
                            class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block h-[1lh] w-full animate-pulse rounded bg-heading-foreground/5"
                            x-show="loadingState === 'loading'"
                            x-transition.opacity
                        ></span>
                    </p>
                </div>
            @endforeach

        </div>

        <h3 class="mb-3 mt-3">@lang('Post Analytics')</h3>

        <div class="lqd-post-content-meta grid grid-cols-2 gap-x-3 gap-y-6">
            @if (count($post_metrics))
                <div class="contents text-2xs/4 font-medium text-heading-foreground">
                    @foreach ($post_metrics as $value)
                        <div class="contents text-2xs/4 font-medium text-heading-foreground">
                            <p class="m-0 flex flex-wrap items-center gap-1">
                                {{ $value['label'] }}
                            </p>
                            <p class="m-0 grid place-items-center">
                                <span
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 justify-self-start opacity-60"
                                    x-show="loadingState === 'loaded'"
                                    x-transition.opacity
                                >
                                    {{ $value['value'] }}
                                </span>
                                <span
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block h-[1lh] w-full animate-pulse rounded bg-heading-foreground/5"
                                    x-show="loadingState === 'loading'"
                                    x-transition.opacity
                                ></span>
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

    </div>

    <div class="w-full md:w-4/12">
        <div class="flex flex-col gap-5">
            <div class="grid place-items-center">
                <div
                    class="col-start-1 col-end-1 row-start-1 row-end-1 min-h-80 w-full animate-pulse rounded bg-heading-foreground/5"
                    x-show="loadingState === 'loading'"
                    x-transition.opacity
                ></div>
                <div
                    class="col-start-1 col-end-1 row-start-1 row-end-1"
                    x-show="loadingState === 'loaded'"
                    x-transition.opacity
                >
                    <x-card class:head="border-none pb-0">
                        @php
                            $image = 'vendor/social-media/icons/' . $post->platform?->platform . '.svg';
                            $image_dark_version = 'vendor/social-media/icons/' . $post->platform?->platform . '-light.svg';
                            $darkImageExists = file_exists(public_path($image_dark_version));
                        @endphp
                        <x-slot:head>
                            <figure>
                                <img
                                    @class(['w-7 h-auto', 'dark:hidden' => $darkImageExists])
                                    src="{{ asset($image) }}"
                                    alt="{{ $post->platform?->platform }}"
                                />
                                @if ($darkImageExists)
                                    <img
                                        class="hidden h-auto w-7 dark:block"
                                        src="{{ asset($image_dark_version) }}"
                                        alt="{{ $post->platform?->platform }}"
                                    />
                                @endif
                            </figure>
                        </x-slot:head>

                        @php
                            $postImages = $post['images'] ?? ($post['image'] ? [$post['image']] : []);
                        @endphp

                        <figure class="mb-4 w-full">
                            @if ($post['video'])
                                <video
                                    class="w-full rounded"
                                    src="{{ $post['video'] }}"
                                    controls
                                    loading="lazy"
                                ></video>
                            @elseif(count($postImages) > 1)
                                <div
                                    class="relative"
                                    x-data="{ currentSlide: 0, totalSlides: {{ count($postImages) }} }"
                                >
                                    <div class="relative overflow-hidden rounded">
                                        @foreach ($postImages as $idx => $img)
                                            <img
                                                class="w-full rounded"
                                                src="{{ $img }}"
                                                alt="{{ __('Social Media Post Image') }}"
                                                loading="lazy"
                                                decoding="async"
                                                x-show="currentSlide === {{ $idx }}"
                                                x-transition:enter="transition ease-out duration-300"
                                                x-transition:enter-start="opacity-0"
                                                x-transition:enter-end="opacity-100"
                                            >
                                        @endforeach
                                    </div>

                                    <button
                                        class="absolute left-2 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                        x-show="currentSlide > 0"
                                        @click.prevent="currentSlide--"
                                    >
                                        <x-tabler-chevron-left class="size-4" />
                                    </button>
                                    <button
                                        class="absolute right-2 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70"
                                        x-show="currentSlide < totalSlides - 1"
                                        @click.prevent="currentSlide++"
                                    >
                                        <x-tabler-chevron-right class="size-4" />
                                    </button>

                                    <div class="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-1.5">
                                        @foreach ($postImages as $idx => $img)
                                            <button
                                                class="size-2 rounded-full transition-all"
                                                :class="currentSlide === {{ $idx }} ? 'bg-white scale-110' : 'bg-white/50'"
                                                @click.prevent="currentSlide = {{ $idx }}"
                                            ></button>
                                        @endforeach
                                    </div>

                                    <span class="absolute right-2 top-2 rounded-full bg-black/50 px-2 py-0.5 text-xs text-white">
                                        <span x-text="currentSlide + 1"></span>/{{ count($postImages) }}
                                    </span>
                                </div>
                            @elseif(count($postImages) === 1)
                                <img
                                    class="w-full rounded"
                                    src="{{ $postImages[0] }}"
                                    alt="{{ __('Social Media Post Image') }}"
                                    loading="lazy"
                                    decoding="async"
                                >
                            @endif
                        </figure>

                        <p
                            class="mb-0 max-h-24 overflow-hidden text-ellipsis text-2xs/6 font-medium text-heading-foreground"
                            style="mask-image: linear-gradient(to bottom, black 50%, transparent)"
                        >
                            {{ $post['content'] }}
                        </p>
                    </x-card>
                </div>
            </div>

            @if ($post['link'])
                <x-button
                    variant="outline"
                    target="_blank"
                    href="{{ $post['link'] }}"
                    size="lg"
                    x-show="loadingState === 'loaded'"
                >
                    @lang('View Post on ')
                    {{ str()->title($post->platform?->platform) }}
                </x-button>
            @endif

            <x-button
                variant="outline"
                href="#"
                size="lg"
                hover-variant="danger"
                x-show="loadingState === 'loaded'"
                href="{{ route('dashboard.user.social-media.post.delete', $post['id']) }}"
                onclick="return confirm('Are you sure?')"
            >
                @lang('Delete')
            </x-button>
        </div>
    </div>
</div>
