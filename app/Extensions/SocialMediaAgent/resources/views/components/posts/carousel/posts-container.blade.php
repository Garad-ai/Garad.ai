@php
    $show_heading_and_arrows = $show_heading_and_arrows ?? true;
@endphp

@push('css')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('assets/css/frontend/flickity.min.css') }}"
    >
@endpush

@if ($show_heading_and_arrows)
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2>
            @lang('Latest Posts')
        </h2>

        @if (filled($posts))
            <div class="flex items-center gap-2">
                <button
                    class="inline-grid size-6 place-items-center rounded-md transition hover:bg-foreground hover:text-background hover:shadow-lg hover:shadow-black/5"
                    @click.prevent="flickityData.previous()"
                >
                    <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
                </button>
                <button
                    class="inline-grid size-6 place-items-center rounded-md transition hover:bg-foreground hover:text-background hover:shadow-lg hover:shadow-black/5"
                    @click.prevent="flickityData.next()"
                >
                    <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
                </button>
            </div>
        @endif
    </div>
@endif

<div
    id="social-media-agent-posts-carousel"
    @class([
        '-mx-5 overflow-hidden [&_.flickity-viewport]:w-full [&_.flickity-slider]:w-full' => filled(
            $posts),
        'flex relative [&.flickity-resize_.social-media-agent-post-item]:min-h-full',
    ])
    :class="{ 'animate-pulse': currentTasks.has('fetchingPosts') }"
    x-ref="postsCarousel"
>
    @include('social-media-agent::components.posts.carousel.post-items', ['items' => $posts])

    @if ($posts->hasMorePages())
        <div
            class="social-media-agent-posts-carousel-load-more-wrap social-media-agent-post-item flex min-h-full w-full flex-shrink-0 flex-grow-0 basis-auto flex-col px-5 py-2 md:w-1/2 lg:w-1/4">
            <div class="flex min-h-full grow items-center justify-center p-3.5">
                <a
                    class="social-media-agent-posts-carousel-load-more group inline-flex w-full flex-col items-center gap-2 p-10 text-center text-sm font-medium"
                    href="{{ route('dashboard.user.social-media.agent.post-items', ['page' => 2, 'post_style' => 'carousel']) }}"
                    x-intersect:enter.half="!allPostsLoaded && !loadingMore && $ajax($el.href, { target: '_none' })"
                    x-ref="loadMoreTrigger"
                    @click.prevent="!allPostsLoaded && !loadingMore && $ajax($el.href, { target: '_none' })"
                    @ajax:send="onAjaxSend"
                    @ajax:success="onAjaxSuccess"
                    @ajax:error="onAjaxError"
                >
                    <span x-text="allPostsLoaded ? '{{ __('All posts loaded') }}' : loadingMore ? '{{ __('Loading...') }}' : '{{ __('Load more') }}'">
                        @lang('Load more')
                    </span>
                    <span class="inline-grid size-10 place-items-center rounded-full border">
                        <x-tabler-progress-down
                            class="col-start-1 col-end-1 row-start-1 row-end-1 size-6"
                            x-show="!loadingMore && !allPostsLoaded"
                        />
                        <x-tabler-loader-2
                            class="col-start-1 col-end-1 row-start-1 row-end-1 size-6 animate-spin"
                            x-cloak
                            x-show="loadingMore"
                        />
                        <x-tabler-check
                            class="col-start-1 col-end-1 row-start-1 row-end-1 size-6"
                            x-cloak
                            x-show="!loadingMore && allPostsLoaded"
                        />
                    </span>
                </a>
            </div>
        </div>
    @endif
</div>

@push('script')
    <script src="{{ custom_theme_url('assets/libs/flickity.pkgd.min.js') }}"></script>
@endpush
