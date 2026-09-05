<x-table
    class="relative"
    id="blogpilot-posts-list"
    ::class="{ 'animate-pulse': currentTasks.has('fetchingPosts') }"
    x-ref="postsList"
>
    <x-slot:head>
        <th>
            <button
                class="flex items-center gap-2"
                @click.prevent="sortPosts('title')"
                type="button"
            >
                @lang('Title')
                <span class="-ms-1 flex flex-col items-center">
                    <x-tabler-chevron-up
                        class="-mb-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'content' && sort.sortDirection === 'desc' }"
                        stroke-width="2.5"
                    />
                    <x-tabler-chevron-down
                        class="-mt-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'content' && sort.sortDirection === 'asc' }"
                        stroke-width="2.5"
                    />
                </span>
            </button>
        </th>

        <th>
            <button
                class="flex items-center gap-2"
                @click.prevent="sortPosts('status')"
                type="button"
            >
                @lang('Status')
                <span class="-ms-1 flex flex-col items-center">
                    <x-tabler-chevron-up
                        class="-mb-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'status' && sort.sortDirection === 'desc' }"
                        stroke-width="2.5"
                    />
                    <x-tabler-chevron-down
                        class="-mt-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'status' && sort.sortDirection === 'asc' }"
                        stroke-width="2.5"
                    />
                </span>
            </button>
        </th>

        <th class="min-w-36">
            <button
                class="flex items-center gap-2"
                type="button"
                @click.prevent="sortPosts('created_at')"
            >
                @lang('Date')
                <span class="-ms-1 flex flex-col items-center">
                    <x-tabler-chevron-up
                        class="-mb-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'created_at' && sort.sortDirection === 'desc' }"
                        stroke-width="2.5"
                    />
                    <x-tabler-chevron-down
                        class="-mt-0.5 h-auto w-3 transition-opacity"
                        ::class="{ 'opacity-30': sort.sortBy === 'created_at' && sort.sortDirection === 'asc' }"
                        stroke-width="2.5"
                    />
                </span>
            </button>
        </th>

        <th class="text-end">
            @lang('Actions')
        </th>
    </x-slot:head>

    <x-slot:body>
        @include('blogpilot::components.posts.list.post-items', ['items' => $posts])
    </x-slot:body>

    <x-slot:foot
        class="border-t"
    >
        @if ($posts->hasMorePages())
            <tr>
                <td colspan="5">
                    <div class="blogpilot-posts-list-load-more-wrap">
                        <a
                            class="blogpilot-posts-list-load-more group inline-flex w-full items-center justify-center gap-2 text-xs font-medium"
                            href="{{ route('dashboard.user.blogpilot.agent.post-items', ['page' => 2, 'post_style' => 'list']) }}"
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
                            <span class="inline-grid size-8 place-items-center rounded-full border">
                                <x-tabler-progress-down
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-5"
                                    x-show="!loadingMore && !allPostsLoaded"
                                />
                                <x-tabler-loader-2
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 animate-spin"
                                    x-cloak
                                    x-show="loadingMore"
                                />
                                <x-tabler-check
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-5"
                                    x-cloak
                                    x-show="!loadingMore && allPostsLoaded"
                                />
                            </span>
                        </a>
                    </div>
                </td>
            </tr>
        @endif
    </x-slot:foot>
</x-table>
