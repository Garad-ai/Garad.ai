<div
    class="lqd-tabs-content {{ !$loop->first ? 'hidden' : '' }}"
    id="{{ \Illuminate\Support\Str::slug($item->menu_title) }}"
>
    <div class="flex flex-wrap items-center justify-between gap-y-8">
        <div class="w-full lg:w-4/12">
            <h5 class="text-gradient mb-5 text-sm">
                {!! __($item->subtitle_one) !!}
                @if (!empty($item->subtitle_two))
                    <span class="dot bg-gradient"></span>
                    <span>{!! __($item->subtitle_two) !!}</span>
                @endif
            </h5>
            <h3 class="mb-5">
                {!! __($item->title) !!}
            </h3>
            <div class="mb-5 last:mb-0 [&_ul]:space-y-2">
                {!! __($item->text) !!}
            </div>
        </div>

        <div class="w-full lg:w-6/12">
            <figure class="w-full">
                <img
                    class="w-full rounded-xl transition-all duration-300 group-hover:-translate-y-2 group-hover:scale-[1.025] group-hover:shadow-[0px_20px_65px_rgba(0,0,0,0.05)]"
                    width="878"
                    height="748"
                    src="{{ custom_theme_url($item->image, true) }}"
                    alt="{{ __($item->image_title) }}"
                >
            </figure>
        </div>
    </div>
</div>
