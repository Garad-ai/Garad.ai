<a
    class="lqd-skip-link pointer-events-none fixed start-7 top-7 z-[90] rounded-md bg-background px-3 py-1 text-lg opacity-0 shadow-xl focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-primary"
    href="#lqd-titlebar"
>
    {{ __('Skip to content') }}
</a>

<button
    class="lqd-navbar-expander fixed start-[--navbar-width] top-[calc(var(--header-height)/2)] z-[999] inline-flex size-6 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center rounded-button border-0 bg-foreground/10 p-0 text-heading-foreground backdrop-blur-sm transition-all hover:bg-heading-foreground hover:text-heading-background group-[.navbar-shrinked]/body:!start-[80px] group-[.navbar-shrinked]/body:rotate-180 max-lg:hidden rtl:translate-x-1/2"
    x-init
    @click.prevent="$store.navbarShrink.toggle()"
>
    <x-tabler-chevron-left class="w-4 rtl:rotate-180" />
</button>

<aside
    class="lqd-navbar no-scrollbar z-[99] w-[--navbar-width] shrink-0 overflow-hidden rounded-ee-navbar-ee rounded-es-navbar-es rounded-se-navbar-se rounded-ss-navbar-ss border-e border-navbar-border bg-navbar-background text-navbar font-medium text-navbar-foreground transition-all max-lg:invisible max-lg:absolute max-lg:left-0 max-lg:top-[65px] max-lg:z-[99] max-lg:max-h-[calc(85vh-2rem)] max-lg:min-h-0 max-lg:w-full max-lg:origin-top max-lg:-translate-y-2 max-lg:scale-95 max-lg:rounded-b max-lg:bg-background max-lg:p-0 max-lg:opacity-0 max-lg:shadow-xl lg:sticky lg:top-0 lg:h-screen max-lg:[&.lqd-is-active]:visible max-lg:[&.lqd-is-active]:translate-y-0 max-lg:[&.lqd-is-active]:scale-100 max-lg:[&.lqd-is-active]:opacity-100"
    data-name="{{ \App\Enums\Introduction::SIDEBAR }}"
    x-init
    :class="{ 'lqd-is-active': !$store.mobileNav.navCollapse }"
>
    <div class="lqd-navbar-inner no-scrollbar h-full overflow-y-auto overscroll-contain pe-navbar-pe ps-navbar-ps max-lg:max-h-[inherit]">
        <div
            class="lqd-navbar-logo relative flex min-h-[--header-height] max-w-full items-center pe-navbar-link-pe ps-navbar-link-ps group-[.navbar-shrinked]/body:w-full group-[.navbar-shrinked]/body:justify-center group-[.navbar-shrinked]/body:px-0 group-[.navbar-shrinked]/body:text-center max-lg:hidden">
            <x-progressive-blur
                class="lqd-navbar-logo-blur hidden lg:-bottom-4 lg:-start-navbar-ps lg:-me-navbar-pe lg:block"
                dir="reverse"
            />

            <x-navbar.navbar-logo />
        </div>

        <nav
            class="lqd-navbar-nav"
            id="navbar-menu"
        >
            <ul class="lqd-navbar-ul">
                {{-- @include('panel.layout.partials.menu') --}}
                <!-- Menu cache -->
                {!! App\Caches\BladeCache::navMenu(static fn() => view('panel.layout.partials.menu')->render()) !!}
              @php
    $sidebarUserPlan = \App\Helpers\Classes\PlanHelper::userPlan();
@endphp

@if (!Auth::user()?->isAdmin() && !$sidebarUserPlan)
    <x-navbar.item class="group-[&.navbar-shrinked]/body:hidden">
        <div
            style="
                margin: 14px 10px 10px;
                padding: 18px 14px;
                border-radius: 16px;
                background: linear-gradient(145deg, rgba(124,58,237,0.13), rgba(168,85,247,0.07));
                border: 1px solid rgba(139,92,246,0.25);
                text-align: center;
            "
        >
            <div
                style="
                    width: 38px;
                    height: 38px;
                    margin: 0 auto 10px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #7c3aed, #a855f7);
                    color: white;
                    font-size: 20px;
                "
            >
                ✨
            </div>

            <h4
                style="
                    margin: 0 0 6px;
                    font-size: 14px;
                    font-weight: 700;
                "
            >
                {{ __('Unlock AI Tools') }}
            </h4>

            <p
                style="
                    margin: 0 0 14px;
                    font-size: 11px;
                    line-height: 1.5;
                    opacity: .7;
                "
            >
                {{ __('Upgrade your plan to access more powerful AI tools and features.') }}
            </p>

            <a
                href="{{ route('dashboard.user.payment.subscription') }}"
                style="
                    display: block;
                    width: 100%;
                    padding: 9px 12px;
                    border-radius: 10px;
                    background: linear-gradient(135deg, #7c3aed, #9333ea);
                    color: #fff;
                    font-size: 12px;
                    font-weight: 700;
                    text-decoration: none;
                "
            >
                {{ __('Upgrade Now') }}
            </a>
        </div>
    </x-navbar.item>
@endif
                @if (Auth::user()?->isAdmin())
                    @if ($app_is_not_demo && setting('premium_support', true) && !\App\Helpers\Classes\Helper::isUserVIP())
                        <x-navbar.item>
                            <x-navbar.link
                                label="{{ __('Premium Membership') }}"
                                href="#"
                                icon="tabler-diamond"
                                trigger-type="modal"
                            >
                                <x-slot:modal>
                                    @includeIf('premium-support.index')
                                </x-slot:modal>
                            </x-navbar.link>
                        </x-navbar.item>
                    @endif
                @endif

                <x-navbar.item>
                    <x-navbar.divider />
                </x-navbar.item>

                <x-navbar.item class="group-[&.navbar-shrinked]/body:hidden">
                    <x-navbar.label>
                        {{ __('Credits') }}
                    </x-navbar.label>
                </x-navbar.item>

                <x-navbar.item class="pb-navbar-link-pb pe-navbar-link-pe ps-navbar-link-ps pt-navbar-link-pt group-[&.navbar-shrinked]/body:hidden">
                    <x-credit-list
                        modal-trigger-pos="block"
                        expanded-modal-trigger="true"
                    />
                </x-navbar.item>

                @if ($setting->feature_affilates && \auth()->user()?->affiliate_status == 1)
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
