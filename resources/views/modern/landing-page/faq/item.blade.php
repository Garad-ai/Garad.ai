<div>
    <button
        class="group/btn flex w-full items-center justify-between px-10 py-8 text-start text-[18px] font-semibold leading-[1.15em] text-heading-foreground transition-all max-md:px-5 [&.lqd-is-active]:border-transparent"
        data-target="#faq-{{ $item->id }}"
        data-trigger-type="accordion"
    >
        <span class="group-[&.lqd-is-active]/btn:text-gradient">
            {!! $item->question !!}
        </span>
        <span
            class="size-[50px] before:bg-gradient max-md:size-11 relative ms-auto inline-grid shrink-0 place-content-center overflow-hidden rounded-full border border-heading-foreground/10 transition-all before:absolute before:inset-0 before:z-0 before:opacity-0 group-[&.lqd-is-active]/btn:before:opacity-20"
        >
            <span class="relative z-1 group-[&.lqd-is-active]/btn:hidden">
                <x-tabler-plus
                    class="size-6"
                    stroke-width="1.5"
                />
            </span>
            <span class="relative z-1 hidden group-[&.lqd-is-active]/btn:block">
                <x-tabler-minus
                    class="size-6"
                    stroke-width="1.5"
                />
            </span>
        </span>
    </button>
    <div
        class="hidden px-10 max-md:px-5"
        id="faq-{{ $item->id }}"
    >
        <div class="lqd-accordion-content">
            <p class="text-lg/6">{!! $item->answer !!}</p>
        </div>
    </div>
</div>
