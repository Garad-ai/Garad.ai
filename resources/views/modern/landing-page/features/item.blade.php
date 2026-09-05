<div
    class="lqd-feature-box group rounded-xl border bg-white/5 px-6 py-8 backdrop-blur-md transition-all hover:-translate-y-1 hover:scale-105 hover:border-white hover:bg-white hover:shadow-xl hover:shadow-black/5">
    <div class="mb-5">
        <span class="inline-flex w-8 [&_svg]:h-auto [&_svg]:w-full">
            {!! $item->image !!}
        </span>
    </div>
    <h5 class="mb-4">
        {!! __($item->title) !!}
    </h5>
    <p class="m-0">
        {!! __($item->description) !!}
    </p>
</div>
