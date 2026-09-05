@php
    $menu_items = app(App\Services\Common\FrontMenuService::class)->generate();
@endphp

<header
    @class([
        'site-header group/header absolute inset-x-0 top-0 z-50 transition-[background,shadow]',
    ])
    x-data="{
        windowScrollY: window.scrollY,
        scrollDir: 1,
        navbarHeight: $refs.navbar.offsetHeight,
        navbarOffsetTop: $refs.navbar.offsetTop - parseInt(getComputedStyle($refs.navbar).marginTop, 10),
        isSticky: false,
        get sections() {
            return [...document.querySelectorAll('.site-section')].map(section => {
                const rect = section.getBoundingClientRect();
                return {
                    el: section,
                    rect: {
                        top: rect.top + this.windowScrollY,
                        bottom: rect.bottom + this.windowScrollY,
                        height: rect.height,
                    },
                    isDark: section.getAttribute('data-color-scheme') === 'dark'
                }
            })
        },
        checkSticky: function() {
            if (this.windowScrollY > this.navbarOffsetTop) {
                this.isSticky = true;
            } else {
                this.isSticky = false;
            }
        },
        checkColorScheme: function() {
            if (window.innerWidth <= 992) return;
            const sectionBehindNavbar = this.sections.find(section => {
                return (
                    section.rect.top <= this.windowScrollY + this.navbarHeight &&
                    section.rect.bottom >= this.windowScrollY + this.navbarHeight
                );
            });
            if (sectionBehindNavbar) {
                $el.classList.toggle('is-dark', sectionBehindNavbar.isDark)
            }
        },
        checkScroll() {
            const currentScrollY = window.scrollY;

            if (this.windowScrollY < currentScrollY) {
                this.scrollDir = 1;
            } else {
                this.scrollDir = -1;
            }

            this.windowScrollY = window.scrollY;
        }
    }"
    x-init="document.body.style.setProperty('--header-height', navbarHeight + 'px');
    checkSticky();
    checkColorScheme();"
    @resize.window.debounce.500ms="navbarOffsetTop = $refs.navbar.offsetTop - parseInt(getComputedStyle($refs.navbar).marginTop, 10);"
    @scroll.window="checkSticky(); checkScroll();"
    @scroll.window.throttle.50ms="checkColorScheme();"
    :class="{ 'lqd-is-sticky': isSticky }"
>
    <div
        class="hidden"
        x-ref="navbar-placeholder"
        style="height: var(--header-height)"
        :class="{ 'hidden': !isSticky }"
    ></div>

    <nav
        class="site-header-nav relative flex items-center justify-between px-9 py-5 text-sm transition-all duration-500 group-[.lqd-is-sticky]/header:fixed group-[.lqd-is-sticky]/header:top-0 group-[.lqd-is-sticky]/header:w-full max-sm:px-2"
        id="frontend-local-navbar"
        x-ref="navbar"
    >
        <a
            class="site-logo relative basis-1/3 max-lg:basis-1/3"
            href="{{ route('index') }}"
        >
            @if (isset($setting->logo_dark))
                <img
                    class="peer absolute start-0 top-1/2 -translate-y-1/2 opacity-0 transition-all duration-300 group-[.is-dark]/header:opacity-100"
                    src="{{ custom_theme_url($setting->logo_dark_path, true) }}"
                    @if (isset($setting->logo_dark_2x_path)) srcset="/{{ $setting->logo_dark_2x_path }} 2x" @endif
                    alt="{{ custom_theme_url($setting->site_name) }} logo"
                >
            @endif
            <img
                class="transition-all duration-300 group-[.is-dark]/header:peer-first:opacity-0"
                src="{{ custom_theme_url($setting->logo_path, true) }}"
                @if (isset($setting->logo_2x_path)) srcset="/{{ $setting->logo_2x_path }} 2x" @endif
                alt="{{ $setting->site_name }} logo"
            >
        </a>
        <div
            class="site-nav-container transition-all max-lg:absolute max-lg:right-0 max-lg:top-full max-lg:max-h-0 max-lg:w-full max-lg:overflow-hidden max-lg:bg-[#0A0A0E] max-lg:text-white [&.lqd-is-active]:max-lg:max-h-[calc(100vh-150px)]">
            <div class="max-lg:max-h-[inherit] max-lg:overflow-y-scroll">
                <ul
                    class="flex items-center justify-center gap-2.5 whitespace-nowrap text-center backdrop-blur-md transition-all duration-300 group-[&.is-dark]/header:bg-white/10 max-xl:gap-10 max-lg:flex-col max-lg:items-start max-lg:gap-5 max-lg:p-10 lg:rounded-full lg:border lg:border-white/15 lg:bg-black/70 lg:px-3 lg:py-1.5">
                    @php
                        $setting->menu_options = $setting->menu_options
                            ? $setting->menu_options
                            : '[{"title": "Home","url": "#banner","target": false},{"title": "Features","url": "#features","target": false},{"title": "How it Works","url": "#how-it-works","target": false},{"title": "Testimonials","url": "#testimonials","target": false},{"title": "Pricing","url": "#pricing","target": false},{"title": "FAQ","url": "#faq","target": false}]';
                        $menu_options = json_decode($setting->menu_options, true);
                    @endphp
                    @foreach ($menu_items as $menu_item)
                        @php
                            $has_children = !empty($menu_item['mega_menu_id']);
                        @endphp
                        <li
                            @class([
                                'group/li w-full relative flex flex-wrap items-center gap-2 after:pointer-events-none after:absolute after:-inset-x-4 after:bottom-[calc(var(--sub-offset,0)*-1)] after:top-full [&.is-hover]:after:pointer-events-auto',
                                'has-children' => $has_children,
                                'has-mega-menu' => !empty($menu_item['mega_menu_id']),
                            ])
                            x-data="{ hover: false }"
                            x-on:mouseover="if(window.innerWidth < 992 ) return; hover = true"
                            x-on:mouseleave="if(window.innerWidth < 992 ) return; hover = false"
                            :class="{ 'is-hover': hover }"
                        >
                            <a
                                class="relative px-4 py-1.5 text-white/60 transition-all before:absolute before:inset-0 before:scale-90 before:rounded-full before:bg-white/20 before:opacity-0 before:transition-all hover:text-white hover:before:scale-100 hover:before:opacity-100 group-[&.is-dark]/header:text-white/90 [&.active]:text-white [&.active]:before:scale-100 [&.active]:before:opacity-100"
                                href="{{ $menu_item['url'] }}"
                                @if ($menu_item['target']) target="_blank" @endif
                            >
                                {{ __($menu_item['title']) }}

                                @if ($has_children)
                                @endif
                            </a>
                            @if ($has_children)
                                <span
                                    class="relative ms-auto inline-grid size-8 shrink-0 place-content-center align-middle before:absolute before:inset-0 before:rounded-xl before:bg-current before:opacity-5 lg:hidden"
                                    @click="hover = !hover"
                                >
                                    <x-tabler-chevron-down class="size-4" />
                                </span>
                            @endif
                            @if (!empty($menu_item['mega_menu_id']))
                                @includeFirst(['mega-menu::partials.frontend-megamenu', 'vendor.empty'], ['menu_item' => $menu_item])
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if (count(explode(',', $settings_two->languages)) > 1)
                    <div class="group relative -mt-3 block border-t border-white/5 px-10 pb-5 pt-6 md:hidden">
                        <p class="mb-3 flex items-center gap-2">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                fill="none"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                                <path d="M3.6 9h16.8"></path>
                                <path d="M3.6 15h16.8"></path>
                                <path d="M11.5 3a17 17 0 0 0 0 18"></path>
                                <path d="M12.5 3a17 17 0 0 1 0 18"></path>
                            </svg>
                            {{ __('Languages') }}
                        </p>
                        @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                            @if (in_array($localeCode, explode(',', $settings_two->languages)))
                                <a
                                    class="block border-b border-black border-opacity-5 py-3 transition-colors last:border-none hover:bg-black hover:bg-opacity-5"
                                    href="{{ route('language.change', $localeCode) }}"
                                    rel="alternate"
                                    hreflang="{{ $localeCode }}"
                                >{{ country2flag(substr($properties['regional'], strrpos($properties['regional'], '_') + 1)) }}
                                    {{ $properties['native'] }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="flex basis-1/3 justify-end gap-2 max-lg:basis-2/3">
            @if (count(explode(',', $settings_two->languages)) > 1)
                <div class="group relative hidden md:block">
                    <button
                        class="relative inline-flex size-10 items-center justify-center rounded-full border border-border p-0 text-center text-base font-semibold text-heading-foreground backdrop-blur-lg transition-all before:absolute before:end-0 before:top-full before:h-4 before:w-full group-hover:scale-110 group-hover:bg-heading-foreground group-hover:text-heading-background group-[&.is-dark]/header:border-white/15 group-[.lqd-is-sticky]/header:border-black/5 group-[&.is-dark]/header:text-white group-[&.is-dark]/header:hover:bg-white group-[&.is-dark]/header:hover:text-black"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            fill="none"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                            <path d="M3.6 9h16.8"></path>
                            <path d="M3.6 15h16.8"></path>
                            <path d="M11.5 3a17 17 0 0 0 0 18"></path>
                            <path d="M12.5 3a17 17 0 0 1 0 18"></path>
                        </svg>
                    </button>
                    <div
                        class="pointer-events-none absolute end-0 top-[calc(100%+1rem)] min-w-[145px] translate-y-2 rounded-md bg-white text-black opacity-0 shadow-lg transition-all group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100">
                        @foreach (LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                            @if (in_array($localeCode, explode(',', $settings_two->languages)))
                                <a
                                    class="block border-b border-black border-opacity-5 px-3 py-3 transition-colors last:border-none hover:bg-black hover:bg-opacity-5"
                                    href="{{ route('language.change', $localeCode) }}"
                                    rel="alternate"
                                    hreflang="{{ $localeCode }}"
                                >{{ country2flag(substr($properties['regional'], strrpos($properties['regional'], '_') + 1)) }}
                                    {{ $properties['native'] }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @auth
                <div class="mx-3">
                    <a
                        class="relative inline-flex items-center justify-center rounded-full bg-heading-foreground/[8%] px-5 py-3 text-center text-base font-semibold leading-none text-heading-foreground backdrop-blur-lg transition-all duration-300 hover:scale-110 hover:bg-heading-foreground hover:text-heading-background group-[&.is-dark]/header:bg-white/10 group-[&.is-dark]/header:text-white group-[&.is-dark]/header:hover:bg-white group-[&.is-dark]/header:hover:text-black max-sm:px-4 max-sm:text-2xs"
                        href="{{ route('dashboard.index') }}"
                    >
                        {!! __('Dashboard') !!}
                    </a>
                </div>
            @else
                <a
                    class="relative inline-flex items-center rounded-full border border-border px-5 py-3 text-base font-semibold leading-none text-heading-foreground backdrop-blur-lg transition-all hover:scale-110 hover:bg-heading-foreground hover:text-heading-background group-[&.is-dark]/header:border-white/15 group-[.lqd-is-sticky]/header:border-black/5 group-[&.is-dark]/header:text-white group-[&.is-dark]/header:hover:bg-white group-[&.is-dark]/header:hover:text-black max-sm:px-4 max-sm:text-2xs"
                    href="{{ route('login') }}"
                >
                    {!! __($fSetting->sign_in) !!}
                </a>
                <a
                    class="relative inline-flex items-center justify-center rounded-full bg-heading-foreground/[8%] px-5 py-3 text-center text-base font-semibold leading-none text-heading-foreground backdrop-blur-lg transition-all duration-300 hover:scale-110 hover:bg-heading-foreground hover:text-heading-background group-[&.is-dark]/header:bg-white/10 group-[&.is-dark]/header:text-white group-[&.is-dark]/header:hover:bg-white group-[&.is-dark]/header:hover:text-black max-sm:px-4 max-sm:text-2xs"
                    href="{{ route('register') }}"
                >
                    {!! __($fSetting->join_hub) !!}
                </a>
            @endauth

            <button class="mobile-nav-trigger group relative z-2 flex size-10 shrink-0 items-center justify-center rounded-full bg-heading-foreground/10 lg:hidden">
                <span class="flex w-4 flex-col gap-1">
                    @for ($i = 0; $i <= 1; $i++)
                        <span
                            class="inline-flex h-[2px] w-full bg-heading-foreground transition-transform first:origin-left last:origin-right group-[.lqd-is-sticky]/header:bg-black group-[&.lqd-is-active]:first:-translate-y-[2px] group-[&.lqd-is-active]:first:translate-x-[3px] group-[&.lqd-is-active]:first:rotate-45 group-[&.lqd-is-active]:last:-translate-x-[2px] group-[&.lqd-is-active]:last:-translate-y-[8px] group-[&.lqd-is-active]:last:-rotate-45"
                        ></span>
                    @endfor
                </span>
            </button>
        </div>
    </nav>

    @includeWhen($fSetting->floating_button_active, 'landing-page.header.floating-button')
</header>

@includeWhen($app_is_demo, 'landing-page.header.envato-link')

@includeWhen(in_array($settings_two->chatbot_status, ['frontend', 'both']) &&
        ($settings_two->chatbot_login_require == false || ($settings_two->chatbot_login_require == true && auth()->check())),
    'panel.chatbot.widget',
    ['page' => 'landing-page']
)
