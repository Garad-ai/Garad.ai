@php
    $isOtherCategories = isset($category) && in_array($category->slug, ['ai_vision', 'ai_pdf', 'ai_chat_image']);
    $disable_actions = $app_is_demo && $isOtherCategories;

    $user_is_premium = false;
    $plan = auth()->user()?->relationPlan;
    if ($plan) {
        $planType = strtolower($plan->plan_type ?? 'all');
        if ($plan->plan_type === 'all' || $plan->plan_type === 'premium') {
            $user_is_premium = true;
        }
    }

    // $premium_features = \App\Models\OpenAIGenerator::query()->where('active', 1)->where('premium', 1)->get()->pluck('title')->toArray();
    $premium_features = [
        ['title' => 'Chat History', 'is_pro' => false],
        ['title' => 'Chat with Document', 'is_pro' => false],
        ['title' => 'Unlimited Credits', 'is_pro' => true],
        ['title' => 'Chatbot Training', 'is_pro' => true],
        ['title' => 'Voice Chat', 'is_pro' => true],
        ['title' => 'Access to All AI Tools', 'is_pro' => true],
    ];
    $ad_enabled = adsense('chat-pro-top-header-section-728x90');

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

    $typographyDefaults = [
        'body' => ['fontSize' => ['value' => 14, 'unit' => 'px'], 'lineHeight' => ['value' => 1.43, 'unit' => 'em']],
        'h1' => ['fontSize' => ['value' => 32, 'unit' => 'px'], 'lineHeight' => ['value' => 1.1, 'unit' => 'em']],
        'h2' => ['fontSize' => ['value' => 21, 'unit' => 'px'], 'lineHeight' => ['value' => 1.33, 'unit' => 'em']],
        'h3' => ['fontSize' => ['value' => 18, 'unit' => 'px'], 'lineHeight' => ['value' => 1.56, 'unit' => 'em']],
        'h4' => ['fontSize' => ['value' => 15, 'unit' => 'px'], 'lineHeight' => ['value' => 1.53, 'unit' => 'em']],
        'h5' => ['fontSize' => ['value' => 14, 'unit' => 'px'], 'lineHeight' => ['value' => 1.43, 'unit' => 'em']],
        'h6' => ['fontSize' => ['value' => 12, 'unit' => 'px'], 'lineHeight' => ['value' => 1.67, 'unit' => 'em']],
    ];
    $savedTypography = json_decode(setting('ai_chat_pro_typography', '{}'), true);
    $typography = array_replace_recursive($typographyDefaults, is_array($savedTypography) ? $savedTypography : []);

    $typoVal = fn($prop) => is_numeric($prop['value'] ?? null) ? $prop['value'] . ($prop['unit'] ?? 'px') : null;

    $typoCss = '';
    foreach (['body' => '', 'h1' => ' h1', 'h2' => ' h2', 'h3' => ' h3', 'h4' => ' h4', 'h5' => ' h5', 'h6' => ' h6'] as $el => $sel) {
        $fs = $typoVal($typography[$el]['fontSize'] ?? []);
        $lh = $typoVal($typography[$el]['lineHeight'] ?? []);
        $rules = ($fs ? "font-size:{$fs};" : '') . ($lh ? "line-height:{$lh};" : '');
        if ($rules) {
            $typoCss .= '.lqd-chat-v2 .chat-content' . $sel . '{' . $rules . '}';
        }
    }
@endphp

@extends('panel.layout.app', [
    'disable_tblr' => true,
    'layout_wide' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
    'body_class' => 'lqd-chat-v2',
])
@section('title', trans('Social Media Agent Chat'))

@if (filled($typoCss))
    @push('before-head-close')
        <style>
            {!! $typoCss !!}
        </style>
    @endpush
@endif

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.remove("focus-mode");
        })();
    </script>
@endpush

@if ($ad_enabled)
    <style>
        body {
            --ad-h: calc(90px + 0.5rem);
        }
    </style>
@endif

@push('css')
    <link
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
        rel="stylesheet"
    />

    <style>
        .chat-content-container:has(.social-media-agent-chat-post-card:only-child) {
            width: 100%;
        }

        .social-media-agent-chat-post-card {
            width: clamp(70%, 440px, 100%);
        }

        .social-media-agent-chat-post-card>p:first-of-type {
            display: none;
        }
    </style>
@endpush

@section('content')
    @if ($category->slug == 'ai_webchat' && count($list) === 0)
        <input
            id="createChatUrl"
            type="hidden"
            name="createChatUrl"
            value="/dashboard/user/openai/webchat/start-new-chat"
        />
    @endif
    <input
        id="openChatAreaContainerUrl"
        type="hidden"
        name="openChatAreaContainerUrl"
        value="@yield('openChatAreaContainerUrl', '/dashboard/user/openai/chat/open-chat-area-container')"
    />

    <input
        id="chat_open_ai_agent_id"
        type="hidden"
        name="chat_open_ai_agent_id"
        value="{{ $defaultAgent->id ?? '' }}"
    >

    <div x-data="socialMediaAgentPosts">
        <div
            class="relative lg:flex"
            x-data="chatsV2"
            @keydown.escape.window="sidebarHidden = true"
            @chat-created.window="onNewChatCreated"
        >
            <div
                class="pointer-events-none absolute -top-10 start-20 z-20 hidden h-52 w-[500px] rotate-12 rounded-full bg-gradient-to-r from-blue-500 to-green-400 opacity-[0.04] blur-3xl transition-all lg:block">
            </div>
            <div class="pointer-events-none absolute top-52 hidden size-52 -translate-x-2/3 bg-rose-500 opacity-10 blur-3xl transition-all dark:opacity-5 lg:block">
            </div>

            <div class="lqd-chat-v2-sidebar sticky top-0 z-20 hidden w-[--sidebar-w] shrink-0 flex-col border-e py-5 transition-all lg:flex">
                <div class="flex flex-col items-center gap-4">
                    <x-button
                        class="relative size-11 before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-10 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100 [&.active]:bg-primary [&.active]:text-primary-foreground [&.active]:outline-primary [&.active]:before:opacity-0"
                        id="show-recent-btn"
                        variant="outline"
                        size="none"
                        hover-variant="primary"
                        @click.prevent="$nextTick(() => toggleSidebarHidden())"
                        title="{{ __('Show Recent') }}"
                        ::class="{ 'active': !sidebarHidden }"
                    >
                        <x-tabler-circle-chevron-right
                            class="size-6 transition-all group-[&.active]:rotate-180"
                            stroke-width="1.5"
                        />
                    </x-button>

                    @if (view()->hasSection('chat_sidebar_actions'))
                        @yield('chat_sidebar_actions')
                    @else
                        @if (isset($category) && $category->slug == 'ai_pdf')
                            <x-button
                                class="lqd-upload-doc-trigger relative size-11 before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-50 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100"
                                variant="outline"
                                size="none"
                                hover-variant="primary"
                                href="javascript:void(0);"
                                title="{{ __('Upload Document') }}"
                                onclick="return $('#selectDocInput').click();"
                            >
                                <x-tabler-upload
                                    class="size-5"
                                    stroke-width="1.5"
                                />
                            </x-button>
                        @else
                            <x-button
                                class="lqd-new-chat-trigger relative size-11 before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-50 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100"
                                size="none"
                                hover-variant="primary"
                                variant="outline"
                                href="javascript:void(0);"
                                title="{{ __('New Chat') }}"
                                onclick="{!! $disable_actions
                                    ? 'return toastr.info(\'{{ __('This feature is disabled in Demo version.') }}\')'
                                    : (auth()->check()
                                        ? 'return startNewChat(\'{{ $category?->id }}\', \'{{ LaravelLocalization::getCurrentLocale() }}\', \'social-media-agent\')'
                                        : 'return window.location.reload();') !!}"
                            >
                                <x-tabler-plus class="size-5" />
                            </x-button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="grow px-2 md:px-5 lg:px-0">
                <div @class([
                    'lqd-chat-pro-header relative end-0 start-0 top-0 z-40 h-[--header-h] justify-between gap-3 bg-background px-3.5 transition-all md:px-5 lg:absolute lg:start-[--sidebar-w] lg:z-10 xl:px-8',
                    'hidden lg:flex' => Auth::check(),
                    'flex border-b max-md:-mx-2 md:max-lg:-mx-5' => !Auth::check(),
                ])>
                    <div class="hidden w-5/12 items-center gap-4 lg:flex">

                        @include('social-media-agent::chat.components.chat_category_dropdown')

                        <hr class="inline-block h-6 w-px shrink-0 bg-heading-foreground/10" />

                        <x-dropdown.dropdown
                            class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
                            offsetY="20px"
                            :teleport="false"
                        >
                            <x-slot:trigger>
                                <x-tabler-dots class="size-6" />
                            </x-slot:trigger>
                            <x-slot:dropdown
                                class="min-w-52 whitespace-nowrap"
                            >
                                <p
                                    class="m-0 translate-y-1 border-b border-heading-foreground/5 px-5 py-2 text-2xs font-medium text-heading-foreground/60 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[40ms]">
                                    {{ __('More Options') }}
                                </p>
                                <div class="p-2">
                                    <div
                                        class="group relative flex translate-y-1 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[80ms]"
                                        id="show_export_btns"
                                    >
                                        <x-button
                                            class="w-full cursor-default justify-start rounded-md px-3.5 py-2 text-2xs font-medium text-heading-foreground/60 hover:transform-none hover:bg-heading-foreground/[3%] hover:text-heading-foreground hover:shadow-none"
                                            variant="none"
                                        >
                                            {{ __('Export') }}
                                        </x-button>
                                        <div
                                            class="invisible absolute start-full top-0 flex min-w-44 translate-y-1 flex-col rounded-dropdown bg-dropdown-background p-2 opacity-0 shadow-lg shadow-black/5 transition-all group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100"
                                            id="export_btns"
                                        >
                                            <button
                                                class="chat-download flex items-center gap-2 rounded-md px-3.5 py-2 text-start text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                                                data-doc-type="pdf"
                                            >
                                                <x-tabler-file-type-pdf class="size-[18px] text-heading-foreground" />
                                                {{ __('PDF') }}
                                            </button>
                                            <button
                                                class="chat-download flex items-center gap-2 rounded-md px-3.5 py-2 text-start text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                                                data-doc-type="doc"
                                            >
                                                <x-tabler-brand-office class="size-[18px] text-heading-foreground" />
                                                {{ __('Word') }}
                                            </button>
                                            <button
                                                class="chat-download flex items-center gap-2 rounded-md px-3.5 py-2 text-start text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                                                data-doc-type="txt"
                                            >
                                                <x-tabler-file-text class="size-[18px] text-heading-foreground" />
                                                {{ __('Txt') }}
                                            </button>
                                        </div>
                                    </div>

                                    @auth
                                        <div
                                            class="translate-y-1 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[120ms]">
                                            <div
                                                class="relative cursor-pointer rounded-md px-3.5 py-2 text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground [&_.lqd-chat-share-modal-trigger]:absolute [&_.lqd-chat-share-modal-trigger]:inset-0 [&_.lqd-chat-share-modal-trigger]:z-2 [&_.lqd-chat-share-modal-trigger]:opacity-0">
                                                @includeFirst(['chat-share::share-button-include', 'panel.user.openai_chat.includes.share-button-include', 'vendor.empty'])
                                                <div class="lqd-btn inline-flex items-center first:hidden">
                                                    {{ __('Share') }}
                                                </div>
                                            </div>
                                        </div>
                                    @endauth
                                </div>
                            </x-slot:dropdown>
                        </x-dropdown.dropdown>
                    </div>

                    <div class="flex w-2/12 items-center gap-2 lg:justify-center">
                        <x-header-logo />
                    </div>

                    <div class="flex w-5/12 items-center justify-end gap-3.5 max-lg:grow max-sm:gap-2">

                        @if ((!$user_is_premium || $app_is_demo) && !auth()->check())
                            <x-modal
                                class:modal-content="w-[min(100%,845px)]"
                                class:modal-head="hidden"
                                class:modal-backdrop="bg-black/40 backdrop-blur-md"
                            >
                                <x-slot:trigger
                                    class="min-h-[38px] font-semibold text-heading-foreground hover:bg-primary hover:text-primary-foreground max-md:size-[38px] max-md:border max-md:p-0"
                                    variant="ghost"
                                    hover-variant="primary"
                                >
                                    <span class="max-md:hidden">
                                        {{ __('Upgrade') }}
                                    </span>
                                    <svg
                                        width="19"
                                        height="15"
                                        viewBox="0 0 19 15"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M7.75 7L6 5.075L6.525 4.2M4.25 0.875H14.75L17.375 5.25L9.9375 13.5625C9.88047 13.6207 9.8124 13.6669 9.73728 13.6985C9.66215 13.7301 9.58149 13.7463 9.5 13.7463C9.41851 13.7463 9.33785 13.7301 9.26272 13.6985C9.1876 13.6669 9.11953 13.6207 9.0625 13.5625L1.625 5.25L4.25 0.875Z"
                                        />
                                    </svg>
                                </x-slot:trigger>

                                <x-slot:modal>
                                    <div class="mb-6 flex items-center justify-between gap-3">
                                        <x-header-logo />

                                        <x-button
                                            class="size-7"
                                            @click.prevent="modalOpen = false"
                                            variant="none"
                                            size="none"
                                        >
                                            <x-tabler-x
                                                class="size-5"
                                                stroke-width="3"
                                            />
                                        </x-button>
                                    </div>

                                    <div class="mb-6 flex items-center gap-3 rounded-lg bg-yellow-400/20 px-7 py-2.5 text-yellow-800">
                                        <x-tabler-info-circle class="size-5" />

                                        <p class="m-0 font-medium underline">
                                            {{ __('You are out of trial credits.') }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-1 gap-7 lg:grid-cols-2 lg:gap-12">
                                        <div class="lg:pe-10">
                                            <h4 class="mb-5 text-[22px] font-bold leading-[1.22em]">
                                                {!! __('Upgrade your plan to unlock new AI capabilities.') !!}
                                            </h4>

                                            <p class="mb-5">
                                                {{ __('Register to access a world where creativity meets cutting-edge technology.') }}
                                            </p>

                                            <x-button
                                                class="w-full py-5 text-[18px] font-bold shadow-[0_14px_44px_rgba(0,0,0,0.07)] hover:shadow-2xl hover:shadow-primary/30 dark:hover:bg-primary"
                                                href="{{ LaravelLocalization::localizeUrl(route('dashboard.user.payment.subscription')) }}"
                                                variant="ghost-shadow"
                                            >
                                                <span
                                                    class="bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to bg-clip-text font-bold text-transparent group-hover:from-white group-hover:via-white group-hover:to-white/80"
                                                >
                                                    @lang('Upgrade Your Plan')
                                                </span>
                                            </x-button>
                                        </div>

                                        <div>
                                            <svg
                                                width="0"
                                                height="0"
                                                fill="none"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <defs>
                                                    <linearGradient
                                                        id="checkmarks-gradient"
                                                        x1="19"
                                                        y1="13.452"
                                                        x2="-7.62939e-06"
                                                        y2="19"
                                                        gradientUnits="userSpaceOnUse"
                                                    >
                                                        <stop stop-color="#3D9BFC" />
                                                        <stop
                                                            offset="0.208"
                                                            stop-color="#5F53EB"
                                                        />
                                                        <stop
                                                            offset="1"
                                                            stop-color="#70B4AF"
                                                        />
                                                    </linearGradient>
                                                </defs>
                                            </svg>

                                            <ul class="mb-3 flex flex-col gap-6 text-xs font-medium">
                                                @foreach ($premium_features as $feature)
                                                    <li class="flex items-center gap-2.5">
                                                        <svg
                                                            class="shrink-0"
                                                            width="19"
                                                            height="19"
                                                            viewBox="0 0 19 19"
                                                            fill="none"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                        >
                                                            <path
                                                                d="M8.08074 13.7538L14.8038 7.03075L13.75 5.977L8.08074 11.6463L5.23074 8.79625L4.17699 9.85L8.08074 13.7538ZM9.50174 19C8.18774 19 6.95266 18.7507 5.79649 18.252C4.64032 17.7533 3.63466 17.0766 2.77949 16.2218C1.92432 15.3669 1.24724 14.3617 0.748242 13.206C0.249409 12.0503 -7.62939e-06 10.8156 -7.62939e-06 9.50175C-7.62939e-06 8.18775 0.249325 6.95267 0.747992 5.7965C1.24666 4.64033 1.92341 3.63467 2.77824 2.7795C3.63307 1.92433 4.63832 1.24725 5.79399 0.74825C6.94966 0.249417 8.18441 0 9.49824 0C10.8123 0 12.0473 0.249333 13.2035 0.748C14.3597 1.24667 15.3653 1.92342 16.2205 2.77825C17.0757 3.63308 17.7528 4.63833 18.2518 5.794C18.7506 6.94967 19 8.18442 19 9.49825C19 10.8123 18.7507 12.0473 18.252 13.2035C17.7533 14.3597 17.0766 15.3653 16.2218 16.2205C15.3669 17.0757 14.3617 17.7528 13.206 18.2518C12.0503 18.7506 10.8156 19 9.50174 19ZM9.49999 17.5C11.7333 17.5 13.625 16.725 15.175 15.175C16.725 13.625 17.5 11.7333 17.5 9.5C17.5 7.26667 16.725 5.375 15.175 3.825C13.625 2.275 11.7333 1.5 9.49999 1.5C7.26666 1.5 5.37499 2.275 3.82499 3.825C2.27499 5.375 1.49999 7.26667 1.49999 9.5C1.49999 11.7333 2.27499 13.625 3.82499 15.175C5.37499 16.725 7.26666 17.5 9.49999 17.5Z"
                                                                fill="url(#checkmarks-gradient)"
                                                            />
                                                        </svg>
                                                        {{ __($feature['title']) }}
                                                        @if ($feature['is_pro'])
                                                            <span class="-my-2 inline-flex items-center rounded-full border px-3 py-2 text-2xs/none">
                                                                <span class="bg-gradient-to-r from-gradient-from via-gradient-via to-gradient-to bg-clip-text text-transparent">
                                                                    {{ __('Pro') }}
                                                                </span>
                                                            </span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </x-slot:modal>
                            </x-modal>
                        @endif

                        @if (Theme::getSetting('dashboard.supportedColorSchemes') === 'all')
                            <x-light-dark-switch
                                class="size-[38px] border text-heading-foreground hover:border-primary hover:bg-primary hover:text-primary-foreground hover:outline-primary"
                            />
                        @endif

                        @auth
                            <x-button
                                class="size-[38px] border text-heading-foreground hover:transform-none hover:border-primary hover:bg-primary hover:text-primary-foreground hover:shadow-none hover:outline-primary"
                                size="none"
                                href="{{ route('dashboard.user.index') }}"
                                title="{{ __('Dashboard') }}"
                                variant="outline"
                            >
                                <x-tabler-grid-dots class="size-[18px]" />
                            </x-button>

                            {{-- User menu --}}
                            <x-user-dropdown
                                class:trigger="size-[38px] hover:outline-primary text-heading-foreground hover:bg-primary hover:text-primary-foreground hover:border-primary border"
                            >
                                <x-slot:trigger>
                                    <x-tabler-user-circle
                                        class="size-6"
                                        stroke-width="1.5"
                                    />
                                </x-slot:trigger>
                            </x-user-dropdown>
                        @else
                            <x-button
                                class="hidden h-[38px] rounded-lg outline lg:inline-flex"
                                variant="outline"
                                hover-variant="primary"
                                href="{{ route('login') }}"
                            >
                                {!! __($fSetting->sign_in) !!}
                            </x-button>
                            <x-button
                                class="h-[38px] rounded-lg outline"
                                variant="outline"
                                hover-variant="primary"
                                href="{{ route('register') }}"
                            >
                                {!! __($fSetting->join_hub) !!}
                            </x-button>
                        @endauth
                    </div>
                </div>

                <div class="max-lg:py-5">
                    <div
                        id="user_chat_area"
                        @class([
                            'chats-wrap group/chats-wrap relative lg:h-[calc(100vh-var(--ad-h,0px))]',
                            'max-md:h-[calc(100vh-var(--header-height,var(--header-h,0px))-var(--bottom-menu-height,0px)-var(--ad-h,0px)-2.5rem)] md:max-lg:h-[75vh] md:grid md:max-lg:grid-flow-col md:max-lg:[grid-template-columns:30%_70%]' => Auth::check(),
                            'max-lg:h-[calc(100vh-2.5rem-var(--header-h))]' => !Auth::check(),
                            'conversation-started' => count($chat->messages ?? []) > 1,
                            'conversation-not-started' => count($chat->messages ?? []) <= 1,
                        ])
                        :class="{ 'chats-sidebar-hidden': (($store.focusMode.active && sidebarHidden) || sidebarForceHidden) }"
                    >
                        <div
                            @class([
                                'chats-sidebar-wrap relative flex h-[inherit] w-full transition-all max-md:absolute max-md:start-0 max-md:top-20 max-md:z-10 max-md:h-0 max-md:overflow-hidden max-md:bg-background lg:fixed lg:start-0 lg:z-10 lg:grid lg:h-screen lg:w-[405px] lg:grid-rows-5 lg:flex-wrap lg:bg-background lg:ps-[--sidebar-w] lg:duration-300 max-md:[&.active]:h-[calc(100%-80px)]',
                                'md:max-lg:hidden' => !Auth::check(),
                            ])
                            :class="{
                                'active': mobileSidebarShow || !sidebarHidden,
                                'lg:hidden': (($store.focusMode.active && sidebarHidden) || sidebarForceHidden)
                            }"
                            @click.outside="(e) => {return !sidebarHidden && !isShowRecent(e?.target) && (sidebarHidden = true);}"
                        >
                            @if (view()->hasSection('chat_sidebar'))
                                @yield('chat_sidebar')
                            @else
                                @include('panel.user.openai_chat.components.chat_sidebar', [
                                    'website_url' => 'social-media-agent',
                                ])
                            @endif

                            <div class="chats-sidebar-links mt-auto hidden w-full flex-col items-start gap-y-5 px-7 pb-12 lg:flex">
                                <x-button
                                    class="text-4xs uppercase tracking-widest text-heading-foreground/50 hover:text-heading-foreground"
                                    href="{{ url('/privacy-policy') }}"
                                    variant="link"
                                >
                                    <x-tabler-chevron-right class="size-3.5 transition-all group-hover:translate-x-0.5" />
                                    {{ __('Privacy Policy') }}
                                </x-button>

                                <x-button
                                    class="text-4xs uppercase tracking-widest text-heading-foreground/50 hover:text-heading-foreground"
                                    href="{{ url('/terms') }}"
                                    variant="link"
                                >
                                    <x-tabler-chevron-right class="size-3.5 transition-all group-hover:translate-x-0.5" />
                                    {{ __('Need Help?') }}
                                </x-button>
                            </div>
                        </div>

                        <x-card
                            class="conversation-area-wrap relative flex h-[inherit] grow flex-col md:rounded-s-none lg:w-full lg:border-none"
                            class:body="h-full rounded-b-[inherit] rounded-t-[inherit]"
                            id="load_chat_area_container"
                            ::class="sidebarForceHidden ? 'md:max-lg:col-span-2' : ''"
                            size="none"
                        >
                            <x-slot:head
                                class="!border-none !p-0"
                            >
                                <x-button
                                    class="chats-sidebar-expander absolute start-0 top-5 z-[99] hidden size-10 -translate-x-1/2 place-content-center rounded-full bg-background text-heading-foreground shadow-md shadow-heading-foreground/10 hover:translate-y-0 hover:scale-105 dark:bg-background lg:group-[&.focus-mode]/body:inline-grid"
                                    variant="ghost-shadow"
                                    size="none"
                                    href="#"
                                    @click.prevent="if ( $store.focusMode.active ) { toggleSidebarHidden() }"
                                >
                                    <span
                                        class="inline-block transition-transform"
                                        :class="{ 'rotate-180': sidebarHidden }"
                                    >
                                        <x-tabler-chevron-left class="rtl:-scale-x-1 size-4" />
                                    </span>
                                </x-button>
                            </x-slot:head>
                            @if ($chat != null)
                                @if (view()->hasSection('chat_area_container'))
                                    @yield('chat_area_container')
                                    {{--                            @elseif (\App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1)) --}}
                                    {{--                                @include('canvas::includes.chat_area_container') --}}
                                @else
                                    @include('social-media-agent::chat.includes.chat_area_container')
                                @endif
                            @else
                                <div
                                    class="conversation-area flex h-[inherit] grow flex-col justify-between overflow-y-auto rounded-b-[inherit] rounded-t-[inherit] max-md:max-h-full">
                                </div>
                            @endif
                        </x-card>
                    </div>
                </div>
            </div>
        </div>

        @include('social-media-agent::components.edit-post-sidedrawer', ['platforms' => $platforms])
    </div>

    <template id="chat_user_image_bubble">
        <div class="lqd-chat-image-bubble mb-2 flex !w-auto max-w-[50%] flex-row-reverse content-end gap-2 !px-3 !py-2.5 last:mb-0 lg:ms-auto lg:justify-self-end">
            <a
                class="flex items-center gap-1.5 underline underline-offset-2"
                data-fslightbox="gallery"
                data-type="image"
                href="#"
                target="_blank"
            >
                <svg
                    class="shrink-0 opacity-50"
                    width="15"
                    height="19"
                    viewBox="0 0 15 19"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M1.66667 18.5C1.20833 18.5 0.815972 18.3042 0.489583 17.9125C0.163194 17.5208 0 17.05 0 16.5V2.5C0 1.95 0.163194 1.47917 0.489583 1.0875C0.815972 0.695833 1.20833 0.5 1.66667 0.5H10L15 6.5V16.5C15 17.05 14.8368 17.5208 14.5104 17.9125C14.184 18.3042 13.7917 18.5 13.3333 18.5H1.66667ZM3.33333 14.5H11.6667V12.5H3.33333V14.5ZM3.33333 10.5H11.6667V8.5H3.33333V10.5ZM3.33333 6.5H9.16667V4.5H3.33333V6.5Z"
                    />
                </svg>
                <img
                    class="img-content rounded-3xl"
                    loading="lazy"
                />
            </a>
        </div>
    </template>

    <template id="chat_bot_image_bubble">
        <div class="lqd-chat-image-bubble mb-2 flex content-end gap-2 lg:ms-auto">
            <div class="mb-2 flex w-4/5 justify-start rounded-3xl text-heading-foreground dark:text-heading-foreground md:w-1/2">
                <a
                    data-fslightbox="gallery"
                    data-type="image"
                    href="#"
                    target="_blank"
                >
                    <img
                        class="img-content rounded-3xl"
                        loading="lazy"
                    />
                </a>
            </div>
        </div>
    </template>

    <template id="chat_user_bubble">
        <div class="lqd-chat-user-bubble mb-2 flex flex-row-reverse content-end gap-2 lg:ms-auto">
            <div class="lqd-chat-sender flex items-center gap-2.5">
                <span
                    class="lqd-chat-avatar inline-block size-6 shrink-0 rounded-full bg-cover bg-center"
                    style="background-image: url({{ url(Auth::user()?->avatar ?? '', true) }})"
                ></span>
                <span class="lqd-chat-sender-name">
                    @lang('You')
                </span>
            </div>
            <div
                class="chat-content-container group relative max-w-[calc(100%-64px)] rounded-[2em] bg-secondary text-secondary-foreground dark:bg-zinc-700 dark:text-primary-foreground">
                <div class="chat-content px-5 py-3.5 max-md:break-words"></div>
                <div
                    class="lqd-chat-actions-wrap pointer-events-auto invisible absolute -start-5 bottom-0 flex flex-col gap-2 leading-5 opacity-0 transition-all group-hover:!visible group-hover:!opacity-100">
                    <div class="lqd-clipboard-copy-wrap group/copy-wrap flex flex-col gap-2 transition-all">
                        <button
                            class="lqd-clipboard-copy group/btn relative inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110"
                            data-copy-options='{ "content": ".chat-content", "contentIn": "<.chat-content-container" }'
                            title="{{ __('Copy to clipboard') }}"
                        >
                            <span
                                class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                            >
                                {{ __('Copy to clipboard') }}
                            </span>
                            <x-tabler-copy class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template id="chat_ai_bubble">
        <div
            class="lqd-chat-ai-bubble group mb-2 flex content-start items-start gap-2"
            data-message-id=""
            data-title=""
        >
            <div class="lqd-chat-sender flex items-center gap-2.5">
                <span
                    class="lqd-chat-avatar inline-block size-12 shrink-0 rounded-full bg-cover bg-center"
                    style="background-image: url('{{ !empty($chat->category->image) ? custom_theme_url($chat->category->image, true) : url(custom_theme_url('/assets/img/auth/default-avatar.png')) }}')"
                ></span>
                <span class="lqd-chat-sender-name">
                    {{ __($chat?->category?->name ?? 'AI Assistant') }}
                </span>
            </div>
            <div class="chat-content-container relative min-h-12 min-w-12 max-w-[calc(100%-64px)] rounded-3xl text-heading-foreground dark:text-heading-foreground">
                <div class="inline-flex min-h-11 max-w-full items-center rounded-full font-medium leading-none transition-all">
                    <div class="lqd-typing relative inline-flex aspect-square w-12 shrink-0 items-center justify-center overflow-hidden">
                        <div class="lqd-typing-dots flex h-5 shrink-0 items-center justify-center gap-1">
                            <span class="lqd-typing-dot inline-block size-1 shrink-0 rounded-full bg-current opacity-40 ![animation-delay:0.2s]"></span>
                            <span class="lqd-typing-dot inline-block size-1 shrink-0 rounded-full bg-current opacity-60 ![animation-delay:0.3s]"></span>
                            <span class="lqd-typing-dot inline-block size-1 shrink-0 rounded-full bg-current opacity-80 ![animation-delay:0.4s]"></span>
                        </div>
                    </div>
                    <div
                        class="chat-content prose relative w-full max-w-none px-5 py-3.5 indent-0 font-[inherit] text-xs font-normal text-current [word-break:break-word] empty:hidden">
                    </div>

                    <div
                        class="lqd-chat-actions-wrap pointer-events-auto invisible absolute -end-5 bottom-0 flex flex-col gap-2 opacity-0 transition-all group-hover:!visible group-hover:!opacity-100">
                        <div class="lqd-clipboard-copy-wrap group/copy-wrap flex flex-col gap-2 transition-all">
                            <button
                                class="lqd-clipboard-copy group/btn relative inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110"
                                data-copy-options='{ "content": ".chat-content", "contentIn": "<.chat-content-container" }'
                                title="{{ __('Copy to clipboard') }}"
                            >
                                <span
                                    class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                                >
                                    {{ __('Copy to clipboard') }}
                                </span>
                                <x-tabler-copy class="size-4" />
                            </button>

                            @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1))
                                <button
                                    class="lqd-chat-bubble-canvas-trigger group/btn inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110 group-[&.loading]:pointer-events-none group-[&.streaming-on]:pointer-events-none group-[&.loading]:opacity-50 group-[&.streaming-on]:opacity-50"
                                    type="button"
                                    @click.prevent="setCanvasActive(true);"
                                >
                                    <span
                                        class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                                    >
                                        {{ __('Open in Canvas') }}
                                    </span>
                                    <x-tabler-edit class="size-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1))
        <template id="canvas_edit_btn_block">
            <div class="mb-3 w-full">
                <button
                    class="lqd-chat-bubble-canvas-trigger group/btn flex items-center gap-2 rounded-md border px-3 py-2 text-2xs font-medium transition-all hover:border-foreground hover:bg-foreground hover:text-background group-[&.loading]:pointer-events-none group-[&.streaming-on]:pointer-events-none group-[&.loading]:opacity-50 group-[&.streaming-on]:opacity-50"
                    type="button"
                    @click.prevent="setCanvasActive(true);"
                >
                    <span
                        class="inline-grid size-9 place-items-center rounded-full border-none bg-surface-background p-0 text-foreground shadow-lg shadow-black/5 transition-all group-hover/btn:-translate-y-[2px] group-hover/btn:scale-110 group-[&.loading]:scale-90 group-[&.streaming-on]:scale-90"
                    >
                        <x-tabler-pencil class="size-4" />
                    </span>
                    {{ __('Open in Canvas') }}
                </button>
            </div>
        </template>
    @endif

    <template id="prompt_image">
        <div class="relative">
            <button
                class="prompt_image_close absolute -end-2 -top-2 flex size-5 items-center justify-center rounded-full bg-red-600 text-white"
                onclick="if ( document.getElementById('mainupscale_src') ) { document.getElementById('mainupscale_src').style.display = 'block'; }"
            >
                <x-tabler-x class="size-4" />
            </button>
            <img
                class="m-0 aspect-square w-20 rounded-xl object-cover object-center"
                src=""
            />
        </div>
    </template>

    <template id="prompt_pdf">
        <div class="relative ms-2 flex items-center gap-3 self-center rounded-full bg-foreground/5 px-3 py-2.5 text-sm/tight text-heading-foreground">
            <a
                class="flex items-center gap-1.5"
                href="#"
                target="_blank"
            >
                <svg
                    class="shrink-0 opacity-50"
                    width="15"
                    height="19"
                    viewBox="0 0 15 19"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M1.66667 18.5C1.20833 18.5 0.815972 18.3042 0.489583 17.9125C0.163194 17.5208 0 17.05 0 16.5V2.5C0 1.95 0.163194 1.47917 0.489583 1.0875C0.815972 0.695833 1.20833 0.5 1.66667 0.5H10L15 6.5V16.5C15 17.05 14.8368 17.5208 14.5104 17.9125C14.184 18.3042 13.7917 18.5 13.3333 18.5H1.66667ZM3.33333 14.5H11.6667V12.5H3.33333V14.5ZM3.33333 10.5H11.6667V8.5H3.33333V10.5ZM3.33333 6.5H9.16667V4.5H3.33333V6.5Z"
                    />
                </svg>
                <span class="inline-block max-w-52 truncate"></span>
            </a>
            <button
                class="prompt_image_close shrink-0"
                onclick="if ( document.getElementById('mainupscale_src') ) { document.getElementById('mainupscale_src').style.display = 'block'; }"
            >
                <x-tabler-x class="size-4" />
            </button>
        </div>
    </template>

    <template id="prompt_image_add_btn">
        <div class="promt_image_btn">
            <button class="aspect-square w-20 rounded-xl bg-foreground/10 text-2xl font-light transition-all hover:bg-emerald-500 hover:text-white">+
            </button>
        </div>
    </template>

    <template id="chat_pdf">
        <div class="mb-2 mr-[30px] flex flex-row-reverse content-end gap-[8px] lg:ms-auto">
            <svg
                width="36"
                height="36"
                viewBox="0 0 36 36"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M23.7762 0H5.11921C4.59978 0 4.17871 0.421071 4.17871 1.23814V35.3571C4.17871 35.5789 4.59978 36 5.11921 36H30.8811C31.4005 36 31.8216 35.5789 31.8216 35.3571V8.343C31.8216 7.89557 31.7618 7.75157 31.6564 7.6455L24.1761 0.165214C24.07 0.0597857 23.926 0 23.7762 0Z"
                    fill="#E9E9E0"
                />
                <path
                    d="M24.1074 0.0970459V7.71426H31.7246L24.1074 0.0970459Z"
                    fill="#D9D7CA"
                />
                <path
                    d="M12.5445 21.4226C12.3208 21.4226 12.1061 21.35 11.9229 21.2131C11.2537 20.711 11.1637 20.1523 11.2061 19.7718C11.3231 18.7252 12.6172 17.6298 15.0536 16.5138C16.0205 14.3949 16.9404 11.7843 17.4888 9.60306C16.8472 8.20677 16.2236 6.3952 16.6781 5.33256C16.8375 4.96035 17.0362 4.67492 17.4071 4.55149C17.5537 4.50263 17.924 4.44092 18.0603 4.44092C18.3843 4.44092 18.669 4.85813 18.8709 5.11527C19.0605 5.35699 19.4906 5.86935 18.6311 9.48799C19.4977 11.2777 20.7255 13.1008 21.902 14.3493C22.7448 14.1969 23.4699 14.1191 24.0607 14.1191C25.0674 14.1191 25.6775 14.3538 25.9263 14.8372C26.132 15.2371 26.0478 15.7044 25.6755 16.2258C25.3175 16.7266 24.8238 16.9914 24.2484 16.9914C23.4667 16.9914 22.5564 16.4977 21.5413 15.5225C19.7175 15.9037 17.5878 16.5838 15.8662 17.3366C15.3288 18.4771 14.8138 19.3957 14.3343 20.0694C13.6753 20.9919 13.107 21.4226 12.5445 21.4226ZM14.2558 18.1273C12.882 18.8994 12.3221 19.5339 12.2816 19.8913C12.2752 19.9505 12.2578 20.1061 12.5587 20.3362C12.6545 20.306 13.2138 20.0508 14.2558 18.1273ZM23.0225 15.2718C23.5464 15.6748 23.6743 15.8786 24.017 15.8786C24.1674 15.8786 24.5962 15.8722 24.7948 15.5951C24.8906 15.4608 24.9279 15.3746 24.9427 15.3283C24.8636 15.2866 24.7588 15.2017 24.1873 15.2017C23.8627 15.2023 23.4545 15.2165 23.0225 15.2718ZM18.2203 11.0405C17.7607 12.6309 17.1538 14.348 16.5013 15.9031C17.8449 15.3817 19.3055 14.9266 20.6773 14.6045C19.8095 13.5965 18.9423 12.3378 18.2203 11.0405ZM17.8301 5.60063C17.7671 5.62185 16.9751 6.73013 17.8918 7.66806C18.5019 6.30842 17.8578 5.59163 17.8301 5.60063Z"
                    fill="#CC4B4C"
                />
                <path
                    d="M30.8811 36H5.11921C4.59978 36 4.17871 35.5789 4.17871 35.0595V25.0714H31.8216V35.0595C31.8216 35.5789 31.4005 36 30.8811 36Z"
                    fill="#CC4B4C"
                />
                <path
                    d="M11.176 34.0714H10.1211V27.594H11.9841C12.2592 27.594 12.5318 27.6377 12.8012 27.7258C13.0705 27.8139 13.3122 27.9456 13.5263 28.1211C13.7404 28.2966 13.9133 28.5094 14.0451 28.7582C14.1769 29.007 14.2431 29.2866 14.2431 29.5978C14.2431 29.9263 14.1872 30.2233 14.076 30.4901C13.9647 30.7569 13.8092 30.9812 13.6099 31.1625C13.4106 31.3438 13.1702 31.4846 12.8892 31.5842C12.6083 31.6839 12.2972 31.7334 11.9577 31.7334H11.1754L11.176 34.0714ZM11.176 28.3937V30.96H12.1429C12.2715 30.96 12.3987 30.9381 12.5254 30.8938C12.6514 30.8501 12.7671 30.7781 12.8725 30.6784C12.978 30.5788 13.0628 30.4399 13.1271 30.2612C13.1914 30.0825 13.2235 29.8614 13.2235 29.5978C13.2235 29.4924 13.2087 29.3702 13.1798 29.2333C13.1502 29.0957 13.0905 28.9639 12.9998 28.8379C12.9085 28.7119 12.7812 28.6065 12.6173 28.5216C12.4534 28.4368 12.2361 28.3944 11.9667 28.3944L11.176 28.3937Z"
                    fill="white"
                />
                <path
                    d="M20.7121 30.6527C20.7121 31.1856 20.6549 31.6414 20.5404 32.0194C20.426 32.3974 20.2814 32.7137 20.1052 32.9689C19.9291 33.2241 19.7317 33.4247 19.5119 33.5713C19.292 33.7179 19.0799 33.8271 18.8748 33.9011C18.6697 33.9744 18.482 34.0213 18.3123 34.0419C18.1426 34.0611 18.0166 34.0714 17.9343 34.0714H15.4824V27.594H17.4335C17.9786 27.594 18.4576 27.6808 18.8703 27.8531C19.283 28.0254 19.6263 28.2561 19.8989 28.5429C20.1714 28.8296 20.3746 29.1568 20.5096 29.5226C20.6446 29.889 20.7121 30.2657 20.7121 30.6527ZM17.5833 33.2981C18.2981 33.2981 18.8137 33.0699 19.13 32.6128C19.4463 32.1557 19.6044 31.4936 19.6044 30.6264C19.6044 30.357 19.5723 30.0902 19.508 29.8266C19.4431 29.5631 19.319 29.3246 19.1345 29.1105C18.95 28.8964 18.6993 28.7235 18.383 28.5917C18.0667 28.4599 17.6566 28.3937 17.1526 28.3937H16.5374V33.2981H17.5833Z"
                    fill="white"
                />
                <path
                    d="M23.3135 28.3937V30.4329H26.0206V31.1535H23.3135V34.0714H22.2412V27.594H26.2925V28.3937H23.3135Z"
                    fill="white"
                />
            </svg>
        </div>
        <div class="mb-2 mr-[30px] flex flex-row-reverse content-end gap-[8px] lg:ms-auto">
            <a
                class="pdfpath flex"
                href=""
                target="_blank"
            >
                <label class="pdfname"></label>
                <svg
                    width="17"
                    height="18"
                    viewBox="0 0 17 18"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <mask
                        id="mask0_3243_893"
                        style="mask-type:alpha"
                        maskUnits="userSpaceOnUse"
                        x="0"
                        y="0"
                        width="17"
                        height="18"
                    >
                        <rect
                            y="0.43103"
                            width="17"
                            height="17"
                            fill="#D9D9D9"
                        />
                    </mask>
                    <g mask="url(#mask0_3243_893)">
                        <path
                            d="M4.45937 12.9289L3.71973 12.1892L10.69 5.21212H4.35314V4.14966H12.4989V12.2955H11.4365V5.95858L4.45937 12.9289Z"
                            fill="#1C1B1F"
                        />
                    </g>
                </svg>
            </a>
        </div>
    </template>

    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('multi-model'))
        <template id="multi-model-response-head">
            <div class="multi-model-response-head mb-3 hidden w-full items-center gap-4">
                {{-- blade-formatter-disable --}}
                <svg class="shrink-0" width="15" height="14" viewBox="0 0 15 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg" > <path d="M4.76586 11.495L5.08728 11.4297C5.1773 11.4117 5.25828 11.363 5.31647 11.292C5.37466 11.221 5.40645 11.132 5.40645 11.0402C5.40645 10.9484 5.37466 10.8594 5.31647 10.7884C5.25828 10.7174 5.1773 10.6688 5.08728 10.6507L4.76586 10.5854C4.36954 10.505 4.00569 10.3097 3.71974 10.0237C3.43379 9.7378 3.23842 9.37397 3.15801 8.97767L3.09275 8.65626C3.07471 8.56625 3.02605 8.48525 2.95503 8.42706C2.88402 8.36888 2.79504 8.3371 2.70323 8.3371C2.61142 8.3371 2.52245 8.36888 2.45143 8.42706C2.38042 8.48525 2.33175 8.56625 2.3137 8.65626L2.24844 8.97767C2.16804 9.37397 1.97266 9.7378 1.68671 10.0237C1.40076 10.3097 1.03692 10.505 0.640595 10.5854L0.319189 10.6507C0.229171 10.6688 0.148173 10.7174 0.0899825 10.7884C0.0317923 10.8594 0 10.9484 0 11.0402C0 11.132 0.0317923 11.221 0.0899825 11.292C0.148173 11.363 0.229171 11.4117 0.319189 11.4297L0.640595 11.495C1.03692 11.5754 1.40076 11.7708 1.68671 12.0567C1.97266 12.3426 2.16804 12.7065 2.24844 13.1028L2.3137 13.4242C2.33175 13.5142 2.38042 13.5952 2.45143 13.6534C2.52245 13.7116 2.61142 13.7433 2.70323 13.7433C2.79504 13.7433 2.88402 13.7116 2.95503 13.6534C3.02605 13.5952 3.07471 13.5142 3.09275 13.4242L3.15801 13.1028C3.23842 12.7065 3.43379 12.3426 3.71974 12.0567C4.00569 11.7708 4.36954 11.5754 4.76586 11.495Z" /> <path d="M12.5567 5.67479L13.7396 5.43497C13.8576 5.41083 13.9637 5.34666 14.0399 5.25332C14.1161 5.15998 14.1577 5.04318 14.1577 4.92269C14.1577 4.80221 14.1161 4.68542 14.0399 4.59208C13.9637 4.49873 13.8576 4.43457 13.7396 4.41042L12.5567 4.1706C11.9869 4.05496 11.4637 3.77405 11.0526 3.36291C10.6414 2.95178 10.3605 2.42865 10.2449 1.85884L10.005 0.67604C9.98131 0.557759 9.91735 0.451342 9.82403 0.374886C9.73071 0.29843 9.61379 0.256653 9.49315 0.256653C9.37251 0.256653 9.25559 0.29843 9.16228 0.374886C9.06896 0.451342 9.00499 0.557759 8.98126 0.67604L8.74143 1.85884C8.62589 2.4287 8.345 2.95188 7.93384 3.36303C7.52267 3.77418 6.99947 4.05506 6.42959 4.1706L5.24674 4.41042C5.12869 4.43457 5.02259 4.49873 4.9464 4.59208C4.87022 4.68542 4.8286 4.80221 4.8286 4.92269C4.8286 5.04318 4.87022 5.15998 4.9464 5.25332C5.02259 5.34666 5.12869 5.41083 5.24674 5.43497L6.42959 5.67479C6.99947 5.79032 7.52267 6.07121 7.93384 6.48236C8.345 6.89351 8.62589 7.4167 8.74143 7.98656L8.98126 9.16936C9.00499 9.28764 9.06896 9.39404 9.16228 9.4705C9.25559 9.54695 9.37251 9.58874 9.49315 9.58874C9.61379 9.58874 9.73071 9.54695 9.82403 9.4705C9.91735 9.39404 9.98131 9.28764 10.005 9.16936L10.2449 7.98656C10.3605 7.41674 10.6414 6.89361 11.0526 6.48248C11.4637 6.07135 11.9869 5.79042 12.5567 5.67479Z" /> </svg>
				{{-- blade-formatter-enable --}}

                <span class="multi-model-response-name inline-block max-w-full truncate text-[12px] font-medium underline underline-offset-4"></span>

                {{-- <div class="multi-model-response-actions contents shrink-0">
                    <x-button
                        class="multi-model-response-regenerate size-8 shrink-0 rounded-full p-0"
                        size="none"
                        variant="outline"
                    >
                        <x-tabler-rotate class="size-4" />
                    </x-button>
                </div> --}}
            </div>
        </template>

        <template id="multi-model-response-foot">
            <div class="multi-model-response-foot hidden">
                <x-button
                    class="multi-model-response-accept mt-3 p-0 text-[12px] underline underline-offset-4"
                    size="none"
                    variant="none"
                >
                    <x-tabler-thumb-up class="size-4" />
                    {{ __('I prefer this response') }}
                </x-button>
            </div>
        </template>
    @endif

    @include('social-media-agent::chat.components.chat-post-card-template')

    <input
        id="assistant"
        type="hidden"
        value="{{ $category->assistant }}"
    />
    <input
        id="guest_id"
        type="hidden"
        value="{{ $apiUrl }}"
    >
    <input
        id="guest_search"
        type="hidden"
        value="{{ $apiSearch }}"
    >
    <input
        id="guest_search_id"
        type="hidden"
        value="{{ $apiSearchId }}"
    >
    <input
        id="guest_event_id"
        type="hidden"
        value="{{ $apikeyPart1 }}"
    >
    <input
        id="guest_look_id"
        type="hidden"
        value="{{ $apikeyPart2 }}"
    >
    <input
        id="guest_product_id"
        type="hidden"
        value="{{ $apikeyPart3 }}"
    >
    @if ($category->prompt_prefix != null)
        <input
            id="prompt_prefix"
            type="hidden"
            value="{{ $category->prompt_prefix }} you will now play a character and respond as that character (You will never break character). Your name is {{ $category->human_name }} but do not introduce by yourself as well as greetings."
        >
    @else
        <input
            id="prompt_prefix"
            type="hidden"
            value=""
        >
    @endif
@endsection

@push('script')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/prism/prism.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/katex/katex.min.css') }}"
    >

    <script src="{{ custom_theme_url('/assets/libs/prism/prism.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it-container.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it-attrs.browser.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/html2pdf/html2pdf.bundle.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/turndown.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/katex/katex.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/vscode-markdown-it-katex/index.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/fslightbox/fslightbox.js') }}"></script>

    @include('panel.user.openai_chat.components.chat_js')

    @include('social-media-agent::components.posts.posts-script', [
        'platforms_with_image' => $platforms_with_image,
        'total_posts_count' => 0,
        'scheduled_posts_count' => 0,
        'pending_posts_count' => 0,
    ])

    @if ($category?->slug !== 'ai_pdf')
        <script>
            (() => {
                    const promptEl = document.getElementById('prompt');
                    if (!promptEl) return;

                    const hasImageInClipboard = (items) => {
                        if (!items?.length) return false;
                        for (const item of items) {
                            if (item.kind === 'file' && item.type.startsWith('image/')) return true;
                        }
                        return false;
                    };

                    @guest
                    promptEl.addEventListener('paste', (e) => {
                        if (!hasImageInClipboard(e.clipboardData?.items)) return;
                        e.preventDefault();
                        toastr.warning('{{ __('Login to upload a document or image.') }}');
                    });
                @else
                    @if ($app_is_demo)
                        promptEl.addEventListener('paste', (e) => {
                            if (!hasImageInClipboard(e.clipboardData?.items)) return;
                            e.preventDefault();
                            toastr.info('{{ __('This feature is disabled in Demo version.') }}');
                        });
                    @else
                        promptEl.addEventListener('paste', (e) => {
                            const items = e.clipboardData?.items;
                            if (!items?.length) return;

                            const imageFiles = [];
                            for (const item of items) {
                                if (item.kind === 'file' && item.type.startsWith('image/')) {
                                    const file = item.getAsFile();
                                    if (file) imageFiles.push(file);
                                }
                            }
                            if (!imageFiles.length) return;

                            e.preventDefault();

                            imageFiles.forEach((file, idx) => {
                                const reader = new FileReader();
                                reader.onload = (ev) => {
                                    const name = file.name && file.name !== 'image.png' ?
                                        file.name :
                                        `pasted-image-${Date.now()}-${idx}.${(file.type.split('/')[1] || 'png')}`;
                                    if (typeof addFileToChat === 'function') {
                                        addFileToChat({
                                            data: ev.target.result,
                                            name
                                        });
                                    }
                                };
                                reader.readAsDataURL(file);
                            });
                        });
                    @endif
                @endguest
            })();
        </script>
    @endif

    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('chatsV2', () => ({
                    mobileOptionsShow: false,
                    mobileSidebarShow: false,
                    mobileHeaderMoreOptionsShow: false,
                    sidebarHidden: true,
                    sidebarForceHidden: false,
                    realtimeStatus: 'idle',
                    promptLibraryShow: false,
                    promptFilter: 'all',
                    searchPromptStr: '',
                    prompt: '',
                    selectedTools: [],

                    init() {
                        Alpine.store('chatsV2', this);
                    },
                    togglePromptLibraryShow() {
                        this.promptLibraryShow = !this.promptLibraryShow
                    },
                    changePromptFilter(filter) {
                        filter !== this.promptFilter && (this.promptFilter = filter)
                    },
                    setSearchPromptStr(str) {
                        this.searchPromptStr = str.trim().toLowerCase()
                    },
                    setPrompt(prompt) {
                        this.prompt = prompt
                    },
                    focusOnPrompt() {
                        this.$nextTick(() => this.$refs.prompt?.focus())
                    },
                    toggleMobileOptions() {
                        this.mobileOptionsShow = !this.mobileOptionsShow;
                        if (this.mobileOptionsShow) {
                            this.mobileSidebarShow = false;
                            this.mobilePromptNavShow = false;
                            this.mobileHeaderMoreOptionsShow = false;
                        }
                    },
                    toggleMobileSidebar() {
                        this.mobileSidebarShow = !this.mobileSidebarShow;
                        if (this.mobileSidebarShow) {
                            this.mobileOptionsShow = false;
                            this.mobilePromptNavShow = false;
                            this.mobileHeaderMoreOptionsShow = false;
                        }
                    },
                    toggleMobileHeaderMoreOptions() {
                        this.mobileHeaderMoreOptionsShow = !this.mobileHeaderMoreOptionsShow;
                        if (this.mobileHeaderMoreOptionsShow) {
                            this.mobileOptionsShow = false;
                            this.mobileSidebarShow = false;
                            this.mobilePromptNavShow = false;
                        }
                    },
                    toggleSidebarHidden() {
                        this.sidebarHidden = !this.sidebarHidden
                    },
                    setRealtimeStatus(status) {
                        this.realtimeStatus = status
                    },
                    isShowRecent(element) {
                        let el = element;

                        do {
                            if (el && el.id && el.id == 'show-recent-btn') {
                                return true;
                            }
                        } while (el = el.parentElement);

                        return false;
                    },

                    toggleToolSelection(tool) {
                        const index = this.selectedTools.indexOf(tool);

                        if (index === -1) {
                            this.selectedTools.push(tool);
                        } else {
                            this.selectedTools.splice(index, 1);
                        }
                    },

                    addToolSelection(tool) {
                        if (!this.selectedTools.includes(tool)) {
                            this.selectedTools.push(tool);
                        }
                    },

                    removeToolSelection(tool) {
                        const index = this.selectedTools.indexOf(tool);

                        if (index !== -1) {
                            this.selectedTools.splice(index, 1);
                        }
                    },

                    hasToolSelected(tool) {
                        return this.selectedTools.includes(tool);
                    },

                    onNewChatCreated(event) {
                        this.selectedTools = [];
                    }
                }));

                Alpine.data('socialMediaAgentChatPostCard', () => ({
                    post: null,
                    contentTemplate: document.querySelector('#social-media-agent-chat-post-card-template'),
                    scheduleButton: null,
                    postQuery: '',
                    videoStatusEndpoint: '{{ route('dashboard.user.social-media.agent.video.status') }}',
                    videoPollingTimer: null,
                    videoPollingDelay: 6000,
                    videoPollingAttempts: 0,
                    videoPollingMaxAttempts: 40,

                    async init() {
                        this.appendContentTemplate();

                        this.postQuery = `id=${this.$el.getAttribute('data-post-id')}`;

                        await this.loadPost();

                        this.$watch('post', () => this.evaluateVideoPolling());
                        if (this.$cleanup) {
                            this.$cleanup(() => this.stopVideoPolling());
                        }
                    },

                    async loadPost({
                        suppressErrors = false
                    } = {}) {
                        const data = await this.fetchPost({
                            query: this.postQuery,
                            suppressErrors,
                        });

                        const post = data?.posts?.data?.[0];

                        if (!post) {
                            if (!suppressErrors) {
                                toastr.error('{{ __('Failed Fetching Post.') }}');
                            }

                            return;
                        }

                        this.post = post;
                        this.evaluateVideoPolling();
                    },

                    appendContentTemplate() {
                        if (!this.contentTemplate) return;

                        const clone = this.contentTemplate.content.cloneNode(true);
                        this.$el.appendChild(clone);
                    },

                    evaluateVideoPolling() {
                        if (this.shouldPollVideo(this.post)) {
                            this.startVideoPolling();
                        } else {
                            this.stopVideoPolling();
                        }
                    },

                    shouldPollVideo(post) {
                        if (!post) {
                            return false;
                        }

                        const hasVideo = Array.isArray(post.video_urls) && post.video_urls.length > 0;
                        const pendingStatuses = ['pending', 'generating'];

                        return !hasVideo &&
                            !!post.video_request_id &&
                            pendingStatuses.includes(post.video_status ?? 'none') &&
                            !!this.videoStatusEndpoint;
                    },

                    startVideoPolling() {
                        if (this.videoPollingTimer) {
                            return;
                        }

                        this.videoPollingAttempts = 0;
                        this.scheduleNextVideoPoll();
                    },

                    scheduleNextVideoPoll() {
                        if (this.videoPollingTimer) {
                            clearTimeout(this.videoPollingTimer);
                        }

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
                        this.videoPollingAttempts = 0;
                    },

                    async pollVideoStatus() {
                        if (!this.postQuery) {
                            return;
                        }

                        this.videoPollingAttempts += 1;

                        let updatedPost = null;

                        if (this.post?.video_request_id && this.videoStatusEndpoint) {
                            const result = await this.fetchVideoStatus(this.post.video_request_id);

                            if (result?.success && result.post) {
                                updatedPost = result.post;
                            } else if (result?.status) {
                                this.post = {
                                    ...this.post,
                                    video_status: result.status,
                                };
                            }
                        }

                        if (!updatedPost) {
                            const data = await this.fetchPost({
                                query: this.postQuery,
                                suppressErrors: true,
                            });

                            updatedPost = data?.posts?.data?.[0] ?? null;
                        }

                        if (updatedPost) {
                            this.post = updatedPost;
                            this.$dispatch('social-media-agent-post-updated', {
                                post: updatedPost
                            });
                        }

                        if (this.shouldPollVideo(this.post) && this.videoPollingAttempts < this.videoPollingMaxAttempts) {
                            this.scheduleNextVideoPoll();
                        } else {
                            this.stopVideoPolling();
                        }
                    },

                    onPostUpdated(event) {
                        const {
                            post
                        } = event.detail;

                        if (!post || post.id !== this.post?.id) {
                            return;
                        }

                        this.post = post;
                        this.evaluateVideoPolling();
                    },

                    onPostRejected(event) {
                        const {
                            postId
                        } = event.detail;

                        if (!this.post || postId !== this.post.id) {
                            return;
                        }

                        this.post = null;
                        this.stopVideoPolling();
                    },

                    async fetchPost({
                        query,
                        url,
                        suppressErrors = false,
                    } = {}) {
                        if (!url && !query) {
                            if (!suppressErrors) {
                                toastr.error('{{ __('Please provide a valid url or query.') }}');
                            }

                            return null;
                        }

                        url = url ?? `/dashboard/user/social-media/agent/api/posts?per_page=1&${query}`;

                        try {
                            const res = await fetch(url, {
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                }
                            });
                            const data = await res.json();

                            if (!data.success) {
                                if (!suppressErrors) {
                                    const message = data.message || '{{ __('Failed fetching post') }}';
                                    toastr.error(message);
                                }

                                return null;
                            }

                            return data;
                        } catch (err) {
                            if (!suppressErrors) {
                                const message = err.message || '{{ __('Failed fetching post') }}';
                                toastr.error(message);
                            }

                            return null;
                        }
                    },

                    async fetchVideoStatus(requestId, suppressErrors = true) {
                        if (!requestId || !this.videoStatusEndpoint) {
                            return null;
                        }

                        try {
                            const url = `${this.videoStatusEndpoint}?request_id=${encodeURIComponent(requestId)}`;
                            const res = await fetch(url, {
                                headers: {
                                    'Accept': 'application/json'
                                }
                            });

                            const data = await res.json();

                            if (!data.success && !suppressErrors) {
                                const message = data.message || '{{ __('Failed fetching video status') }}';
                                toastr.error(message);
                            }

                            return data;
                        } catch (error) {
                            if (!suppressErrors) {
                                const message = error.message || '{{ __('Failed fetching video status') }}';
                                toastr.error(message);
                            }

                            return null;
                        }
                    },
                }));
            });

        })();
    </script>
@endpush
