<section class="site-section">
    <div
        class="pointer-events-none relative min-h-[400px] w-full overflow-hidden lg:-mt-52"
        data-lqd-throwable-scene="true"
        data-throwable-options='{"scrollGravity": true}'
    >
        @foreach ($who_is_for as $item)
            <p
                class="lqd-throwable-element pointer-events-auto absolute start-0 top-0 inline-flex select-none text-xs opacity-0 md:text-[19px]"
                data-lqd-throwable-el
            >
                <span class="lqd-throwable-element-rot inline-flex rounded-full bg-[#F5F5F5] px-6 py-3 text-heading-foreground md:px-9 md:py-4">
                    {!! __($item->title) !!}
                </span>
            </p>
        @endforeach
    </div>
</section>
