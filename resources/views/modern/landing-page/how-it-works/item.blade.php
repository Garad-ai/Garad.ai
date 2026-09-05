<div class="group relative z-1 flex flex-col">
    <div class="relative mb-16 lg:before:absolute lg:before:-end-36 lg:before:start-8 lg:before:top-1/2 lg:before:h-px lg:before:bg-black/5 lg:group-last:before:content-none">
        <span class="size-8 relative inline-grid place-content-center rounded-full border border-black/5 text-xs text-heading-foreground">
            {{ __($item->order) }}
        </span>
    </div>
    <h5 class="mb-4 mt-1">
        {!! __($item->title) !!}
    </h5>
    <p class="mb-0">
        {!! __($item->description) !!}
    </p>
</div>
