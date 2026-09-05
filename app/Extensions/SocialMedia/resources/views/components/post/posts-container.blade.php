@php
    //Replicating table styles from table component
    $base_class = 'rounded-xl transition-colors';

    $variations = [
        'variant' => [
            'solid' => 'rounded-card bg-card-background pt-1 group-[&[data-view-mode=grid]]:bg-transparent',
            'outline' => 'rounded-card border border-card-border pt-1 group-[&[data-view-mode=grid]]:border-0',
            'shadow' => ' rounded-card shadow-card bg-card-background pt-1 group-[&[data-view-mode=grid]]:shadow-none group-[&[data-view-mode=grid]]:bg-transparent',
            'outline-shadow' => 'rounded-card border border-card-border pt-1 shadow-card bg-card-background',
            'plain' => '',
        ],
    ];

    $variant =
        isset($variant) && isset($variations['variant'][$variant])
            ? $variations['variant'][$variant]
            : $variations['variant'][Theme::getSetting('defaultVariations.table.variant', 'outline')];

    $class = @twMerge($base_class, $variant);
@endphp

<div
    class="lqd-social-media-posts-wrap"
    x-data="socialMediaPosts"
    @modal:open.window="setModalOpen(true);"
    @modal:close.window="setModalOpen(false);"
    @keyup.escape.window="$dispatch('modal:close')"
    @keydown.left.window="modalOpen && !$refs.prevBtn.hasAttribute('disabled') && $refs.prevBtn.click();"
    @keydown.right.window="modalOpen && !$refs.nextBtn.hasAttribute('disabled') && $refs.nextBtn.click();"
>
    <div
        class="lqd-posts-container lqd-social-posts-container group transition-all [&[aria-busy=true]]:animate-pulse"
        id="lqd-posts-container"
        data-view-mode="list"
        x-bind:data-view-mode="$store.socialMediaPostsViewMode.socialMediaPostsViewMode"
        x-init="$watch('$store.socialMediaPostsViewMode.socialMediaPostsViewMode', value => {
            document.body.setAttribute('data-view-mode', value);
        })"
        x-merge.transition
    >
        {{-- Setting the view mode attribute before contents load to avoid page flashes --}}
        <script>
            const savedViewMode = localStorage.getItem('socialMediaPostsViewMode')?.replace(/\"/g, '') || 'list';
            document.querySelector('.lqd-posts-container')?.setAttribute('data-view-mode', savedViewMode);
            document.body.setAttribute('data-view-mode', savedViewMode);

            console.log('Saved view mode:', savedViewMode);
        </script>

        @if (filled($posts))
            <div class="{{ $class }}">
                <div
                    class="lqd-social-posts-head grid gap-x-5 border-b px-4 py-3 text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 ![grid-template-columns:3fr_repeat(4,minmax(0,1fr))_100px_1fr] group-[&[data-view-mode=grid]]:hidden max-lg:hidden">
                    <span>
                        {{ __('Content') }}
                    </span>

                    <span>
                        {{ __('Status') }}
                    </span>

                    <span>
                        {{ __('Publish Date') }}
                    </span>

                    <span>
                        {{ __('Type') }}
                    </span>

                    <span>
                        {{ __('Like Count') }}
                    </span>

                    <span>
                        {{ __('Platform') }}
                    </span>

                    <span class="text-end">
                        {{ __('Actions') }}
                    </span>
                </div>

                <div
                    class="lqd-posts-list lqd-social-media-posts-list group-[&[data-view-mode=grid]]:grid group-[&[data-view-mode=grid]]:grid-cols-2 group-[&[data-view-mode=grid]]:gap-5 md:group-[&[data-view-mode=grid]]:grid-cols-3 lg:group-[&[data-view-mode=grid]]:grid-cols-4 lg:group-[&[data-view-mode=grid]]:gap-8 xl:group-[&[data-view-mode=grid]]:grid-cols-5"
                    id="lqd-posts-list"
                >
                    @foreach ($posts as $post)
                        @if ($filter === 'all' || (isset($filter) && !empty($filter) && isset($post?->platform['platform']) && $post?->platform['platform'] === $filter))
                            @include('social-media::components.post.posts-list-item', ['post' => $post])
                        @endif
                    @endforeach
                </div>
            </div>

            {{ $posts->links('pagination::ajax', [
                'action' => '#',
            ]) }}
        @else
            <h2>
                {{ __('No posts found.') }}
            </h2>
        @endif

    </div>

    {{-- Modal --}}
    <div
        class="lqd-modal-post group/modal invisible fixed start-0 top-0 z-[999] flex h-screen w-screen flex-col items-center border p-3 opacity-0 [&.is-active]:visible [&.is-active]:opacity-100"
        :class="{ 'is-active': modalOpen }"
    >
        <div
            class="lqd-modal-post-backdrop absolute start-0 top-0 z-0 h-screen w-screen bg-black/10 opacity-0 backdrop-blur-sm transition-opacity group-[&.is-active]/modal:opacity-100"
            @click="$dispatch('modal:close')"
        ></div>

        <div class="lqd-modal-post-content-wrap pointer-events-none relative z-10 my-auto max-h-[90vh] w-full">
            <div class="container relative h-full max-w-4xl">
                <a
                    class="pointer-events-auto absolute -end-1 -top-4 z-10 flex size-9 items-center justify-center rounded-full border bg-background text-inherit shadow-sm transition-all hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black"
                    @click.prevent="$dispatch('modal:close')"
                    href="#"
                >
                    <x-tabler-x class="size-4" />
                </a>
                <div
                    class="lqd-modal-post-content pointer-events-auto relative flex h-full translate-y-2 scale-[0.985] flex-wrap justify-between overflow-y-auto rounded-xl bg-background p-8 opacity-0 shadow-2xl transition-all group-[&.is-active]/modal:translate-y-0 group-[&.is-active]/modal:scale-100 group-[&.is-active]/modal:opacity-100">

                    @include('social-media::post.show', ['post', []])
                </div>

                <!-- Prev/Next buttons -->
                <x-button
                    class="pointer-events-auto absolute -start-1 top-1/2 z-10 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-background text-inherit shadow-md transition-all hover:-translate-y-1/2 hover:scale-110 hover:bg-primary hover:text-primary-foreground [&[disabled]]:pointer-events-none [&[disabled]]:opacity-50"
                    size="none"
                    variant="ghost-shadow"
                    ::href="prevPostUrl"
                    ::disabled="!prevPostUrl"
                    x-target="lqd-social-media-post-content"
                    @ajax:before="onAjaxBefore()"
                    @ajax:success="onAjaxSuccess()"
                    @ajax:error="onAjaxError()"
                    x-ref="prevBtn"
                >
                    <x-tabler-chevron-left class="size-5" />
                </x-button>
                <x-button
                    class="pointer-events-auto absolute -end-1 top-1/2 z-10 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-background text-inherit shadow-md transition-all hover:-translate-y-1/2 hover:scale-110 hover:bg-primary hover:text-primary-foreground [&[disabled]]:pointer-events-none [&[disabled]]:opacity-50"
                    size="none"
                    variant="ghost-shadow"
                    ::href="nextPostUrl"
                    ::disabled="!nextPostUrl"
                    x-target="lqd-social-media-post-content"
                    @ajax:before="onAjaxBefore()"
                    @ajax:success="onAjaxSuccess()"
                    @ajax:error="onAjaxError()"
                    x-ref="nextBtn"
                >
                    <x-tabler-chevron-right class="size-5" />
                </x-button>
            </div>
        </div>
    </div>
</div>

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/js/format-string.js') }}"></script>
@endpush
