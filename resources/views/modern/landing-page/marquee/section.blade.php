<section class="site-section relative pb-16 pt-20 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100">
    <div
        class="pointer-events-none"
        aria-hidden="true"
    >
        <figure
            class="absolute start-0 top-1/4 z-0 w-full overflow-hidden"
            style="mask-image: linear-gradient(to bottom, transparent, black 20%)"
        >
            <img
                class="w-full"
                src="{{ custom_theme_url('/assets/landing-page/bg-marquee.jpg') }}"
                width="2880"
                height="1286"
            />
        </figure>
    </div>
    <div style="mask-image: linear-gradient(to right, transparent, black 25%, black 75%, transparent)">
        <marquee behavior="alternate">
            <div class="flex items-center gap-8 whitespace-nowrap">
                @foreach ($top_marquee_items as $item)
                    <span class="text-[13vw] leading-[1.15em] text-heading-foreground">
                        {{ __($item) }}
                    </span>
                @endforeach
            </div>
        </marquee>
        <marquee
            behavior="alternate"
            direction="right"
            scrolldelay="50"
        >
            <div class="flex items-center gap-8 whitespace-nowrap">
                @foreach ($bottom_marquee_items as $item)
                    <span
                        class="text-[13vw] leading-[1.15em] text-transparent"
                        style="-webkit-text-stroke: 1px; -webkit-text-stroke-color: hsl(var(--heading-foreground))"
                    >
                        {{ __($item) }}
                    </span>
                @endforeach
            </div>
        </marquee>
    </div>
</section>
