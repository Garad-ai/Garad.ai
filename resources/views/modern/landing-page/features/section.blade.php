@php
    $marquee_items = explode(',', $fSectSettings?->marquee_items);
@endphp

{!! adsense_features_728x90() !!}
<section
    class="site-section group/section relative pb-20 pt-32 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="features"
>
    <div class="w-full overflow-hidden">
        <svg
            class="absolute -bottom-20 end-1/2 z-0"
            width="2943"
            height="569"
            viewBox="0 0 2943 569"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M2198.61 322.118C2355.68 328.06 2638.4 255.894 2778.31 195.27C2957.55 117.605 2987.17 49.6129 2879.68 22.1326C2772.21 -5.35201 2554 -3.33324 2332.58 10.1458C2110.51 23.6601 1855.74 52.3913 1587.2 94.7737C1301.35 139.892 983.833 202.194 716.96 281.81C478.488 352.955 313.097 429.256 302.333 483.334C291.686 536.859 405.97 568.515 590.668 568.602C728.784 568.665 1000.18 544.899 1195.31 479.273C1283.96 449.462 1291.28 426.716 1247.01 417.874C1188.59 406.207 1071.68 410.816 965.647 416.753C746.744 429.015 483.367 461.578 210.626 510.419C143.069 522.516 75.7901 535.738 7.97147 550.071C-2.43565 552.274 -1.67353 555.048 8.6399 553.014C264.393 502.574 513.187 462.936 740.706 440.101C852.962 428.838 957.866 421.708 1051.74 419.175C1141.18 416.767 1269.27 413.932 1249.48 443.575C1231.54 470.491 1098.93 504.549 992.515 524.496C873.109 546.879 758.849 557.449 669.503 560.752C480.692 567.728 361.37 544.1 332.052 503.126C299.554 457.637 408.966 387.771 635.533 312.718C867.027 236.027 1158.7 172.666 1430.01 125.433C1920.66 40.0073 2383.73 -2.61508 2693.68 9.13567C2790.1 12.7955 2871.01 22.2102 2904.17 44.6537C2938.61 67.993 2924.43 103.285 2861.74 144.439C2807.64 179.966 2713.51 222.413 2579.98 259.318C2453.43 294.297 2299.68 320.259 2208.71 318.47C2203.83 318.37 2193.91 321.936 2198.61 322.118Z"
                fill="url(#paint0_linear_471_511)"
            />
            <defs>
                <linearGradient
                    id="paint0_linear_471_511"
                    x1="2396.86"
                    y1="35.1863"
                    x2="2237.99"
                    y2="229.675"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stop-color="#BDDAFF" />
                    <stop
                        offset="0.505"
                        stop-color="#E3D2FD"
                    />
                    <stop
                        offset="1"
                        stop-color="white"
                    />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="container">
        <header class="relative mx-auto mb-14 w-full text-center lg:w-1/2">
            <figure
                class="size-20 absolute -start-20 top-1/2 hidden -translate-y-1/2 animate-bounce place-content-center rounded-full bg-[#D9CCF4] [animation-duration:3s] lg:inline-grid"
                aria-hidden="true"
            >
                <img
                    class="relative -start-1 -top-0.5 group-[&.lqd-is-in-view]/section:animate-tada group-[&.lqd-is-in-view]/section:[animation-iteration-count:3]"
                    src="{{ custom_theme_url('/assets/landing-page/avatar-1.png') }}"
                    alt="{{ __('Decor Image') }}"
                    width="58"
                    height="58"
                >
            </figure>
            <h6 class="mb-5 inline-flex rounded-full border px-3.5 py-1.5">
                {!! __($fSectSettings->features_subtitle) ?? __('Designed to be your co-pilot') !!}
            </h6>
            <h2 class="mb-5">
                {!! __($fSectSettings->features_title) !!}
            </h2>
            <p class="text-header-p mx-auto lg:w-3/4">
                {!! $fSectSettings->features_description
                    ? __($fSectSettings->features_description)
                    : __('All-in-one platform designed to supercharge you in generating, analyzing, and refining AI-driven content.') !!}
            </p>
        </header>

        <div class="grid grid-cols-3 justify-between gap-x-20 gap-y-9 max-lg:grid-cols-2 max-lg:gap-x-10 max-md:grid-cols-1">
            @foreach ($futures as $item)
                @include('landing-page.features.item')
            @endforeach
        </div>
    </div>
</section>

<section
    class="site-section group/section relative pb-20 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100">
    <div style="mask-image: linear-gradient(to right, transparent, black 20%, black 80%, transparent);">
        <marquee
            behavior="alternate"
            scrolldelay="250"
        >
            <div class="flex items-center gap-4">
                @for ($i = 0; $i < 2; $i++)
                    @foreach ($marquee_items as $item)
                        <p class="inline-flex whitespace-nowrap rounded-xl bg-[#fafafa] px-2.5 py-3 text-[18px] font-medium leading-none">
                            <span class="text-gradient">{{ __($item) }}</span>
                        </p>
                    @endforeach
                @endfor
            </div>
        </marquee>
    </div>
</section>
