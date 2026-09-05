<div
    class="group relative flex w-24 min-w-full font-semibold uppercase"
    x-data="dynamicInput({
        relativeValue: () => selectedNodes.length > 1,
        min: 0,
        onInput(value) { selectedNodesProps = { cornerRadius: value } },
    })"
    @nodes-selected.window="updateValue(selectedNodesProps.cornerRadius, true, false);"
    :class="{ 'opacity-50 pointer-events-none': !selectedNodes.find(n => ['Rect', 'Image'].includes(n.getClassName())) }"
>
    <span
        class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
        x-ref="dynamicLabel"
    >
        <x-tabler-border-corners class="size-4" />
    </span>
    <input
        class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
        x-ref="dynamicInput"
    >
</div>
