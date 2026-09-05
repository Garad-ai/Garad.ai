<div class="lqd-cs-predefined-artboard py-9">
    <div class="mb-6 flex items-center justify-between gap-3">
        <h2 class="mb-0">
            @lang('Pre-defined Artboards')
        </h2>

        <x-button
            class="text-2xs font-medium opacity-80 hover:opacity-100"
            variant="link"
            href="#"
            @click.prevent="switchView('editor'); $nextTick(() => {activeTool = 'resize';})"
        >
            @lang('View All')
            <x-tabler-chevron-right class="size-4" />
        </x-button>
    </div>

    @if (isset($sizes) && filled($sizes))
        <div
            class="lqd-cs-predefined-artboard-grid grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-3 md:gap-x-6 lg:grid-cols-5 lg:gap-x-11 [&_.image-result:nth-child(n+7)]:hidden">
            @foreach ($sizes as $size)
                @continue(!isset($size['featured']) || !$size['featured'])

                <div class="lqd-adv-editor-sizes-grid-item group/item relative">
                    <div class="relative mb-4 grid w-full items-end overflow-hidden rounded-lg transition-all group-hover/item:-translate-y-1">
                        <img
                            class="h-auto w-full transition-all group-hover/item:scale-105"
                            src="{{ $size['image'] }}"
                            alt="{{ $size['label'] }}"
                        />
                    </div>
                    <h5 class="mb-0 text-xs">
                        {{ $size['label'] }}
                    </h5>
                    <p class="m-0 opacity-70">
                        {{ $size['aspect'] }}
                    </p>

                    <a
                        class="absolute inset-0"
                        href="#"
                        @click.prevent="resetCanvas(); $nextTick(() => {handleStageResize({ width: {{ $size['width'] }}, height: {{ $size['height'] }} }); showWelcomeScreen = false; switchView('editor') })"
                    >
                        <span class="sr-only">{{ __('Apply pre-defined size') }}</span>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-16 flex flex-wrap gap-1 sm:gap-3">
            @foreach ($sizes as $size)
                <x-button
                    class="bg-foreground/5 text-foreground max-sm:p-2 max-sm:text-2xs"
                    hover-variant="primary"
                    size="lg"
                    href="#"
                    @click.prevent="resetCanvas(); $nextTick(() => {handleStageResize({ width: {{ $size['width'] }}, height: {{ $size['height'] }} }); showWelcomeScreen = false; switchView('editor') })"
                >
                    {{ $size['label'] }}
                </x-button>
            @endforeach
        </div>
    @endif
</div>
