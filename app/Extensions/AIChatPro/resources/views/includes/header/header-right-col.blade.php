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
        <x-light-dark-switch class="size-[38px] border text-heading-foreground hover:border-primary hover:bg-primary hover:text-primary-foreground hover:outline-primary" />
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
        <x-user-dropdown class:trigger="size-[38px] hover:outline-primary text-heading-foreground hover:bg-primary hover:text-primary-foreground hover:border-primary border">
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
			href="{{ route('login', ['redirect' => 'chatPro']) }}"
		>
			{!! __($fSetting->sign_in) !!}
		</x-button>

		<x-button
			class="h-[38px] rounded-lg outline"
			variant="outline"
			hover-variant="primary"
			href="{{ route('register', ['redirect' => 'chatPro']) }}"
		>
			{!! __($fSetting->join_hub) !!}
		</x-button>
    @endauth
</div>
