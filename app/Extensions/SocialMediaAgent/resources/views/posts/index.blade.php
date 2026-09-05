@php
    $theme = \Theme::get();

    $post_types = [
        'post' => [
            'label' => __('Post'),
        ],
        'story' => [
            'label' => __('Story'),
        ],
    ];

    $sort_options = [
        'created_at' => [
            'label' => __('Date'),
        ],
        'status' => [
            'label' => __('Status'),
        ],
        'platform_id' => [
            'label' => __('Platform'),
        ],
        'content' => [
            'label' => __('Content'),
        ],
    ];

    $platforms_with_image = collect($platforms)
        ->map(function ($platform) {
            $timestampKeys = ['created_at', 'updated_at', 'deleted_at', 'connected_at', 'expires_at'];
            $isArray = is_array($platform);

            $name = $isArray ? $platform['platform'] ?? null : $platform->platform ?? null;

            $image = asset('vendor/social-media/icons/' . $name . '.svg');
            $image_dark_version = asset('vendor/social-media/icons/' . $name . '-mono-light.svg');
            $darkImageExists = file_exists(public_path($image_dark_version));

            if ($isArray) {
                $platform = \Illuminate\Support\Arr::except($platform, $timestampKeys);
                $platform['image'] = $image;
                $platform['image_dark_version'] = $darkImageExists ? $image_dark_version : null;

                return $platform;
            }

            foreach ($timestampKeys as $key) {
                unset($platform->{$key});
            }

            $platform->image = $image;
            $platform->image_dark_version = $darkImageExists ? $image_dark_version : null;

            return $platform;
        })
        ->values()
        ->all();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Social Media Posts'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Create and edit posts'))
@section('titlebar_actions')
    @include('social-media-agent::components.titlebar-actions')
@endsection

@push('css')
    <link
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
        rel="stylesheet"
    />
@endpush

@section('content')
    <div @class([
        'pb-10',
        'pt-10' => $theme !== 'social-media-agent-dashboard',
    ])>
        @if ($theme === 'social-media-agent-dashboard')
            @include('social-media-agent::posts.titlebar')
        @endif

        @if (filled($platforms))
            @php
                $videoPlatforms = ['youtube', 'youtube-shorts'];
            @endphp
            <div class="mb-8 grid grid-cols-2 gap-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                @foreach ($platforms as $platform)
                    @if (in_array($platform->platform, $videoPlatforms))
                        @continue
                    @endif
                    <x-card
                        class="hover:-translate-y-1 hover:shadow-lg hover:shadow-black/5"
                        x-data="{}"
                    >
                        @php
                            $image = asset('vendor/social-media/icons/' . $platform->platform . '.svg');
                            $image_dark_version = asset('vendor/social-media/icons/' . $platform->platform . '-mono-light.svg');
                            $platformLabel = method_exists($platform, 'platformLabel')
                                ? $platform->platformLabel()
                                : \App\Extensions\SocialMedia\System\Enums\PlatformEnum::tryFrom($platform->platform)?->label() ??
                                    \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $platform->platform));
                        @endphp

                        <figure class="mb-9">
                            <img
                                class="h-auto max-h-8 w-8 dark:hidden"
                                src="{{ $image }}"
                                alt="{{ $platformLabel }}"
                                width="32"
                                height="32"
                            >
                            <img
                                class="hidden h-auto max-h-8 w-8 dark:block"
                                src="{{ $image_dark_version }}"
                                alt="{{ $platformLabel }}"
                                width="32"
                                height="32"
                            >
                        </figure>

                        <p class="mb-0 text-sm font-medium">
                            {{ $platformLabel }}
                        </p>
                        @if (isset($platform->credentials['username']))
                            <p class="m-0 opacity-70">
                                {{ $platform->credentials['username'] }}
                            </p>
                        @endif

                        <a
                            class="absolute start-0 top-0 z-2 h-full w-full"
                            href="#"
                            @click.prevent="$dispatch('create-post-modal-show', {show: true, platform_id: {{ $platform->id }}})"
                        ></a>
                    </x-card>
                @endforeach
            </div>
        @endif

        <div x-data="socialMediaAgentPosts">
            <div class="mb-9 flex flex-wrap justify-between gap-5 max-md:gap-y-3 xl:flex-nowrap xl:gap-10">
                <div class="flex grow items-center gap-3 transition max-md:flex-wrap lg:gap-8 lg:border-b">
                    <div
                        class="relative flex grow max-lg:border-b max-md:w-full lg:contents"
                        x-data="{ selectedPlatformLabel: '{{ __('All Platforms') }}', dropdownOpen: false }"
                        @click.outside="dropdownOpen = false"
                    >
                        <span
                            class="hidden grow items-center gap-1 max-lg:flex max-lg:w-full max-lg:justify-between max-lg:border-b max-lg:px-2 max-lg:py-4 max-md:py-2"
                            @click.prevent="dropdownOpen = !dropdownOpen"
                        >
                            <span x-text="selectedPlatformLabel">
                                @lang('All Platforms')
                            </span>

                            <x-tabler-chevron-down class="size-4" />
                        </span>

                        <div
                            class="contents max-lg:invisible max-lg:absolute max-lg:start-0 max-lg:top-full max-lg:z-1 max-lg:block max-lg:w-full max-lg:rounded-dropdown max-lg:border max-lg:border-dropdown-border max-lg:bg-dropdown-background max-lg:p-2 max-lg:opacity-0 max-lg:transition"
                            :class="{ 'max-lg:invisible max-lg:opacity-0': !dropdownOpen }"
                        >
                            <button
                                class="active inline-flex px-2 py-2 text-xs font-normal transition max-lg:w-full lg:-mb-px lg:border-b lg:border-transparent lg:py-4 [&.active]:border-b-heading-foreground"
                                :class="{ active: filters.platform == '' }"
                                @click.prevent="filterPosts({platform: ''}); selectedPlatformLabel = '{{ __('All Platforms') }}';"
                            >
                                @lang('All')
                            </button>

                            @if (filled($platformEnums))
                                @foreach ($platformEnums as $platform)
                                    @if (in_array($platform->value, $videoPlatforms))
                                        @continue
                                    @endif
                                    <button
                                        class="inline-flex px-2 py-2 text-xs font-normal transition max-lg:w-full lg:-mb-px lg:border-b lg:border-transparent lg:py-4 lg:[&.active]:border-b-heading-foreground"
                                        :class="{ active: filters.platform == '{{ $platform->value }}' }"
                                        @click.prevent="filterPosts({platform: '{{ $platform->value }}'}); selectedPlatformLabel = '{{ $platform->label() }}';"
                                    >
                                        {{ $platform->label() }}
                                    </button>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div class="flex gap-4 md:ms-auto">
                        <div class="flex items-center self-center">
                            @php
                                $agentsToShow = 5;
                            @endphp
                            @foreach ($agents->take($agentsToShow) as $agent)
                                <button
                                    class="relative -ms-2.5 shrink-0 transition before:pointer-events-none before:absolute before:-end-2 before:top-full before:mt-1 before:w-52 before:translate-y-0 before:rounded-md before:bg-background before:px-2 before:py-2 before:text-[12px] before:font-medium before:opacity-0 before:shadow-md before:shadow-black/5 before:transition before:content-[attr(title)] hover:z-2 hover:-translate-y-1 hover:scale-110 hover:before:translate-y-0 hover:before:opacity-100"
                                    title="{{ $agent->name }}"
                                    ::class="{'active': filters.agent_id.includes({{ $agent['id'] }})}"
                                    @click.prevent="filterPosts({agent_id: {{ $agent['id'] }}});"
                                >
                                    <figure
                                        class="inline-grid size-8 place-items-center overflow-hidden rounded-full border-2 border-background bg-[#969696] text-background dark:bg-[#414954] dark:text-foreground"
                                        :class="{ 'border-green-400 shadow-md shadow-green-400/50': filters.agent_id.includes({{ $agent['id'] }}) }"
                                    >
                                        @if ($agent->image)
                                            <img
                                                class="size-full object-cover object-center"
                                                src="{{ $agent->image }}"
                                                alt="{{ $agent->name }}"
                                            >
                                        @else
                                            <svg
                                                width="14"
                                                height="19"
                                                viewBox="0 0 17 23"
                                                fill="currentColor"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    d="M8.17757 0C11.0099 0 13.305 2.29589 13.305 5.12695C13.305 7.95869 11.0099 10.2539 8.17757 10.2539C5.34664 10.2539 3.05152 7.95869 3.05152 5.12695C3.05152 2.29589 5.34664 0 8.17757 0ZM8.17416 22.3128C5.34936 22.3128 2.76217 21.284 0.766625 19.5811C0.280506 19.1665 0 18.5585 0 17.9205C0 15.0493 2.32371 12.7513 5.19549 12.7513H11.161C14.0335 12.7513 16.3483 15.0493 16.3483 17.9205C16.3483 18.5591 16.0692 19.1658 15.5824 19.5805C13.5875 21.284 10.9996 22.3128 8.17416 22.3128Z"
                                                />
                                            </svg>
                                        @endif
                                    </figure>
                                </button>
                            @endforeach

                            @if ($agents->count() > $agentsToShow)
                                <x-dropdown.dropdown
                                    class="-ms-2.5"
                                    anchor="end"
                                    offsetY="10px"
                                >
                                    <x-slot:trigger
                                        class="relative size-8 shrink-0 rounded-full border-2 border-background bg-foreground p-0 text-2xs font-medium text-background transition hover:z-2 hover:-translate-y-1 hover:scale-110 hover:bg-foreground hover:text-background"
                                        variant="ghost"
                                        size="none"
                                    >
                                        +{{ $agents->count() - $agentsToShow }}
                                    </x-slot:trigger>

                                    <x-slot:dropdown
                                        class="min-w-[250px] overflow-hidden rounded-lg bg-background shadow-lg"
                                    >
                                        @foreach ($agents->skip($agentsToShow) as $agent)
                                            <x-button
                                                class="w-full justify-start gap-2 rounded-none text-start hover:transform-none [&.active]:underline"
                                                ::class="{ 'active': filters.agent_id.includes({{ $agent['id'] }}) }"
                                                @click.prevent="filterPosts({agent_id: {{ $agent['id'] }}})"
                                                variant="ghost"
                                                title="{{ $agent->name }}"
                                            >
                                                <figure
                                                    class="inline-grid size-6 shrink-0 place-items-center overflow-hidden rounded-full bg-[#969696] text-background dark:bg-[#414954] dark:text-foreground"
                                                    :class="{ 'ring-2 ring-green-400': filters.agent_id.includes({{ $agent['id'] }}) }"
                                                >
                                                    @if ($agent->image)
                                                        <img
                                                            class="size-full object-cover object-center"
                                                            src="{{ $agent->image }}"
                                                            alt="{{ $agent->name }}"
                                                        >
                                                    @else
                                                        <svg
                                                            width="10"
                                                            height="14"
                                                            viewBox="0 0 17 23"
                                                            fill="currentColor"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                        >
                                                            <path
                                                                d="M8.17757 0C11.0099 0 13.305 2.29589 13.305 5.12695C13.305 7.95869 11.0099 10.2539 8.17757 10.2539C5.34664 10.2539 3.05152 7.95869 3.05152 5.12695C3.05152 2.29589 5.34664 0 8.17757 0ZM8.17416 22.3128C5.34936 22.3128 2.76217 21.284 0.766625 19.5811C0.280506 19.1665 0 18.5585 0 17.9205C0 15.0493 2.32371 12.7513 5.19549 12.7513H11.161C14.0335 12.7513 16.3483 15.0493 16.3483 17.9205C16.3483 18.5591 16.0692 19.1658 15.5824 19.5805C13.5875 21.284 10.9996 22.3128 8.17416 22.3128Z"
                                                            />
                                                        </svg>
                                                    @endif
                                                </figure>
                                                <span class="block w-full truncate">
                                                    {{ $agent->name }}
                                                </span>
                                            </x-button>
                                        @endforeach
                                    </x-slot:dropdown>
                                </x-dropdown.dropdown>
                            @endif
                        </div>

                        <div class="group relative flex">
                            <x-dropdown.dropdown
                                class="w-36"
                                class:dropdown-dropdown="max-lg:!start-0 max-lg:!end-auto min-w-36"
                                anchor="end"
                                offsetY="15px"
                                :teleport=false
                            >
                                <x-slot:trigger
                                    class="w-full whitespace-nowrap py-2 md:py-4"
                                    variant="link"
                                >
                                    <template x-if="filters.platform_id">
                                        <figure class="m-0 shrink-0">
                                            <img
                                                class="h-auto max-h-5 w-5 dark:hidden"
                                                aria-hidden="true"
                                                :src="getPlatformById(filters.platform_id)?.image ?? ''"
                                                width="32"
                                                height="32"
                                            >
                                            <img
                                                class="hidden h-auto max-h-5 w-5 dark:block"
                                                aria-hidden="true"
                                                :src="getPlatformById(filters.platform_id)?.image_dark_version ?? ''"
                                                width="32"
                                                height="32"
                                            >
                                        </figure>
                                    </template>
                                    <span
                                        class="w-full truncate"
                                        x-text="filters.platform_id ? (getPlatformById(filters.platform_id)?.credentials?.username ?? '') : '{{ __('All Accounts') }}'"
                                    >
                                        @lang('All Accounts')
                                    </span>

                                    <span class="ms-auto inline-flex items-center gap-1">
                                        <span
                                            class="relative z-2 inline-grid size-6 place-items-center rounded-full transition hover:bg-red-400 hover:text-white"
                                            @click.prevent.stop="filterPosts({platform_id: ''})"
                                            x-cloak
                                            x-show="filters.platform_id"
                                        >
                                            <x-tabler-x class="size-4" />
                                        </span>
                                        <x-tabler-chevron-down class="size-4" />
                                    </span>
                                </x-slot:trigger>

                                <x-slot:dropdown
                                    class="min-w-[150px] overflow-hidden rounded-lg bg-background shadow-lg"
                                >
                                    @foreach ($platforms_with_image as $platform)
                                        @php
                                            $image = asset('vendor/social-media/icons/' . $platform->platform . '.svg');
                                            $image_dark_version = asset('vendor/social-media/icons/' . $platform->platform . '-mono-light.svg');
                                        @endphp

                                        <x-button
                                            class="w-full justify-start rounded-none text-start hover:transform-none [&.active]:underline"
                                            ::class="{ 'active': filters.platform_id == {{ $platform->id }} }"
                                            @click.prevent="filterPosts({platform_id: {{ $platform->id }}})"
                                            variant="ghost"
                                        >
                                            <figure class="m-0 shrink-0">
                                                <img
                                                    class="h-auto max-h-5 w-5 dark:hidden"
                                                    src="{{ $image }}"
                                                    alt="{{ $platform->platform }}"
                                                    width="32"
                                                    height="32"
                                                >
                                                <img
                                                    class="hidden h-auto max-h-5 w-5 dark:block"
                                                    src="{{ $image_dark_version }}"
                                                    alt="{{ $platform->platform }}"
                                                    width="32"
                                                    height="32"
                                                >
                                            </figure>
                                            <span class="block w-full truncate">
                                                {{ $platform->credentials['username'] }}
                                            </span>
                                        </x-button>
                                    @endforeach
                                </x-slot:dropdown>
                            </x-dropdown.dropdown>
                        </div>
                    </div>
                </div>

                <div class="flex md:justify-end">
                    <x-dropdown.dropdown
                        class:dropdown-dropdown="max-lg:!start-0 max-lg:!end-auto"
                        anchor="end"
                        offsetY="15px"
                        :teleport=false
                    >
                        <x-slot:trigger
                            class="whitespace-nowrap py-2 md:py-4"
                            variant="link"
                        >
                            @lang('Sort By'):
                            <span
                                class="capitalize"
                                x-text="sortLabel"
                            >
                                {{ $sort_options['created_at']['label'] }}
                            </span>
                            <x-tabler-caret-down-filled
                                class="size-4 opacity-60"
                                ::class="{ 'rotate-180': sort.sortDirection === 'asc' }"
                            />
                        </x-slot:trigger>

                        <x-slot:dropdown
                            class="min-w-[150px] overflow-hidden rounded-lg bg-background shadow-lg"
                        >
                            @foreach ($sort_options as $key => $sort_option)
                                <x-button
                                    class="w-full justify-start rounded-none text-start hover:transform-none [&.active]:underline"
                                    @click.prevent="sort.sortBy = '{{ $key }}'; sort.sortDirection = sort.sortBy !== '{{ $key }}' || sort.sortDirection === 'asc' ? 'desc' : 'asc'; filterPosts({platform_id: filters.platform_id})"
                                    variant="ghost"
                                    ::class="{
                                        'hidden': '{{ $key }}'
                                        === 'platform_id' && filters.platform_id !== '',
                                        'active': sort.sortBy === '{{ $key }}'
                                    }"
                                >
                                    {{ $sort_option['label'] }}
                                    <x-tabler-caret-down-filled
                                        class="hidden size-4 opacity-60"
                                        ::class="{ 'rotate-180': sort.sortDirection === 'asc', 'hidden': sort.sortBy !== '{{ $key }}' }"
                                    />
                                </x-button>
                            @endforeach
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </div>
            </div>

            @include('social-media-agent::posts.create-post-modal', [
                'platforms' => $platforms_with_image,
                'agents' => $agents ?? collect(),
            ])

            @include('social-media-agent::components.posts.list.posts-container', ['posts' => $posts, 'show_heading_and_arrows' => false])

            @include('social-media-agent::components.edit-post-sidedrawer', ['platforms' => $platforms])
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>

    @include('social-media-agent::components.posts.posts-script', [
        'platforms_with_image' => $platforms_with_image,
    ])
@endpush
