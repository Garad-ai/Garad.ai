{{-- index.blade --}}
@php
    $user_is_premium = false;
    $plan = auth()->user()?->relationPlan;
    if ($plan) {
        $planType = strtolower($plan->plan_type ?? 'all');
        if ($plan->plan_type === 'all' || $plan->plan_type === 'premium') {
            $user_is_premium = true;
        }
    }

    $premium_features = [
        ['title' => 'Chat History', 'is_pro' => false],
        ['title' => 'Chat with Document', 'is_pro' => false],
        ['title' => 'Unlimited Credits', 'is_pro' => true],
        ['title' => 'Chatbot Training', 'is_pro' => true],
        ['title' => 'Voice Chat', 'is_pro' => true],
        ['title' => 'Access to All AI Tools', 'is_pro' => true],
    ];

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
    'body_class' => 'lqd-chat-v2 overflow-hidden',
])
@section('title', __('AI Chat Image'))

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

@section('content')
    {{-- Background --}}
    <div
        class="pointer-events-none absolute inset-x-0 top-0 -z-1 overflow-hidden mix-blend-darken blur-[1px] dark:opacity-70 dark:mix-blend-color dark:[mask-image:linear-gradient(to_bottom,black,transparent)]"
        aria-hidden="true"
    >
        <img
            class="w-full"
            src="{{ custom_theme_url('/vendor/ai-image-pro/images/bg.jpg') }}"
            alt="Background image"
        >
    </div>

    <input
        id="openChatAreaContainerUrl"
        type="hidden"
        name="openChatAreaContainerUrl"
        value="@yield('openChatAreaContainerUrl', '/dashboard/user/openai/chat/open-chat-area-container')"
    />

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
                    <x-button
                        class="lqd-new-chat-trigger relative size-11 before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-50 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100"
                        size="none"
                        hover-variant="primary"
                        variant="outline"
                        href="javascript:void(0);"
                        title="{{ __('New Chat') }}"
                        onclick="{!! $app_is_demo
                            ? 'return toastr.info(\'{{ __('This feature is disabled in Demo version.') }}\')'
                            : (auth()->check()
                                ? 'return startNewChat(\'{{ $category?->id }}\', \'{{ LaravelLocalization::getCurrentLocale() }}\', \'chatpro-image\')'
                                : 'return window.location.reload();') !!}"
                    >
                        <x-tabler-plus class="size-5" />
                    </x-button>
                @endif
            </div>
        </div>

        <div class="grow px-2 md:px-5 lg:px-0">
            <div @class([
                'lqd-chat-pro-header relative end-0 start-0 top-0 z-40 h-[--header-h] justify-between gap-3 px-3.5 transition-all md:px-5 lg:absolute lg:start-[--sidebar-w] lg:z-10 xl:px-8',
                'hidden lg:flex' => Auth::check(),
                'flex max-md:-mx-2 md:max-lg:-mx-5' => !Auth::check(),
            ])>
                <x-progressive-blur
                    class="absolute -bottom-10 top-0 -z-1"
                    dir="reverse"
                />
                <div class="hidden w-5/12 items-center gap-4 lg:flex">
                    @include('ai-image-pro::includes.select-model-dropdown')
                </div>

                <div class="flex w-2/12 items-center gap-2 lg:justify-center">
                    <x-header-logo />
                </div>

                <div class="flex w-5/12 items-center justify-end gap-3.5 max-lg:grow max-sm:gap-2">

                    @if ((!$user_is_premium || $app_is_demo) && !auth()->check())
                        <x-modal
                            class:modal-content="w-[min(calc(100%-2rem),845px)]"
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
                            href="{{ route('login', ['redirect' => 'chatProImage']) }}"
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
                        'max-md:h-[calc(100vh-var(--header-height,var(--header-h,0px))-var(--bottom-menu-height,0px)-var(--ad-h,0px)-2.5rem)] md:grid md:max-lg:grid-flow-col md:max-lg:[grid-template-columns:30%_70%]' => Auth::check(),
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
                                'website_url' => 'chatpro-image',
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
                            @else
                                @include('ai-chat-pro-image-chat::includes.chat_area_container')
                            @endif
                        @else
                            <div class="conversation-area flex h-[inherit] grow flex-col justify-between overflow-y-auto rounded-b-[inherit] rounded-t-[inherit] max-md:max-h-full">
                            </div>
                        @endif
                    </x-card>
                </div>
            </div>
        </div>
    </div>

    <template id="chat_user_image_bubble">
        <div class="lqd-chat-image-bubble mb-2 flex !w-fit max-w-[50%] !flex-row content-end gap-2 !px-3 !py-2.5 last:mb-0 lg:ms-auto lg:justify-self-end">
            <a
                class="inline-flex shrink-0 items-center gap-1.5 overflow-hidden rounded-[inherit]"
                data-fslightbox="gallery"
                data-type="image"
                href="#"
                target="_blank"
            >
                <img
                    class="img-content max-w-40 rounded-xl"
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
                <div class="chat-content px-5 py-3.5"></div>
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
                <span class="lqd-chat-sender-name inline-grid grid-cols-1 items-center">
                    <span class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block transition group-[&.showing-status]:invisible group-[&.showing-status]:opacity-0">
                        {{ __($chat?->category?->name ?? 'AI Assistant') }}
                    </span>
                    <x-shimmer-text
                        class="invisible col-start-1 col-end-1 row-start-1 row-end-1 inline-block opacity-0 transition group-[&.generating-image]:visible group-[&.generating-image]:opacity-100"
                    >
                        @lang('Generating Image...')
                    </x-shimmer-text>
                    <x-shimmer-text
                        class="invisible col-start-1 col-end-1 row-start-1 row-end-1 inline-block opacity-0 transition group-[&.generating-suggestions]:visible group-[&.generating-suggestions]:opacity-100"
                    >
                        @lang('Generating Suggestions...')
                    </x-shimmer-text>
                </span>
            </div>
            <div
                class="chat-content-container relative min-h-12 min-w-12 max-w-[calc(100%-64px)] rounded-3xl text-heading-foreground group-[&.generating-image]:w-full dark:text-heading-foreground">
                <div
                    class="inline-flex min-h-11 max-w-full items-center rounded-full font-medium leading-none transition-all group-[&.generating-image]:w-full group-has-[.lqd-image-placeholder]:hidden">
                    <div class="lqd-typing relative inline-flex aspect-square w-12 shrink-0 items-center justify-center overflow-hidden">
                        <div class="lqd-typing-dots flex h-5 shrink-0 items-center justify-center gap-1">
                            <span class="lqd-typing-dot inline-block size-1 shrink-0 rounded-full bg-current opacity-40 ![animation-delay:0.2s]"></span>
                            <span class="lqd-typing-dot inline-block size-1 shrink-0 rounded-full bg-current opacity-60 ![animation-delay:0.3s]"></span>
                            <span class="lqd-typing-dot inline-block size-1 shrink-0 rounded-full bg-current opacity-80 ![animation-delay:0.4s]"></span>
                        </div>
                    </div>
                    <div
                        class="chat-content prose relative w-full max-w-none px-5 py-3.5 indent-0 font-[inherit] text-xs font-normal text-current [word-break:break-word] empty:hidden group-[&.generating-image]:w-full">
                    </div>

                    <div
                        class="lqd-chat-actions-wrap pointer-events-auto invisible absolute -end-5 bottom-0 flex flex-col gap-2 opacity-0 transition-all group-hover:!visible group-hover:!opacity-100">
                        <button
                            class="lqd-reimagine-images group/btn relative inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110"
                            title="{{ __('Reimagine') }}"
                            @click.prevent="$store.chatsV2.reimagineImages($event, $el)"
                        >
                            <span
                                class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                            >
                                {{ __('Reimagine') }}
                            </span>
                            <x-tabler-rotate class="size-4" />
                        </button>

                        <button
                            class="lqd-download-images group/btn relative inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110"
                            title="{{ __('Download images') }}"
                            x-data="{ downloading: false }"
                            @click.prevent="$store.chatsV2.downloadBubbleImages($event, $el)"
                            :disabled="downloading"
                        >
                            <span
                                class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                            >
                                {{ __('Download images') }}
                            </span>
                            <x-tabler-download
                                class="size-4"
                                x-show="!downloading"
                            />
                            <x-tabler-loader-2
                                class="size-4 animate-spin"
                                x-show="downloading"
                                x-cloak
                            />
                        </button>

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
            <div class="lqd-chat-bubble-foot group-has-[.lqd-image-placeholder]:hidden">
                <div
                    class="lqd-chat-bubble-suggestions flex flex-wrap gap-2"
                    x-data="{ suggestions: [] }"
                >
                    <template
                        x-for="(suggestion, index) in suggestions"
                        x-key="index"
                    >
                        <x-button
                            class="bg-primary/5 text-3xs font-semibold text-primary hover:bg-primary hover:text-primary-foreground"
                            type="button"
                            x-text="suggestion"
                            ::data-prompt="suggestion"
                            @click.prevent="$store.chatsV2.onSuggestionClick($event)"
                        ></x-button>
                    </template>
                </div>
            </div>
        </div>
    </template>

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

    <template id="prompt_image_add_btn">
        <div class="promt_image_btn">
            <button class="aspect-square w-20 rounded-xl bg-foreground/10 text-2xl font-light transition-all hover:bg-emerald-500 hover:text-white">+
            </button>
        </div>
    </template>
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
    <script src="{{ custom_theme_url('/assets/libs/jsonrepair/jsonrepair.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/fslightbox/fslightbox.js') }}"></script>

    @include('ai-chat-pro-image-chat::includes.chat_js')

    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('chatsV2', () => ({
                    selectedModel: '',
                    models: @json($activeImageModels),
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

                    // Image generation properties with better tracking
                    activeImagePolls: new Map(),
                    processedRecords: new Set(),
                    completedRecords: new Set(),
                    displayedImages: new Set(),

                    init() {
                        Alpine.store('chatsV2', this);

                        const modelKeys = Object.keys(this.models);
                        const savedModel = localStorage.getItem('aiImageProSelectedModel');

                        // Use saved model if it exists and is valid, otherwise use first model
                        if (savedModel && modelKeys.includes(savedModel)) {
                            this.selectedModel = savedModel;
                        } else if (modelKeys[0]) {
                            this.selectedModel = modelKeys[0];
                        }

                        // Watch for model changes and save to localStorage
                        this.$watch('selectedModel', (value) => {
                            if (value) {
                                localStorage.setItem('aiImageProSelectedModel', value);
                                this.$dispatch('generator-changed', { generator: value });
                            }
                        });

                        // Dispatch initial model for cost preview
                        if (this.selectedModel) {
                            this.$nextTick(() => {
                                this.$dispatch('generator-changed', { generator: this.selectedModel });
                            });
                        }

                        // Cleanup on page unload
                        window.addEventListener('beforeunload', () => {
                            this.cleanupImagePolls();
                        });
                    },

                    // Existing UI methods
                    togglePromptLibraryShow() {
                        this.promptLibraryShow = !this.promptLibraryShow;
                    },
                    changePromptFilter(filter) {
                        filter !== this.promptFilter && (this.promptFilter = filter);
                    },
                    setSearchPromptStr(str) {
                        this.searchPromptStr = str.trim().toLowerCase();
                    },
                    setPrompt(prompt) {
                        this.prompt = prompt;
                    },
                    focusOnPrompt() {
                        this.$nextTick(() => this.$refs.prompt?.focus());
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
                        this.sidebarHidden = !this.sidebarHidden;
                    },
                    setRealtimeStatus(status) {
                        this.realtimeStatus = status;
                    },
                    isShowRecent(element) {
                        let el = element;
                        do {
                            if (el?.id === 'show-recent-btn') {
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
                    },

                    // Handle image record received
                    handleImageRecordReceived(recordId, responseObj) {
                        // Check if already processed or completed
                        if (this.processedRecords.has(recordId) || this.completedRecords.has(recordId)) {
                            console.log(`Record ${recordId} already handled, ignoring duplicate`);
                            return;
                        }

                        this.processedRecords.add(recordId);
                        this.startImagePolling(recordId, responseObj);
                    },

                    // Image polling with better state management
                    startImagePolling(recordId, responseObj) {
                        // Double-check to prevent duplicate polls
                        if (this.activeImagePolls.has(recordId)) {
                            clearInterval(this.activeImagePolls.get(recordId));
                            this.activeImagePolls.delete(recordId);
                        }

                        let pollAttempts = 0;
                        const maxAttempts = 60; // 5 minutes max (60 * 5 seconds)

                        const checkStatus = async () => {
                            pollAttempts++;

                            // Safety: Stop polling after max attempts
                            if (pollAttempts > maxAttempts) {
                                console.error(`Max poll attempts reached for record ${recordId}`);
                                clearInterval(pollInterval);
                                this.activeImagePolls.delete(recordId);
                                this.processedRecords.delete(recordId);
                                this.displayGenerationError('Image generation timeout', responseObj);
                                return;
                            }

                            // Check if already completed
                            if (this.completedRecords.has(recordId)) {
                                clearInterval(pollInterval);
                                this.activeImagePolls.delete(recordId);
                                return;
                            }

                            try {
                                const response = await fetch(`/dashboard/user/generator/check-image-status/${recordId}`, {
                                    method: 'GET',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                        'Accept': 'application/json',
                                    }
                                });

                                if (!response.ok) {
                                    throw new Error('Failed to check image status');
                                }

                                const data = await response.json();

                                if (data.status === 'completed') {
                                    // Immediately mark as completed and cleanup
                                    this.completedRecords.add(recordId);
                                    clearInterval(pollInterval);
                                    this.activeImagePolls.delete(recordId);

                                    // Display images WITH suggestions (now in data.suggestions_response)
                                    this.displayGeneratedImages(recordId, data, responseObj);

                                    // Cleanup processed records
                                    this.processedRecords.delete(recordId);
                                } else if (data.status === 'failed') {
                                    console.error(`Record ${recordId} failed:`, data.error);
                                    clearInterval(pollInterval);
                                    this.activeImagePolls.delete(recordId);
                                    this.processedRecords.delete(recordId);
                                    this.displayGenerationError(data.error || 'Image generation failed', responseObj);
                                }
                            } catch (error) {
                                console.error(`Error checking image status for ${recordId}:`, error);
                                // Don't stop polling on network errors, they might be temporary
                            }
                        };

                        // Check immediately first, then poll every 5 seconds
                        checkStatus();
                        const pollInterval = setInterval(checkStatus, 5000);

                        this.activeImagePolls.set(recordId, pollInterval);
                    },

                    // Display suggestions from database
                    displaySuggestions(suggestionsResponse, responseObj) {
                        if (!responseObj.bubbleEl) return;

                        const suggestions = suggestionsResponse.suggestions ?? ['Create a variation', 'Make it dramatic', 'Remove background',
                            'Adjust lighting', 'Change colors'
                        ];

                        const suggestionsContainer = responseObj.bubbleEl.querySelector('.lqd-chat-bubble-suggestions');
                        if (!suggestionsContainer) {
                            return;
                        }

                        try {
                            const suggestionsContainerData = Alpine.$data(suggestionsContainer);

                            if (!suggestionsContainerData) {
                                return;
                            }

                            // Set suggestions from the database response
                            suggestionsContainerData.suggestions = suggestions;

                            // Trigger re-render by forcing Alpine to update
                            this.$nextTick(() => {
                                if (suggestionsContainerData.$el) {
                                    suggestionsContainerData.$el.dispatchEvent(new CustomEvent('alpine:updated'));
                                }
                            });
                        } catch (error) {
                            console.error('Error setting suggestions:', error);
                        }
                    },

                    // Display generated images - only once
                    displayGeneratedImages(recordId, data, responseObj) {
                        // Create unique key for this display operation
                        const displayKey = `${data.paths?.[0]}-${responseObj.responseId}`;

                        // Check if we've already displayed these images
                        if (this.displayedImages.has(displayKey)) {
                            return;
                        }

                        // Mark as displayed immediately
                        this.displayedImages.add(displayKey);

                        responseObj.bubbleEl?.classList.remove('showing-status', 'generating-image');

                        // Add the generated images as markdown
                        if (data.paths && data.paths.length > 0) {
                            const imagesMD = `::: lqd-chat-image-grid \n
${data.paths.map(imagePath => `![Generated Image](${imagePath})`)} \n
::: \n`;
                            responseObj.response.push(imagesMD);
                        }

                        if (responseObj.placeholderEls) {
                            responseObj.placeholderEls.forEach(placeholderEl => placeholderEl.remove());
                            responseObj.placeholderEls = [];
                        }

                        const brief = data.suggestions_response?.brief ?? 'Nice — I generated that image for you. Want to make any changes?';

                        if (brief) {
                            responseObj.response.push('\n\n');
                            responseObj.response.push(brief);
                        }

                        // Call onAiResponse to render images using existing chat system
                        if (typeof onAiResponse === 'function') {
                            onAiResponse(responseObj);
                        }

                        const activeChatId = parseInt(
                            document.querySelector('#chat_id')?.value || window.chatid,
                            10
                        );
                        if (!isNaN(activeChatId) && data.paths && data.paths.length > 0) {
                            this.updateChatSidebarThumbnail(activeChatId, data.paths[0]);
                        }

                        // Display suggestions after images are rendered
                        this.$nextTick(() => {
                            if (data.suggestions_response) {
                                this.displaySuggestions(data.suggestions_response, responseObj);
                            }

                            if (typeof refreshFsLightbox === 'function') {
                                refreshFsLightbox();
                            }
                        });

                        this.$nextTick(() => {
                            if ('scrollConversationArea' in window) {
                                scrollConversationArea({
                                    smooth: true
                                });
                            }
                        });
                    },

                    normalizeImagePath(imagePath) {
                        if (!imagePath || typeof imagePath !== 'string') {
                            return null;
                        }

                        const trimmedPath = imagePath.trim();
                        if (!trimmedPath) {
                            return null;
                        }

                        if (
                            trimmedPath.startsWith('http://') ||
                            trimmedPath.startsWith('https://') ||
                            trimmedPath.startsWith('data:') ||
                            trimmedPath.startsWith('blob:')
                        ) {
                            return trimmedPath;
                        }

                        if (trimmedPath.startsWith('//')) {
                            return `${window.location.protocol}${trimmedPath}`;
                        }

                        return `${window.location.origin}/${trimmedPath.replace(/^\/+/, '')}`;
                    },

                    updateChatSidebarThumbnail(chatId, imagePath) {
                        const listItem = document.querySelector(`#chat_${chatId}`);
                        if (!listItem) {
                            return;
                        }

                        const normalizedImagePath = this.normalizeImagePath(imagePath);
                        if (!normalizedImagePath) {
                            return;
                        }

                        const trigger = listItem.querySelector('.chat-list-item-trigger');
                        if (!trigger) {
                            return;
                        }

                        let thumbWrap = listItem.querySelector('.lqd-chat-item-thumb-wrap');
                        if (!thumbWrap) {
                            thumbWrap = document.createElement('div');
                            thumbWrap.className =
                                'lqd-chat-item-thumb-wrap relative size-6 shrink-0 overflow-hidden rounded bg-foreground/5';
                            trigger.insertBefore(thumbWrap, trigger.firstChild);
                        }

                        let imageEl = thumbWrap.querySelector('.lqd-chat-item-thumb');
                        if (!imageEl) {
                            imageEl = document.createElement('img');
                            imageEl.className = 'lqd-chat-item-thumb size-full object-cover';
                            imageEl.alt = 'Generated image preview';
                            imageEl.loading = 'lazy';
                            imageEl.addEventListener('error', () => {
                                thumbWrap.classList.add('hidden');
                                const fallback = listItem.querySelector('.lqd-chat-item-trigger-icons');
                                fallback?.classList.remove('hidden');
                            });
                            thumbWrap.appendChild(imageEl);
                        }

                        imageEl.src = normalizedImagePath;
                        thumbWrap.classList.remove('hidden');

                        const fallbackIcons = listItem.querySelector('.lqd-chat-item-trigger-icons');
                        fallbackIcons?.classList.add('hidden');
                    },

                    // Error display
                    displayGenerationError(errorMessage, responseObj) {
                        const generatingIndex = responseObj.response.findIndex(item =>
                            typeof item === 'string' && (
                                item.includes('Generating') ||
                                item.includes('generating-image-indicator')
                            )
                        );

                        if (generatingIndex !== -1) {
                            responseObj.response.splice(generatingIndex, 1);
                        }

                        // Remove placeholders if they exist
                        if (responseObj.placeholderEls) {
                            responseObj.placeholderEls.forEach(placeholderEl => placeholderEl.remove());
                            responseObj.placeholderEls = [];
                        }

                        // Clean up stream markers from error message
                        const cleanedMessage = errorMessage.replace(/^\[DONE\]/, '').trim();
                        responseObj.response.push(cleanedMessage);

                        responseObj.bubbleEl?.classList.remove('showing-status', 'generating-image');

                        if (typeof onAiResponse === 'function') {
                            onAiResponse(responseObj);
                        }
                    },

                    // Create image placeholders
                    createImagePlaceholders(responseObj) {
                        const aspectRatioVal = responseObj.request?.formData?.get('aspect_ratio') ?? '1:1';
                        const imageCount = responseObj.request?.formData?.get('image_count') ?? 1;
                        const splitter = aspectRatioVal.includes('x') ? 'x' : ':';
                        const aspectRatioArray = aspectRatioVal.split(splitter);
                        const aspectRatioWidth = aspectRatioArray.at(-2) && !isNaN(aspectRatioArray.at(-2)) ? aspectRatioArray.at(-2) : 1;
                        const aspectRatioHeight = aspectRatioArray.at(-1) && !isNaN(aspectRatioArray.at(-1)) ? aspectRatioArray.at(-1) : 1;
                        const CSSAspectRatioValue = `${aspectRatioWidth} / ${aspectRatioHeight}`;
                        const placeholdersWrapper = document.createElement('div');

                        placeholdersWrapper.classList.add('lqd-image-placeholders-wrapper', 'grid', 'grid-cols-2', 'gap-1', 'w-full');

                        for (let i = 0; i < imageCount; i++) {
                            const placeholderEl = document.createElement('figure');

                            placeholderEl.classList.add(
                                'lqd-image-placeholder', 'lqd-shimmer-effect', 'inline-block', 'rounded-lg', 'border', 'border-foreground/5', 'relative',
                                'overflow-hidden'
                            );
                            placeholderEl.style.aspectRatio = CSSAspectRatioValue;
                            placeholderEl.setAttribute('data-index', i);

                            placeholdersWrapper.appendChild(placeholderEl);
                        }

                        responseObj.placeholderEls = [
                            ...responseObj.placeholderEls ?? [],
                            placeholdersWrapper
                        ];

                        responseObj.bubbleEl?.classList?.remove('loading');

                        responseObj.chatContentContainerEl?.insertAdjacentElement('afterbegin', placeholdersWrapper);

                        this.$nextTick(() => {
                            if ('scrollConversationArea' in window) {
                                scrollConversationArea({
                                    smooth: true
                                });
                            }
                        });
                    },

                    // Handle generation started
                    handleImageGenerationStarted(responseObj) {
                        responseObj.bubbleEl?.classList.add('showing-status', 'generating-image');
                        this.createImagePlaceholders(responseObj);
                    },

                    // Cleanup functions
                    cleanupImagePolls() {
                        this.activeImagePolls.forEach((interval) => clearInterval(interval));
                        this.activeImagePolls.clear();
                        this.processedRecords.clear();
                        this.completedRecords.clear();
                        this.displayedImages.clear();
                    },

                    get currentModel() {
                        return this.selectedModel && this.models[this.selectedModel] ?
                            this.models[this.selectedModel] :
                            null;
                    },

                    async downloadBubbleImages(event, buttonEl) {
                        const bubble = buttonEl.closest('.lqd-chat-ai-bubble');
                        if (!bubble) {
                            console.warn('Could not find parent bubble');
                            return;
                        }

                        const images = bubble.querySelectorAll('.chat-content img');
                        if (images.length === 0) {
                            toastr.info(@json(__('No images found in this message')));
                            return;
                        }

                        // Get Alpine data for loading state
                        const buttonData = Alpine.$data(buttonEl);
                        if (buttonData) {
                            buttonData.downloading = true;
                        }

                        try {
                            for (let i = 0; i < images.length; i++) {
                                const img = images[i];
                                const imageUrl = img.src;

                                if (!imageUrl) continue;

                                // Fetch the image as a blob
                                const response = await fetch(imageUrl);
                                const blob = await response.blob();

                                // Create download link
                                const url = window.URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.href = url;

                                // Extract filename from URL or generate one
                                const urlPath = new URL(imageUrl).pathname;
                                const filename = urlPath.split('/').pop() || `image-${i + 1}.png`;
                                link.download = filename;

                                // Trigger download
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                window.URL.revokeObjectURL(url);

                                // Small delay between downloads if multiple images
                                if (images.length > 1 && i < images.length - 1) {
                                    await new Promise(resolve => setTimeout(resolve, 300));
                                }
                            }

                            toastr.success(@json(__('Downloaded')) + ` ${images.length} ` + @json(__('image(s)')));
                        } catch (error) {
                            console.error('Error downloading images:', error);
                            toastr.error(@json(__('Failed to download images')));
                        } finally {
                            if (buttonData) {
                                buttonData.downloading = false;
                            }
                        }
                    },

                    async reimagineImages(event, buttonEl) {
                        const bubble = buttonEl.closest('.lqd-chat-ai-bubble');
                        const messageId = bubble?.getAttribute('data-message-id');

                        if (!messageId) {
                            return console.error('{{ __('No message ID found') }}');
                        }

                        try {
                            // Fetch the original image data for this message
                            const response = await fetch(`/dashboard/user/generator/get-message-image-data/${messageId}`, {
                                method: 'GET',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                                    'Accept': 'application/json',
                                },
                            });

                            if (!response.ok) {
                                const error = await response.json();
                                return window.toastr?.error(error.error || '{{ __('Failed to get image data') }}');
                            }

                            const data = await response.json();

                            if (!data.generated_image) {
                                return window.toastr?.error('{{ __('No image found to reimagine') }}');
                            }

                            const originalPrompt = data.prompt || '{{ __('Create a new variation of this image') }}';

                            // Set the hidden reimagine image URL (backend will use this silently)
                            const reimagineImageInput = document.querySelector('#reimagine_image_url');
                            if (reimagineImageInput) {
                                reimagineImageInput.value = data.generated_image;
                            }

                            // Set the hidden reimagine prompt (original prompt - this is used by backend)
                            const reimaginePromptInput = document.querySelector('#reimagine_prompt');
                            if (reimaginePromptInput) {
                                reimaginePromptInput.value = originalPrompt;
                            }

                            // Set the visible prompt to the original prompt (required for form validation)
                            // This will show in the user's message bubble as if they typed it
                            const promptInput = document.querySelector('#prompt');
                            if (promptInput) {
                                promptInput.value = originalPrompt;
                                promptInput.dispatchEvent(new Event('input'));
                            }

                            // Submit the form
                            const submitBtn = document.querySelector('#send_message_button');
                            if (submitBtn) {
                                submitBtn.click();
                            }
                        } catch (error) {
                            console.error('Reimagine error:', error);
                            window.toastr?.error('{{ __('Failed to reimagine. Please try again.') }}');
                        }
                    },

                    onSuggestionClick(event) {
                        const button = event.currentTarget;
                        const suggestion = button.getAttribute('data-prompt');

                        if (!suggestion) {
                            return console.error('{{ __('No suggestion found') }}');
                        }

                        // Set the suggestion as the prompt input value
                        const promptInput = document.querySelector('#prompt');
                        if (promptInput) {
                            promptInput.value = suggestion;
                            promptInput.dispatchEvent(new Event('input'));
                            promptInput.focus();

                            // Optionally auto-submit the form
                            const submitBtn = document.querySelector('#send_message_button');
                            if (submitBtn) {
                                submitBtn.click();
                            }

                            promptInput.value = '';
                            promptInput.dispatchEvent(new Event('input'));
                        }
                    }
                }));
            })
        })();
    </script>
@endpush
