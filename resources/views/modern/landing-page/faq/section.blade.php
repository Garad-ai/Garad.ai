{!! adsense_faq_728x90() !!}
<section
    class="site-section relative pb-36 pt-24 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="faq"
>
    <div class="absolute start-0 top-0 h-full w-full overflow-hidden">
        <svg
            class="absolute start-1/3 top-0 z-0 -translate-x-1/2"
            width="4709"
            height="794"
            viewBox="0 0 4709 794"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M3881.64 12.1768C4121.89 -50.1855 4174.1 161.442 4411.2 212.119C4714.96 277.036 4784.48 376.652 4628.27 457.061C4472.1 537.47 4134.71 607.555 3788.36 660.249C3440.99 713.105 3037.84 752.427 2608.67 774.378C2151.82 797.742 1640.1 804.051 1200.44 765.442C807.576 730.937 525.657 663.562 490.099 580.035C454.915 497.356 620.15 407.934 905.088 345.721C1118.16 299.2 1545.23 246.286 1869.29 286.465C2016.51 304.712 2035.78 338.906 1970.57 368.034C1884.52 406.468 1702.53 438.334 1536.85 464.402C1194.81 518.211 777.038 554.252 339.114 567.208C230.643 570.418 122.205 571.721 12.5459 571.417C-4.28299 571.365 -4.07941 566.637 12.5459 566.449C424.82 561.781 822.572 542.041 1181.61 502.375C1358.75 482.798 1523.11 459.032 1668.83 431.564C1807.67 405.385 2006.29 366.906 1965.38 325.787C1928.26 288.442 1711.72 278.125 1540.55 281.744C1348.47 285.803 1168.48 307.17 1029.47 331.876C735.714 384.09 559.892 462.268 529.017 538.151C494.816 622.379 688.111 698.197 1063.98 742.999C1448.02 788.786 1920.25 792.865 2355.39 777.801C3142.35 750.566 3871.76 663.621 4345.85 540.518C4493.34 502.213 4614.87 459.849 4658.17 412.538C4703.12 363.352 4668.89 311.242 4557.74 265.993C4461.82 226.923 4301.71 190.156 4082.76 175.559C3875.25 161.721 4038.22 -18.7938 3898.5 14.6602C3891 16.4629 3874.44 14.0508 3881.64 12.1768Z"
                fill="url(#paint0_linear_481_7)"
            />
            <defs>
                <linearGradient
                    id="paint0_linear_481_7"
                    x1="2781"
                    y1="765.283"
                    x2="1733.99"
                    y2="159.33"
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
        <header class="relative mx-auto mb-14 w-full text-center lg:w-1/2">
            <h6 class="mb-5 inline-flex items-center rounded-full border px-3.5 py-1.5">
                {!! __($fSectSettings->faq_text_one) !!}
                <span class="dot"></span>
                <span>{!! __($fSectSettings->faq_text_two) !!}</span>
            </h6>
            <h2 class="mb-5">
                {!! __($fSectSettings->faq_title) !!}
            </h2>
            <p class="text-header-p">
                {!! $fSectSettings->faq_subtitle
                    ? __($fSectSettings->faq_subtitle)
                    : __('Our support team will get assistance from AI-powered suggestions, making it quicker than ever to handle support requests.') !!}
            </p>
        </header>

        <div class="lqd-accordion mx-auto w-full backdrop-blur-xl lg:w-10/12">
            @foreach ($faq as $item)
                @include('landing-page.faq.item')
            @endforeach
        </div>
    </div>
</section>
