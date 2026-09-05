@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;

    $sort_buttons = [
        [
            'label' => __('Date'),
            'sort' => 'created_at',
        ],
        [
            'label' => __('Status'),
            'sort' => 'status',
        ],
        [
            'label' => __('Platform'),
            'sort' => 'platform',
        ],
    ];

    $filter_buttons = [
        [
            'label' => __('All'),
            'filter' => 'all',
        ],
    ];

    $filter = request()->query('filter', 'all');

    foreach ($platforms as $platform) {
        $filter_buttons[] = [
            'label' => $platform->label(),
            'filter' => $platform->value,
        ];
    }
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Social Media Posts'))

@section('titlebar_actions')
    @include('social-media::components.create-post-dropdown', ['platforms' => $platforms])
@endsection

@section('titlebar_after')
    <div class="flex flex-wrap items-center justify-between gap-2">
        <form
            class="lqd-filter-list flex flex-wrap items-center gap-x-4 gap-y-2 text-heading-foreground max-sm:gap-3"
            action="{{ route('dashboard.user.social-media.post.index', ['listOnly' => 'true']) }}"
            method="GET"
            x-init
            x-target="lqd-posts-container"
            @submit="$store.socialMediaPostsFilter.changePage('1')"
        >
            <input
                type="hidden"
                name="sort"
                :value="$store.socialMediaPostsFilter.sort"
            >
            <input
                type="hidden"
                name="page"
                value="1"
            >
            <input
                type="hidden"
                name="sortAscDesc"
                :value="$store.socialMediaPostsFilter.sortAscDesc"
            >
            @foreach ($filter_buttons as $button)
                <x-button
                    @class([
                        'lqd-filter-btn inline-flex rounded-full px-2.5 py-0.5 transition-colors hover:bg-foreground/5 [&.active]:bg-foreground/5 hover:translate-y-0 text-2xs leading-tight',
                        'active' => $filter == $button['filter'],
                    ])
                    tag="button"
                    type="submit"
                    name="filter"
                    value="{{ $button['filter'] }}"
                    variant="ghost"
                    ::class="{ active: $store.socialMediaPostsFilter.filter === '{{ $button['filter'] }}' }"
                    @click="$store.socialMediaPostsFilter.changeFilter('{{ $button['filter'] }}')"
                >
                    {{ $button['label'] }}
                </x-button>
            @endforeach
        </form>

        <div class="flex items-center gap-3">
            <x-dropdown.dropdown
                anchor="end"
                offsetY="1rem"
            >
                <x-slot:trigger
                    class="whitespace-nowrap px-2 py-1"
                    variant="link"
                    size="xs"
                >
                    {{ __('Sort by:') }}
                    <x-tabler-arrows-sort class="size-4 shrink-0" />
                </x-slot:trigger>

                <x-slot:dropdown
                    class="overflow-hidden text-2xs font-medium"
                >
                    <form
                        class="lqd-sort-list flex flex-col"
                        action="{{ route('dashboard.user.social-media.post.index', ['listOnly' => 'true']) }}"
                        method="GET"
                        x-init
                        x-target="lqd-posts-container"
                        @submit="$store.socialMediaPostsFilter.changePage('1')"
                    >
                        <input
                            type="hidden"
                            name="filter"
                            :value="$store.socialMediaPostsFilter.filter"
                        >
                        <input
                            type="hidden"
                            name="page"
                            value="1"
                        >
                        <input
                            type="hidden"
                            name="sortAscDesc"
                            :value="$store.socialMediaPostsFilter.sortAscDesc"
                        >
                        @foreach ($sort_buttons as $button)
                            <button
                                class="group flex w-full items-center gap-1 px-3 py-2 hover:bg-foreground/5 [&.active]:bg-foreground/5"
                                :class="$store.socialMediaPostsFilter.sort === '{{ $button['sort'] }}' && 'active'"
                                name="sort"
                                value="{{ $button['sort'] }}"
                                @click="$store.socialMediaPostsFilter.changeSort('{{ $button['sort'] }}')"
                            >
                                {{ $button['label'] }}
                                <x-tabler-caret-down-filled
                                    class="size-3 opacity-0 transition-all group-[&.active]:opacity-80"
                                    ::class="$store.socialMediaPostsFilter.sortAscDesc === 'asc' && 'rotate-180'"
                                />
                            </button>
                        @endforeach
                    </form>
                </x-slot:dropdown>
            </x-dropdown.dropdown>

            <div class='lqd-posts-view-toggle lqd-docs-view-toggle lqd-view-toggle relative z-1 flex w-full items-center gap-2 lg:ms-auto lg:justify-end')>
                <button
                    class="lqd-view-toggle-trigger inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-foreground/5 [&.active]:bg-foreground/5"
                    :class="$store.socialMediaPostsViewMode.socialMediaPostsViewMode === 'list' && 'active'"
                    x-init
                    @click="$store.socialMediaPostsViewMode.change('list')"
                    title="List view"
                >
                    <x-tabler-list
                        class="size-5"
                        stroke-width="1.5"
                    />
                </button>
                <button
                    class="lqd-view-toggle-trigger inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-foreground/5 [&.active]:bg-foreground/5"
                    :class="$store.socialMediaPostsViewMode.socialMediaPostsViewMode === 'grid' && 'active'"
                    x-init
                    @click="$store.socialMediaPostsViewMode.change('grid')"
                    title="Grid view"
                >
                    <x-tabler-layout-grid
                        class="size-5"
                        stroke-width="1.5"
                    />
                </button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="py-10">
        @include('social-media::components.post.posts-container', ['posts' => $posts, 'filter' => $filter])
    </div>
@endsection
@push('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('socialMediaPosts', () => ({
                    modalOpen: false,
                    loadingState: 'loading',
                    prevPostUrl: null,
                    nextPostUrl: null,
                    async submitDuplicate() {
                        let form = this.$refs.form;

                        let formData = new FormData(form);
                        try {
                            let response = await fetch(formData.get('route'), {
                                method: "POST",
                                headers: {
                                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    "Accept": "application/json"
                                },
                                body: formData
                            });

                            let result = await response.json();

                            if (result.status === 'success') {
                                toastr.success(result.message);
                                window.location = `${result.redirect}`;
                                this.setModalOpen(false);
                            } else {
                                toastr.error(result.message);
                            }
                        } catch (error) {
                            console.error("Error:", error);
                        }
                    },
                    setModalOpen(state) {
                        if (state === this.modalOpen) return;

                        this.setLoadingState('loading');
                        this.modalOpen = state;

                        if (!this.modalOpen) {
                            const url = new URL(window.location);
                            url.searchParams.delete('show');
                            window.history.replaceState({}, document.title, url);
                        }
                    },
                    setLoadingState(state) {
                        if (state === this.loadingState) return;

                        this.loadingState = state;
                    },
                    onAjaxBefore() {
                        this.$dispatch('modal:open');
                        this.setLoadingState('loading');
                    },
                    onAjaxSuccess() {
                        const {
                            url
                        } = this.$event.detail;
                        this.setLoadingState('loaded');

                        if (url) {
                            const postId = url.split('/').pop();
                            const newUrl = new URL(window.location);
                            newUrl.searchParams.set('show', postId);
                            window.history.replaceState({}, document.title, newUrl);
                        }
                    },
                    onAjaxError() {
                        this.$dispatch('modal:close');
                        toastr.error('{{ __('Failed fetching post.') }}.');
                    },
                }));
            });
        })();
    </script>
@endpush
