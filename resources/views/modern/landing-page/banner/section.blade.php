<section
    class="site-section relative z-1 flex min-h-screen items-center justify-center overflow-hidden pb-20 text-center max-md:pb-16 max-md:pt-36 lg:pt-52"
    id="banner"
>
    <div @class(['container', 'lg:pb-96' => $fSetting->hero_image])>
        <div class="repeating-gradient pointer-events-none absolute start-1/2 top-0 z-0 h-1/2 w-8/12 -translate-x-1/2 overflow-hidden opacity-20 md:w-1/2">
            <div class="repeating-gradient-lines absolute start-0 top-0 h-full w-[200%]"></div>
            <div
                class="repeating-gradient-overlay absolute start-0 top-0 h-full w-full bg-gradient-to-tr from-[#c3d8c6] from-20% via-[#8d5e85] via-75% to-[#71301b] mix-blend-color">
            </div>
        </div>
        <div class="relative mx-auto w-8/12 max-lg:w-full lg:px-8">
            <figure
                class="absolute -end-28 top-1/4 hidden translate-y-4 opacity-0 transition-all delay-500 duration-300 group-[.page-loaded]/body:translate-y-0 group-[.page-loaded]/body:opacity-100 lg:block"
                aria-hidden="true"
            >
                <img
                    class="animate-bounce [animation-duration:4s]"
                    src="{{ custom_theme_url('/assets/landing-page/robot-2.png') }}"
                    width="143"
                    height="182"
                />
            </figure>
            @if ($fSectSettings->preheader_active)
                <p
                    class="relative mb-8 inline-flex translate-y-5 flex-wrap items-center gap-5 overflow-hidden rounded-2xl bg-[#fafafa] py-4 pe-2 ps-5 text-start text-2xs leading-none opacity-0 transition-all duration-300 ease-out group-[.page-loaded]/body:translate-y-0 group-[.page-loaded]/body:opacity-100 max-md:justify-center max-md:text-center md:flex-nowrap md:rounded-full md:py-1">
                    <span
                        class="text-gradient before:bg-gradient relative z-1 inline-flex text-[10px] font-bold uppercase tracking-widest before:absolute before:-inset-x-2 before:-inset-y-1 before:-z-1 before:rounded-full before:opacity-20"
                    >
                        {{ __($fSetting->header_title) }}
                    </span>
                    <span class="opacity-75 max-md:w-full">
                        {{ __($fSetting->header_text) }}
                    </span>
                    <a
                        class="inline-flex items-center gap-2 rounded-full bg-background px-5 py-2.5 transition-all hover:scale-95 hover:bg-primary hover:text-primary-foreground"
                        href="#features"
                    >
                        {{ __('Learn more') }}
                        <x-tabler-chevron-right class="size-4" />
                    </a>
                </p>
            @elseif ($fSetting->hero_subtitle)
                <h6
                    class="relative mb-8 translate-y-5 overflow-hidden rounded-2xl px-3 py-1 opacity-0 transition-all duration-300 ease-out group-[.page-loaded]/body:translate-y-0 group-[.page-loaded]/body:opacity-100">
                    <div class="banner-subtitle-gradient absolute -inset-3 blur-3xl transition-all duration-500 group-[.page-loaded]/body:opacity-0">
                        <div class="animate-hue-rotate absolute inset-0 bg-gradient-to-br from-violet-600 to-red-500"></div>
                    </div>
                    <span class="relative">{!! __($setting->site_name) !!}</span>
                    <span class="dot relative"></span>
                    <span class="relative opacity-60">{!! __($fSetting->hero_subtitle) !!}</span>
                </h6>
            @endif
            <h1
                class="banner-title mb-7 translate-y-7 scale-90 opacity-0 transition-all duration-300 ease-out group-[.page-loaded]/body:translate-y-0 group-[.page-loaded]/body:scale-100 group-[.page-loaded]/body:opacity-100">
                {!! __($fSetting->hero_title) !!}
                @if ($fSetting->hero_title_text_rotator != null)
                    <span class="lqd-text-rotator inline-grid grid-cols-1 grid-rows-1 transition-[width] duration-200">
                        @foreach (explode(',', __($fSetting->hero_title_text_rotator)) as $keyword)
                            <span
                                class="lqd-text-rotator-item {{ $loop->first ? 'lqd-is-active' : '' }} col-start-1 row-start-1 inline-flex translate-x-3 opacity-0 blur-sm transition-all duration-300 [&.lqd-is-active]:translate-x-0 [&.lqd-is-active]:opacity-100 [&.lqd-is-active]:blur-0"
                            >
                                <span
                                    class="text-gradient inline-block before:absolute before:start-0 before:top-full before:h-1 before:w-full before:bg-gradient-to-r before:from-gradient-from before:from-20% before:via-gradient-via before:to-gradient-to before:to-80%"
                                >{!! $keyword !!}</span>
                            </span>
                        @endforeach
                    </span>
                @endif
            </h1>
            <p
                class="text-header-p mb-8 translate-y-5 scale-90 text-xl opacity-0 transition-all delay-[150ms] duration-300 ease-out group-[.page-loaded]/body:translate-y-0 group-[&.page-loaded]/body:scale-100 group-[.page-loaded]/body:opacity-100">
                {!! __($fSetting->hero_description) !!}
            </p>
            <div
                class="translate-y-5 scale-90 opacity-0 transition-all delay-300 duration-300 group-[.page-loaded]/body:translate-y-0 group-[&.page-loaded]/body:scale-100 group-[.page-loaded]/body:opacity-100">
                @if ($fSetting->hero_button_type == 1)
                    <a
                        class="bg-gradient relative inline-flex items-center gap-4 overflow-hidden rounded-full px-12 py-5 text-base font-medium leading-none text-white shadow-[0_11px_36px_#032A3E29] transition-all hover:scale-110 hover:shadow-xl hover:shadow-primary/30"
                        href="{{ !empty($fSetting->hero_button_url) ? $fSetting->hero_button_url : '#' }}"
                    >
                        {!! __($fSetting->hero_button) !!}
                        <x-tabler-chevron-right class="size-4" />
                    </a>
                @else
                    <a
                        class="bg-gradient inline-flex items-center justify-center px-12 py-3 text-lg font-medium text-white transition-all duration-300 hover:scale-110"
                        data-fslightbox="video-gallery"
                        style="border-radius: 3rem;"
                        href="{{ !empty($fSetting->hero_button_url) ? $fSetting->hero_button_url : '#' }}"
                    >
                        <svg
                            class="me-4 rounded-full bg-white p-3"
                            xmlns="http://www.w3.org/2000/svg"
                            width="36"
                            height="36"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                            fill="none"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M6 4v16a1 1 0 0 0 1.524 .852l13 -8a1 1 0 0 0 0 -1.704l-13 -8a1 1 0 0 0 -1.524 .852z"
                                stroke-width="0"
                                fill="#37393d"
                            ></path>
                        </svg>
                        {!! __($fSetting->hero_button) !!} &nbsp;
                    </a>
                @endif
            </div>

            <svg
                width="0"
                height="0"
            >
                <defs>
                    <linearGradient
                        id="checkmark_gradient"
                        x1="3.9938"
                        y1="0.699607"
                        x2="11.7544"
                        y2="15.3969"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#6D56B2" />
                        <stop
                            offset="1"
                            stop-color="#1CB2D3"
                        />
                    </linearGradient>
                </defs>
            </svg>
            <div
                class="mt-12 flex translate-y-5 scale-90 flex-wrap items-center justify-center gap-8 opacity-0 transition-all delay-[450ms] duration-300 group-[.page-loaded]/body:translate-y-0 group-[&.page-loaded]/body:scale-100 group-[.page-loaded]/body:opacity-100 md:justify-between">
                @foreach ($banner_bottom_texts as $item)
                    <div class="flex items-center gap-2.5 text-sm text-[#4A4A4A]">
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 16 16"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M2.18925 7.37087C1.89585 7.3717 1.60868 7.45557 1.36096 7.61279C1.11325 7.77001 0.915104 7.99415 0.789459 8.25929C0.663815 8.52442 0.615803 8.81971 0.650979 9.11099C0.686156 9.40228 0.803082 9.67765 0.988231 9.90525L4.93517 14.7402C5.0759 14.915 5.25629 15.0536 5.46136 15.1447C5.66643 15.2357 5.89026 15.2765 6.11425 15.2636C6.59333 15.2379 7.02585 14.9816 7.30161 14.5602L15.5004 1.35608C15.5018 1.35389 15.5032 1.3517 15.5046 1.34954C15.5815 1.23142 15.5566 0.997345 15.3978 0.850295C15.3542 0.809913 15.3027 0.778889 15.2467 0.759132C15.1906 0.739376 15.1311 0.731306 15.0718 0.735419C15.0125 0.739532 14.9547 0.755742 14.9019 0.783049C14.8491 0.810356 14.8024 0.848183 14.7648 0.894201C14.7619 0.897819 14.7588 0.901382 14.7557 0.904891L6.48711 10.2472C6.45565 10.2827 6.41743 10.3117 6.37469 10.3323C6.33195 10.353 6.28552 10.3649 6.23812 10.3675C6.19071 10.3701 6.14327 10.3632 6.09855 10.3473C6.05383 10.3313 6.01271 10.3067 5.9776 10.2747L3.23341 7.7775C2.9484 7.51624 2.57589 7.37117 2.18925 7.37087Z"
                                fill="url(#checkmark_gradient)"
                            />
                        </svg>
                        {{ __($item) }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

@if ($fSetting->hero_image)
    <section
        class="site-section group relative pt-10 lg:-mt-96 lg:pt-20"
        id="banner-ig"
    >
        <figure
            class="absolute bottom-0 start-0 z-0 w-full rotate-6 overflow-hidden opacity-0 transition-all duration-500 group-[&.lqd-is-in-view]:translate-y-0 group-[&.lqd-is-in-view]:rotate-0 group-[&.lqd-is-in-view]:opacity-100"
            aria-hidden="true"
        >
            <img
                class="mx-auto"
                style="mask-image: linear-gradient(to bottom, transparent, black 20%)"
                src="{{ custom_theme_url('/assets/landing-page/decor-1.jpg') }}"
                width="1464"
                height="976"
            />
        </figure>
        <div class="absolute -bottom-20 start-0 h-full w-full overflow-hidden">
            <svg
                class="absolute -end-1/3 bottom-0 z-1"
                width="3059"
                height="629"
                viewBox="0 0 3059 629"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M803.343 38.3681C647.273 -23.9941 347.476 -2.71842 193.456 47.9586C-3.87383 112.876 -49.0351 212.492 52.4401 292.901C153.893 373.31 373.065 443.395 598.056 496.089C823.708 548.945 1085.6 588.267 1364.39 610.217C1661.16 633.581 1993.58 639.891 2279.18 601.282C2534.39 566.777 2717.53 499.402 2740.63 415.874C2763.48 333.196 2656.15 243.774 2471.05 181.561C2332.63 135.04 2055.21 82.1261 1844.7 122.305C1749.06 140.552 1736.54 174.745 1778.91 203.874C1834.8 242.308 1953.03 274.173 2060.65 300.241C2282.84 354.051 2554.23 390.092 2838.71 403.048C2909.17 406.258 2979.62 407.561 3050.85 407.257C3061.78 407.205 3061.65 402.477 3050.85 402.289C2783.03 397.62 2524.65 377.881 2291.42 338.215C2176.34 318.638 2069.58 294.872 1974.91 267.403C1884.72 241.225 1755.7 202.746 1782.28 161.627C1806.39 124.282 1947.05 113.965 2058.25 117.584C2183.02 121.643 2299.95 143.01 2390.25 167.716C2581.08 219.93 2695.29 298.108 2715.35 373.991C2737.56 458.219 2612 534.037 2367.83 578.839C2118.35 624.626 1811.59 628.705 1528.92 613.641C1017.71 586.406 543.88 499.461 235.906 376.357C140.095 338.053 61.146 295.689 33.0221 248.378C3.81828 199.192 26.0564 147.082 98.2616 101.833C160.571 62.763 264.581 25.9954 406.809 11.3986C541.61 -2.43952 701.625 7.39758 792.388 40.8515C797.259 42.6543 808.016 40.2422 803.343 38.3681Z"
                    fill="url(#paint0_linear_471_510)"
                />
                <defs>
                    <linearGradient
                        id="paint0_linear_471_510"
                        x1="1815.5"
                        y1="498.5"
                        x2="1573.51"
                        y2="123.192"
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

        <div class="container relative">
            <figure
                class="relative z-2 w-full translate-y-4 rounded-3xl border-[15px] border-solid border-white/60 opacity-0 shadow-[0_100px_200px_-50px_rgba(0,0,0,0.1)] transition-all delay-300 duration-500 group-[&.lqd-is-in-view]:translate-y-0 group-[&.lqd-is-in-view]:opacity-100"
            >
                <img
                    class="w-full"
                    width="2880"
                    height="1750"
                    src="{{ $fSetting->hero_image }}"
                    alt="{{ __('Image of ' . $setting->site_name . ' dashboard') }}"
                >
            </figure>
        </div>
    </section>
@endif
