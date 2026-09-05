<p class="m-0 cursor-default text-4xs font-semibold opacity-85">
    {{ __('Dimensions') }}
</p>
<div
    class="grid grid-cols-2 gap-1"
    :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
>
    @foreach (['w', 'h'] as $d)
        <div
            class="group relative flex w-16 min-w-full font-semibold uppercase"
            x-data="dynamicInput({
                relativeValue: () => selectedNodes.length > 1,
                min: 1,
                onInput(value) { selectedNodesProps = { {{ $d === 'w' ? 'width' : 'height' }}: value } },
            })"
            @nodes-selected.window="updateValue(selectedNodesProps.{{ $d === 'w' ? 'width' : 'height' }}, true, false);"
        >
            <span
                class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                x-ref="dynamicLabel"
            >
                {{ __($d) }}
            </span>
            <input
                class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                x-ref="dynamicInput"
            >
        </div>
    @endforeach
</div>
