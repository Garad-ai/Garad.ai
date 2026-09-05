<div class="space-y-1">
    <div>
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Stroke') }}
        </p>

        <div class="grid grid-cols-2 gap-1">
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 0,
                    onInput(value) { selectedNodesProps = { strokeWidth: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.strokeWidth, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-border-sides class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>

            <div
                class="flex items-center"
                x-data="liquidColorPicker({ colorVal: selectedNodesProps.stroke })"
            >
                <span
                    class="lqd-input-color-wrap !size-[26px] shrink-0 rounded-full border-[3px] border-background shadow-md shadow-black/10"
                    x-ref="colorInputWrap"
                    :style="{ backgroundColor: colorVal }"
                ></span>
                <input
                    class="invisible w-0 border-none bg-transparent p-0 text-3xs font-medium focus:outline-none"
                    type="text"
                    size="sm"
                    value="#ffffff"
                    x-ref="colorInput"
                    :value="selectedNodesProps.stroke"
                    @input="selectedNodesProps = { stroke: $event.target.value }"
                    @change="picker.setColor($event.target.value)"
                    @keydown.enter.prevent="picker?.setColor($event.target.value);"
                    @focus="picker.open(); $el.select();"
                    x-effect="selectedNodes.length === 1 && $nextTick(() => picker.setColor(selectedNodesProps.stroke))"
                />
            </div>
        </div>
    </div>

    <div>
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Skew') }}
        </p>

        <div
            class="grid grid-cols-2 gap-1"
            :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
        >
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    step: 0.01,
                    onInput(value) { selectedNodesProps = { skewX: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.skewX, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-skew-x class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    step: 0.01,
                    onInput(value) { selectedNodesProps = { skewY: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.skewY, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-skew-y class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="selectedNodes.find(n => n.getClassName() === 'RegularPolygon')"
    >
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Polygon') }}
        </p>
        <div
            class="grid grid-cols-2 gap-1"
            :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
        >
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 3,
                    onInput(value) { selectedNodesProps = { sides: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.sides, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-polygon class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="selectedNodes.find(n => n.getClassName() === 'Star')"
    >
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Star') }}
        </p>
        <div
            class="grid grid-cols-3 gap-1"
            :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
        >
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 3,
                    onInput(value) { selectedNodesProps = { numPoints: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.numPoints, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-topology-star-3 class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 1,
                    onInput(value) { selectedNodesProps = { innerRadius: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.innerRadius, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-topology-star-3 class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 1,
                    onInput(value) { selectedNodesProps = { outerRadius: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.outerRadius, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-michelin-star class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="selectedNodes.find(n => n.getClassName() === 'Arc')"
    >
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Arc') }}
        </p>
        <div
            class="grid grid-cols-3 gap-1"
            :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
        >
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 0,
                    max: 360,
                    onInput(value) { selectedNodesProps = { angle: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.angle, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-angle class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 1,
                    onInput(value) { selectedNodesProps = { innerRadius: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.innerRadius, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-asterisk class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 1,
                    onInput(value) { selectedNodesProps = { outerRadius: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.outerRadius, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-michelin-star class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="selectedNodes.find(n => n.getClassName() === 'Ring')"
    >
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Ring') }}
        </p>
        <div
            class="grid grid-cols-3 gap-1"
            :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
        >
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 1,
                    onInput(value) { selectedNodesProps = { innerRadius: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.innerRadius, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-asterisk class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 1,
                    onInput(value) { selectedNodesProps = { outerRadius: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.outerRadius, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-michelin-star class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="selectedNodes.find(n => n.getClassName() === 'Wedge')"
    >
        <p class="m-0 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Wedge') }}
        </p>
        <div
            class="grid grid-cols-3 gap-1"
            :class="{ 'opacity-50 pointer-events-none': !selectedNodes.length }"
        >
            <div
                class="group relative flex w-16 min-w-full font-semibold uppercase"
                x-data="dynamicInput({
                    relativeValue: () => selectedNodes.length > 1,
                    min: 0,
                    max: 360,
                    onInput(value) { selectedNodesProps = { angle: value } },
                })"
                @nodes-selected.window="updateValue(selectedNodesProps.angle, true, false);"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-angle class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                >
            </div>
        </div>
    </div>
</div>
