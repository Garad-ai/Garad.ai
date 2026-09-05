<a
    class="lqd-skip-link pointer-events-none fixed start-7 top-7 z-[90] rounded-md bg-background px-3 py-1 text-lg opacity-0 shadow-xl focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-primary"
    href="#lqd-titlebar"
>
    {{ __('Skip to content') }}
</a>

<aside
    class="lqd-navbar no-scrollbar start-0 top-0 z-[99] w-[--navbar-width] shrink-0 overflow-hidden rounded-ee-navbar-ee rounded-es-navbar-es rounded-se-navbar-se rounded-ss-navbar-ss border-navbar-border bg-navbar-background text-navbar font-medium text-navbar-foreground transition-all max-lg:invisible max-lg:absolute max-lg:left-0 max-lg:top-[65px] max-lg:z-[99] max-lg:max-h-[calc(85vh-2rem)] max-lg:min-h-0 max-lg:w-full max-lg:origin-top max-lg:-translate-y-2 max-lg:scale-95 max-lg:rounded-b max-lg:bg-background max-lg:p-0 max-lg:opacity-0 max-lg:shadow-xl lg:sticky lg:bottom-0 lg:top-0 lg:h-screen lg:border-e max-lg:[&.lqd-is-active]:visible max-lg:[&.lqd-is-active]:translate-y-0 max-lg:[&.lqd-is-active]:scale-100 max-lg:[&.lqd-is-active]:opacity-100"
    x-init
    :class="{ 'lqd-is-active': !$store.mobileNav.navCollapse }"
    @click.outside="$store.navbarShrink.toggle('shrink')"
    @mouseleave="$store.navbarShrink.toggle('shrink')"
>
    <div
        class="lqd-navbar-inner no-scrollbar h-full overflow-y-auto overscroll-contain pe-navbar-pe ps-navbar-ps max-lg:max-h-[inherit] lg:group-[&.navbar-shrinked]/body:flex lg:group-[&.navbar-shrinked]/body:flex-col lg:group-[&.navbar-shrinked]/body:items-center lg:group-[&.navbar-shrinked]/body:justify-between">
        <button
            class="lqd-navbar-expander group/expander !visible relative flex cursor-pointer flex-col items-center gap-1.5 p-0 text-center text-4xs font-medium !opacity-100 transition-all max-lg:hidden"
            x-init
            @click.prevent="$store.navbarShrink.toggle()"
        >
            <div
                class="lqd-navbar-logo relative flex min-h-[--header-height] max-w-full items-center pe-navbar-link-pe ps-navbar-link-ps group-[.navbar-shrinked]/body:w-full group-[.navbar-shrinked]/body:justify-center group-[.navbar-shrinked]/body:px-0 group-[.navbar-shrinked]/body:text-center max-lg:hidden">
                <x-progressive-blur
                    class="lqd-navbar-logo-blur hidden lg:-bottom-4 lg:-end-navbar-pe lg:-start-navbar-ps lg:block"
                    dir="reverse"
                />

                <x-navbar.navbar-logo />
            </div>
        </button>

        @php
            $items = app(\App\Services\Common\MenuService::class)->generate();
            $isAdmin = \Auth::user()?->isAdmin();
        @endphp

        <div class="hidden w-full grow flex-col items-center pb-5 pt-3.5 lg:group-[&.navbar-shrinked]/body:flex">
            @php
                $middle_nav_urls = app(\App\Services\Common\MenuService::class)->boltMenu();
                $bottom_nav_urls = ['support', 'settings', 'affiliates'];
                $middle_nav_items = [];
                $bottom_nav_items = [];

                foreach ($items as $key => $item) {
                    if (in_array($key, array_keys($middle_nav_urls))) {
                        $middle_nav_items[$key] = $item;
                    } elseif (in_array($key, $bottom_nav_urls)) {
                        $bottom_nav_items[$key] = $item;
                    }
                }
            @endphp
            <nav class="flex w-full grow flex-col">
                <ul class="flex flex-col gap-3.5">
                    @foreach ($middle_nav_items as $key => $item)
                        {{-- <style>
							#{{ $key }} {
								--background: {{ $middle_nav_urls[$key]['background'] }};
								--foreground: {{ $middle_nav_urls[$key]['foreground'] }};
							}
						</style> --}}
                        @if (\App\Helpers\Classes\PlanHelper::planMenuCheck($userPlan, $key))
                            @if (data_get($item, 'is_admin'))
                                @if ($isAdmin)
                                    @if (data_get($item, 'show_condition', true) && data_get($item, 'is_active'))
                                        @if ($item['children_count'])
                                            @includeIf('default.components.navbar.partials.types.item-dropdown')
                                        @else
                                            @includeIf('default.components.navbar.partials.types.' . $item['type'])
                                        @endif
                                    @endif
                                @endif
                            @else
                                @if (data_get($item, 'show_condition', true) && data_get($item, 'is_active'))
                                    @if ($item['children_count'])
                                        @includeIf('default.components.navbar.partials.types.item-dropdown')
                                    @else
                                        @includeIf('default.components.navbar.partials.types.' . $item['type'])
                                    @endif
                                @endif
                            @endif
                        @endif
                    @endforeach
                </ul>

                <div class="mt-auto flex flex-col items-center">
                    <ul class="lqd-navbar-ul-focus-bottom flex w-full flex-col gap-3.5">
                        @foreach ($bottom_nav_items as $key => $item)
                            @if (\App\Helpers\Classes\PlanHelper::planMenuCheck($userPlan, $key))
                                @if (data_get($item, 'is_admin'))
                                    @if ($isAdmin)
                                        @if (data_get($item, 'show_condition', true) && data_get($item, 'is_active'))
                                            @if ($item['children_count'])
                                                @includeIf('default.components.navbar.partials.types.item-dropdown')
                                            @else
                                                @includeIf('default.components.navbar.partials.types.' . $item['type'])
                                            @endif
                                        @endif
                                    @endif
                                @else
                                    @if (data_get($item, 'show_condition', true) && data_get($item, 'is_active'))
                                        @if ($item['children_count'])
                                            @includeIf('default.components.navbar.partials.types.item-dropdown')
                                        @else
                                            @includeIf('default.components.navbar.partials.types.' . $item['type'])
                                        @endif
                                    @endif
                                @endif
                            @endif
                        @endforeach
                    </ul>
                </div>
            </nav>
        </div>

        <nav
            class="lqd-navbar-nav lg:group-[&.navbar-shrinked]/body:hidden"
            id="navbar-menu"
        >
            <ul class="lqd-navbar-ul">
                @foreach ($items as $key => $item)
                    @if (\App\Helpers\Classes\PlanHelper::planMenuCheck($userPlan, $key))
                        @if (data_get($item, 'is_admin'))
                            @if ($isAdmin)
                                @if (data_get($item, 'show_condition', true) && data_get($item, 'is_active'))
                                    @if ($item['children_count'])
                                        @includeIf('default.components.navbar.partials.types.item-dropdown')
                                    @else
                                        @includeIf('default.components.navbar.partials.types.' . $item['type'])
                                    @endif
                                @endif
                            @endif
                        @else
                            @if (data_get($item, 'show_condition', true) && data_get($item, 'is_active'))
                                @if ($item['children_count'])
                                    @includeIf('default.components.navbar.partials.types.item-dropdown')
                                @else
                                    @includeIf('default.components.navbar.partials.types.' . $item['type'])
                                @endif
                            @endif
                        @endif
                    @endif
                @endforeach

                {{-- Admin menu items --}}
                @if ($isAdmin)
                    {{-- <x-navbar.item>
						<x-navbar.link
							label="{{ __('ChatBot') }}"
							href="dashboard.chatbot.index"
							icon="tabler-message-2-code"
							active-condition="{{ activeRoute('dashboard.chatbot.*') }}"
							new
						/>
					</x-navbar.item> --}}

                    @if ($app_is_not_demo && setting('premium_support', true) && !\App\Helpers\Classes\Helper::isUserVIP())
                        <x-navbar.item>
                            <x-navbar.link
                                label="{{ __('Premium Membership') }}"
                                href="#"
                                trigger-type="modal"
                            >
                                <x-slot:modal>
                                    @includeIf('premium-support.index')
                                </x-slot:modal>
                            </x-navbar.link>
                        </x-navbar.item>
                    @endif
                @endif

                @auth
                    <x-navbar.item>
                        <x-navbar.divider />
                    </x-navbar.item>

                    <x-navbar.item class="group-[&.navbar-shrinked]/body:hidden">
                        <x-navbar.label>
                            {{ __('Credits') }}
                        </x-navbar.label>
                    </x-navbar.item>

                    <x-navbar.item class="pb-navbar-link-pb pe-navbar-link-pe ps-navbar-link-ps pt-navbar-link-pt group-[&.navbar-shrinked]/body:hidden">
                        <x-credit-list />
                    </x-navbar.item>
                @endauth

                @if ($setting->feature_affilates)
                    <x-navbar.item class="group-[&.navbar-shrinked]/body:hidden">
                        <x-navbar.divider />
                    </x-navbar.item>

                    <x-navbar.item class="group-[&.navbar-shrinked]/body:hidden">
                        <x-navbar.label>
                            {{ __('Affiliation') }}
                        </x-navbar.label>
                    </x-navbar.item>

                    <x-navbar.item class="pb-navbar-link-pb pe-navbar-link-pe ps-navbar-link-ps pt-navbar-link-pt group-[&.navbar-shrinked]/body:hidden">
                        <div
                            class="lqd-navbar-affiliation inline-block w-full rounded-xl border border-navbar-divider px-8 py-4 text-center text-2xs leading-tight transition-border">
                            <p class="m-0 mb-2 text-[20px] not-italic">🎁</p>
                            <p class="mb-4">{{ __('Invite your friend and get') }}
                                {{ $setting->affiliate_commission_percentage }}%
                                @if ($is_onetime_commission)
                                    {{ __('on their first purchase.') }}
                                @else
                                    {{ __('on all their purchases.') }}
                                @endif
                            </p>
                            <x-button
                                class="text-3xs"
                                href="{{ route('dashboard.user.affiliates.index') }}"
                                variant="ghost-shadow"
                            >
                                {{ __('Invite') }}
                            </x-button>
                        </div>
                    </x-navbar.item>
                @endif
            </ul>
        </nav>
    </div>
</aside>
