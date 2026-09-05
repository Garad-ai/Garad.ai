@php
    $tools = [
        'templates' => [
            'title' => __('Library'),
            'icon' => 'tabler-layout-collage',
            'template' => 'creative-suite::editor.includes.toolbar-items.templates',
        ],
        'text' => [
            'title' => __('Text'),
            'icon' => 'tabler-letter-t',
            'element' => [
                'type' => 'Text',
                'attrs' => [
                    'x' => 'center',
                    'y' => 'middle',
                ],
            ],
        ],
        'image' => [
            'title' => __('Image'),
            'icon' => 'tabler-photo',
            'element' => [
                'type' => 'Image',
                'attrs' => [
                    'width' => 200,
                    'height' => 200,
                    'x' => 'center',
                    'y' => 'middle',
                ],
            ],
        ],
        'elements' => [
            'title' => __('Symbols'),
            'icon' => 'tabler-triangle-square-circle',
            'template' => 'creative-suite::editor.includes.toolbar-items.elements',
        ],
        'resize' => [
            'title' => __('Size'),
            'icon' => 'tabler-resize',
            'template' => 'creative-suite::editor.includes.toolbar-items.resize',
        ],
        'layers' => [
            'title' => __('Layers'),
            'icon' => 'tabler-stack-3',
            'template' => 'creative-suite::editor.includes.toolbar-items.layers',
        ],
    ];
@endphp

<div @click.outside="activeTool = null">
    <div
        class="lqd-cs-toolbar fixed bottom-0 start-0 top-[--header-h] z-20 flex w-[--sidebar-w] flex-col gap-5 overflow-y-auto bg-background/90 p-4 shadow-lg shadow-black/5 backdrop-blur-xl backdrop-saturate-[120%] transition-all max-lg:bottom-0 max-lg:end-0 max-lg:start-0 max-lg:top-auto max-lg:h-[--sidebar-h] max-lg:w-full max-lg:flex-row max-lg:items-center max-lg:justify-between max-lg:overflow-x-auto max-lg:px-5 max-lg:py-3">
        @foreach ($tools as $key => $tool)
            <button
                class="group flex flex-col items-center justify-center gap-1.5 text-center text-4xs font-medium"
                {{-- blade-formatter-disable --}}
				@if (@filled($tool['template']))
					@click.prevent="$nextTick(() => activeTool = activeTool === '{{ $key }}' ? null : '{{ $key }}')"
					:class="{ 'active': activeTool === '{{ $key }}' }"
				@endif
				@if (@filled($tool['element']))
					@click.prevent="const node = addNodeToStage({ type: '{{ $tool['element']['type'] }}', attrs: {{ Js::from($tool['element']['attrs'] ?? [[]]) }} }); $nextTick(() => container.focus()); node.getClassName() === 'Image' && (activeFillTab = 'image')"
					@dragstart.self="$event.dataTransfer.setData('data', JSON.stringify({type: '{{ $tool['element']['type'] }}', attrs: {{ Js::from($tool['element']['attrs'] ?? [[]]) }}}));"
					draggable="true"
				@endif
				{{-- blade-formatter-enable --}}
            >
                @if (@filled($tool['icon']))
                    <span
                        class="inline-grid size-11 place-items-center rounded-full border transition-colors group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground"
                    >
                        <x-dynamic-component
                            class="size-5 stroke-[1.5px] transition-transform group-hover:scale-110"
                            :component="$tool['icon']"
                        />
                    </span>
                @endif
                {{ $tool['title'] }}
            </button>
        @endforeach
    </div>

    <div
        class="lqd-cs-tools-panel pointer-events-none invisible fixed bottom-0 start-[--sidebar-w] top-[--header-h] z-20 w-[--toolspanel-w] -translate-x-1/2 bg-background/90 opacity-0 shadow-lg shadow-black/5 backdrop-blur-xl backdrop-saturate-[120%] transition-all max-lg:bottom-[--sidebar-h] max-lg:top-[calc(var(--header-h)+3rem)] max-lg:w-screen max-lg:translate-x-0 max-lg:translate-y-8"
        :class="{ 'pointer-events-none opacity-0 invisible -translate-x-1/2 max-lg:translate-y-8': !activeTool }"
    >
        <x-button
            class="absolute end-0 top-9 z-10 size-[38px] translate-x-1/2 bg-background max-lg:-top-5 max-lg:end-5 max-lg:translate-x-0"
            variant="outline"
            size="none"
            hover-variant="primary"
            @click.prevent="activeTool = null; $nextTick(() => container.focus())"
            ::class="{ 'rotate-180': activeTool }"
        >
            <x-tabler-chevron-right class="size-4 max-lg:-rotate-90" />
        </x-button>

        <div class="grid h-full grid-cols-1 place-items-start overflow-y-scroll p-4 px-6 py-7">
            @foreach ($tools as $key => $tool)
                @continue(!@filled($tool['template']))

                <div
                    class="col-start-1 col-end-1 row-start-1 row-end-1 flex min-h-full w-full origin-top flex-col"
                    x-show="activeTool === '{{ $key }}'"
                    x-transition:enter="transition duration-150"
                    x-transition:enter-start="opacity-0 scale-[0.99]"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-[0.99]"
                >
                    @include($tool['template'])
                </div>
            @endforeach
        </div>
    </div>
</div>
