{!! adsense_testimonials_728x90() !!}
<section
    class="site-section relative py-24 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="testimonials"
>
    <div class="pointer-events-none w-full overflow-hidden">
        <svg
            class="absolute -top-36 end-0 z-0"
            width="3140"
            height="630"
            viewBox="0 0 3140 630"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M2315.39 591.415C2475.59 653.777 2783.32 632.502 2941.42 581.825C3143.98 516.907 3190.33 417.291 3086.17 336.882C2982.03 256.474 2757.06 186.388 2526.11 133.694C2294.48 80.8385 2025.66 41.516 1739.48 19.5657C1434.85 -3.79823 1093.63 -10.1077 800.465 28.5015C538.498 63.006 350.512 130.381 326.802 213.909C303.341 296.587 413.521 386.01 603.52 448.223C745.6 494.743 1030.37 547.657 1246.46 507.479C1344.62 489.231 1357.47 455.038 1313.99 425.909C1256.62 387.475 1135.26 355.61 1024.78 329.542C796.709 275.733 518.135 239.691 226.124 226.735C153.794 223.525 81.4869 222.222 8.36529 222.527C-2.85635 222.579 -2.7206 227.306 8.36529 227.494C283.273 232.163 548.497 251.902 787.907 291.568C906.029 311.145 1015.62 334.911 1112.79 362.38C1205.37 388.558 1337.81 427.038 1310.53 468.156C1285.78 505.501 1141.39 515.818 1027.25 512.2C899.174 508.14 779.152 486.773 686.461 462.067C490.58 409.853 373.341 331.675 352.752 255.793C329.947 171.564 458.838 95.7467 709.469 50.9447C965.553 5.15706 1280.44 1.07831 1570.59 16.142C2095.34 43.3772 2581.72 130.322 2897.85 253.426C2996.2 291.73 3077.23 334.094 3106.1 381.405C3136.08 430.591 3113.25 482.701 3039.14 527.951C2975.18 567.02 2868.41 603.788 2722.42 618.385C2584.05 632.223 2419.8 622.386 2326.63 588.932C2321.63 587.129 2310.59 589.541 2315.39 591.415Z"
                fill="url(#paint0_linear_479_6)"
            />
            <defs>
                <linearGradient
                    id="paint0_linear_479_6"
                    x1="2749.5"
                    y1="144.283"
                    x2="2447"
                    y2="604.283"
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
        <header class="relative mx-auto mb-4 w-full text-center lg:w-1/2">
            <h6 class="mb-5 inline-flex items-center rounded-full border px-3.5 py-1.5">
                {!! __($fSectSettings->testimonials_subtitle_one) !!}
                <span class="dot"></span>
                {!! __($fSectSettings->testimonials_subtitle_two) !!}
            </h6>
            <h2 class="mb-5">
                {!! __($fSectSettings->testimonials_title) !!}
            </h2>
            <p class="text-header-p mx-auto lg:w-3/4">
                {!! $fSectSettings->testimonials_description
                    ? __($fSectSettings->testimonials_description)
                    : __('From small businesses to enterprises, users from various industries depend on our technology to streamline their content generation.') !!}
            </p>
        </header>
    </div>

    <div>
        <div
            class="testimonials-main-carousel relative z-1 flex [&_.flickity-slider]:w-full [&_.flickity-viewport]:w-full lg:[&_.flickity-viewport]:[mask-image:linear-gradient(to_right,transparent,black_20%,black_80%,transparent)]"
            data-flickity='{ "wrapAround": true, "pageDots": true, "prevNextButtons": false}'
        >
            @foreach ($testimonials as $item)
                @include('landing-page.testimonials.item')
            @endforeach

        </div>
    </div>
</section>

<section class="site-section border-b py-10 text-center lg:pt-36 lg:text-start">
    <div class="container">
        <div class="flex flex-wrap justify-between gap-8 lg:flex-nowrap">
            <div class="w-full lg:w-1/4">
                <h4 class="mb-0 text-[24px]">
                    {{ __('Stay Connected') }}
                </h4>
            </div>
            <div class="mx-auto flex w-full items-center justify-between gap-4 text-center md:w-10/12 lg:w-2/4">
                @foreach (\App\Models\SocialMediaAccounts::where('is_active', true)->take(3)->get() as $social)
                    <a
                        class="flex items-center gap-5 text-2xs/4 text-heading-foreground lg:basis-1/3"
                        href="{{ $social['link'] }}"
                    >
                        <span class="w-7 text-[#476D83] [&_svg]:h-auto [&_svg]:w-full">
                            {!! $social['icon'] !!}
                        </span>
                        {{ __('Follow us on ') }}
                        {{ $social['title'] }}
                    </a>
                @endforeach
            </div>
            <div class="w-full text-2xs/4 text-black lg:w-1/4 lg:text-end">
                <p class="m-0">
                    {{ __('Follow us and Stay connected.') }}
                </p>
            </div>
        </div>
    </div>
</section>
1
