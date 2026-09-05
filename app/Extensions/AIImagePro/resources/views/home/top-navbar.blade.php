@php
    $theme = setting('dash_theme', 'default');
    $user_is_premium = false;
    $plan = auth()->user()?->relationPlan;

    if ($plan) {
        $planType = strtolower($plan->plan_type ?? 'all');
        if ($plan->plan_type === 'all' || $plan->plan_type === 'premium') {
            $user_is_premium = true;
        }
    }

    $premium_features = [
        ['title' => 'On-brand Assets'],
        ['title' => 'Image Chat Assistant'],
        ['title' => 'Video Generation'],
        ['title' => 'Upscale'],
        ['title' => 'Social Media Integration'],
        ['title' => 'Access to All AI Tools'],
    ];
@endphp

<nav @class([
    'lqd-adv-img-editor-nav absolute start-0 end-0 top-0 z-10 min-h-[--header-h] gap-4 py-2.5 sm:px-4 lg:fixed lg:end-[var(--body-padding,0px)] lg:start-[--navbar-width] lg:top-[var(--body-padding,0px)] lg:gap-6 lg:px-6',
    'flex' => !auth()->check(),
    'hidden lg:flex' => auth()->check(),
    'px-2' => $theme === 'bolt' || $theme === 'social-media-dashboard',
    'px-4' => $theme !== 'bolt' && $theme !== 'social-media-dashboard',
])>
    <x-progressive-blur
        class="absolute -bottom-10 top-0 -z-1"
        dir="reverse"
    />

    <div class="flex items-center gap-2">
        @if (!auth()->check())
            <button
                class="lqd-mobile-nav-toggle flex size-10 items-center justify-center lg:hidden"
                type="button"
                x-init
                @click.prevent="$store.mobileNav.toggleNav()"
                :class="{ 'lqd-is-active': !$store.mobileNav.navCollapse }"
            >
                <span class="lqd-mobile-nav-toggle-icon relative h-[2px] w-5 rounded-xl bg-current"></span>
            </button>

            <div class="lg:hidden">
                <x-header-logo class="max-sm:max-w-[100px]" />
            </div>
        @endif

        @include('ai-image-pro::includes.generations-dropdown')
    </div>

    @if ($fSectSettings->preheader_active && !auth()->check())
        <div class="site-preheader hidden items-center self-center rounded-full border border-foreground/5 px-3 py-1.5 text-xs lg:flex">
            <p class="m-0">
                <span class="me-1 font-semibold text-heading-foreground">
                    {{ __($fSetting->header_title) }}
                </span>
                {{ __($fSetting->header_text) }}
            </p>
        </div>
    @endif

    <div class="ms-auto flex select-none items-center justify-end gap-6">
        <div class="flex items-center justify-end gap-3.5 max-lg:grow max-sm:gap-2">
            @if ((!$user_is_premium || $app_is_demo) && !auth()->check())
                <x-modal
                    class="max-lg:hidden"
                    class:modal-head="p-0 border-none"
                    class:modal-body="p-0"
                    class:modal-container="max-w-full"
                    class:modal-content="w-[min(calc(100%-40px),950px)]"
                    class:close-btn="absolute top-3 end-3"
                >
                    <x-slot:trigger
                        class="text-sm font-semibold text-heading-foreground hover:bg-primary hover:text-primary-foreground"
                        variant="ghost"
                    >
                        <svg
                            width="18"
                            height="15"
                            viewBox="0 0 18 15"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6.875 6.875L5.125 4.95L5.65 4.075M3.375 0.75H13.875L16.5 5.125L9.0625 13.4375C9.00547 13.4957 8.9374 13.5419 8.86228 13.5735C8.78715 13.6051 8.70649 13.6213 8.625 13.6213C8.54351 13.6213 8.46285 13.6051 8.38772 13.5735C8.3126 13.5419 8.24453 13.4957 8.1875 13.4375L0.75 5.125L3.375 0.75Z"
                            />
                        </svg>
                        {{ __('Upgrade') }}
                    </x-slot:trigger>

                    <x-slot:modal>
                        <div class="flex flex-wrap md:flex-nowrap">
                            <div class="inline-flex w-full md:w-1/2">
                                <img
                                    class="aspect-video h-full w-full object-cover object-center md:aspect-auto"
                                    src="{{ custom_theme_url('/vendor/ai-image-pro/images/upgrade.jpg') }}"
                                    alt=""
                                    aria-hidden="true"
                                >
                            </div>

                            <div class="w-full px-8 py-10 md:w-1/2">
                                <h3 class="mb-5 text-pretty text-[23px] font-normal leading-[1.1em] [&_strong]:font-semibold">
                                    @lang('Upgrade your plan to access <strong>advanced AI features and additional credits.</strong>')
                                </h3>

                                <ul class="mb-5 space-y-1">
                                    @foreach ($premium_features as $feature)
                                        <li class="flex items-center gap-2.5 py-2 text-base font-medium">
                                            <x-tabler-check class="size-5" />
                                            {{ $feature['title'] }}
                                        </li>
                                    @endforeach
                                </ul>

                                {{-- TODO: add the link --}}
                                <x-button
                                    class="w-full text-2xs font-medium"
                                    href="{{ route('dashboard.user.index') }}"
                                    size="xl"
                                >
                                    @lang('Upgrade Plan')
                                </x-button>
                            </div>
                        </div>
                    </x-slot:modal>
                </x-modal>
            @endif

            <x-button
                class="size-[38px] border-none bg-foreground/5 text-foreground outline-none hover:bg-primary hover:text-primary-foreground max-lg:hidden"
                variant="none"
                href="{{ route('dashboard.user.affiliates.index') }}"
                size="none"
            >
                <span class="sr-only">
                    {{ __('Affiliate Program') }}
                </span>
                <x-tabler-gift class="size-5" />
            </x-button>

            @if (Theme::getSetting('dashboard.supportedColorSchemes') === 'all')
                <x-light-dark-switch class="size-[38px] border border-foreground/10 bg-transparent text-foreground outline-none hover:bg-primary hover:text-primary-foreground" />
            @endif

            @auth
                <x-button
                    class="size-[38px] border border-foreground/10 bg-transparent text-foreground outline-none hover:bg-primary hover:text-primary-foreground"
                    variant="none"
                    size="none"
                    href="{{ route('dashboard.user.index') }}"
                    title="{{ __('Dashboard') }}"
                >
                    <x-tabler-grid-dots class="size-[18px]" />
                </x-button>

                <x-user-dropdown class:trigger="size-[38px] border border-foreground/10 bg-transparent text-foreground outline-none hover:bg-primary hover:text-primary-foreground">
                    <x-slot:trigger>
                        <x-tabler-user-circle
                            class="size-6"
                            stroke-width="1.5"
                        />
                    </x-slot:trigger>
                </x-user-dropdown>
            @else
                @if ($setting->register_active == 1)
                    <x-button href="{{ route('login', ['redirect' => 'aiImagePro']) }}">
                        {!! __($fSetting->sign_in) !!}
                    </x-button>
                @endif
            @endauth
        </div>

        <x-button
            class="hidden size-[34px] shrink-0"
            variant="outline"
            hover-variant="primary"
            size="none"
            title="{{ __('Close') }}"
            ::class="{ 'hidden': currentView === 'home', 'inline-flex': currentView !== 'home' }"
            @click.prevent="switchView('home')"
        >
            <x-tabler-x class="size-4" />
        </x-button>
    </div>
</nav>
