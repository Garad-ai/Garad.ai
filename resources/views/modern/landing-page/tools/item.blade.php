<div
    @class([
        'flex flex-col px-4 w-full text-white',
        'lg:w-1/2' => $w_half,
        'lg:w-1/4' => $w_fourth,
    ])
    x-data="{
        showingDetails: false,
    }"
>
    <div class="relative flex min-h-[180px] grow flex-col justify-between overflow-hidden rounded-xl border border-white/[3%] px-8 pb-6 pt-8">
        <p
            class="text-[19px] font-semibold leading-6 transition-all [&.inactive]:scale-90"
            :class="{ inactive: showingDetails }"
        >
            @php
                $titleWords = explode(' ', $item->title);
                $firstWord = $titleWords[0];
                $remainingWords = implode(' ', array_slice($titleWords, 1));
            @endphp
            {!! __($firstWord) !!}
            <br>
            <span class="text-gradient">
                {!! __($remainingWords) !!}
            </span>
        </p>
        <button
            class="size-[50px] group/btn relative z-2 inline-grid place-content-center self-end rounded-full border border-white/[3%] transition-all [&.active]:border-background [&.active]:bg-background [&.active]:text-heading-foreground"
            :class="{ active: showingDetails }"
            @click="showingDetails = !showingDetails"
        >
            <x-tabler-plus class="size-4 group-[&.active]/btn:hidden" />
            <x-tabler-minus class="size-4 hidden group-[&.active]/btn:block" />
        </button>
        <div
            class="invisible absolute start-0 top-0 z-1 h-full w-full scale-105 overflow-y-auto bg-[--bg] p-8 pb-16 text-sm opacity-0 transition-all [&.active]:visible [&.active]:scale-100 [&.active]:opacity-100"
            :class="{ active: showingDetails }"
        >
            <p>
                {!! __($item->description) !!}
            </p>
        </div>
    </div>
</div>
{{-- <img
    class="-mx-8 max-w-[calc(100%+4rem)]"
    src="{{ custom_theme_url($item->image, true) }}"
    alt="{!! __($item->title) !!}"
    width="696"
    height="426"
> --}}
