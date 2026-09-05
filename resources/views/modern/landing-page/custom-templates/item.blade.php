<div
    class="lqd-template-item group relative m-px my-10 flex shrink-0 basis-auto flex-col items-center gap-3 overflow-hidden rounded-2xl p-8 text-center text-xs font-semibold text-white transition-all hover:scale-105 hover:bg-white hover:text-black hover:shadow-lg hover:shadow-white/10 2xl:w-[13vw]">
    <div class="size-12 inline-grid place-content-center rounded-lg bg-white/[8%] transition-all group-hover:bg-black/5">
        <span class="w-6 [&_svg]:h-auto [&_svg]:max-h-6 [&_svg]:w-full">
            {!! $item->image !!}
        </span>
    </div>
    <p>
        {{ __($item->title) }}
    </p>
</div>
