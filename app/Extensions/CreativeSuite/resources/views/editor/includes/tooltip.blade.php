<div
    class="lqd-creative-suite-tooltip absolute left-0 top-0 z-9 flex -translate-x-1/2 items-center gap-0.5 rounded-md bg-background/95 p-1 text-2xs font-medium shadow-lg shadow-black/5"
    x-ref="tooltip"
    x-cloak
    x-show="selectedNodes.length && !editingTextNode"
    @nodes-selected.window="setActiveTooltipDropdown(null)"
>
    {{-- <button
        class="flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors hover:bg-foreground/10"
        title="{{ __('Group') }}"
        x-show="selectedNodes.every(n => n.getType() === 'Group')"
        @click.prevent="ungroupSelectedNodes"
    >
        {{ __('Ungroup') }}
    </button>
    <button
        class="flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors hover:bg-foreground/10"
        title="{{ __('Group') }}"
        x-show="selectedNodes.length > 1"
        @click.prevent="groupSelectedNodes"
    >
        {{ __('Group') }}
    </button>

    <span
        class="inline-block h-4 w-px bg-foreground/15"
        x-show="selectedNodes.length > 1"
    ></span> --}}

    <div class="relative">
        <button
            class="relative flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors before:pointer-events-none before:invisible before:absolute before:bottom-full before:left-1/2 before:mb-1 before:-translate-x-1/2 before:whitespace-nowrap before:rounded before:bg-background before:p-1 before:text-3xs/none before:shadow-md before:shadow-black/5 before:content-[attr(title)] hover:bg-foreground/10 hover:before:visible [&.open]:bg-foreground/10"
            title="{{ __('Properties') }}"
            :class="{ 'active': 'properties' }"
            @click.prevent="setActiveTooltipDropdown('properties')"
        >
            <x-tabler-dots-circle-horizontal class="size-4" />
        </button>
        <div
            class="absolute left-1/2 top-full mt-2 w-60 max-w-[100vw] -translate-x-1/2 rounded-md bg-background/90 p-1 shadow-lg shadow-black/5 backdrop-blur-md"
            x-cloak
            x-show="activeTooltipDropdown === 'properties'"
        >
            @include('creative-suite::editor.includes.tooltip-items.properties')
        </div>
    </div>

    <div class="relative">
        <button
            class="relative flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors before:pointer-events-none before:invisible before:absolute before:bottom-full before:left-1/2 before:mb-1 before:-translate-x-1/2 before:whitespace-nowrap before:rounded before:bg-background before:p-1 before:text-3xs/none before:shadow-md before:shadow-black/5 before:content-[attr(title)] hover:bg-foreground/10 hover:before:visible [&.open]:bg-foreground/10"
            title="{{ __('Layering & Alignment') }}"
            :class="{ 'active': 'layering-alignment' }"
            @click.prevent="setActiveTooltipDropdown('layering-alignment')"
        >
            <x-tabler-stack-front class="size-4" />
        </button>
        <div
            class="absolute left-1/2 top-full mt-2 w-60 max-w-[100vw] -translate-x-1/2 rounded-md bg-background/90 p-1 shadow-lg shadow-black/5 backdrop-blur-md"
            x-cloak
            x-show="activeTooltipDropdown === 'layering-alignment'"
        >
            @include('creative-suite::editor.includes.tooltip-items.layering-alignment')
        </div>
    </div>

    <div
        class="relative"
        x-show="selectedNodes.some(n => ['Rect', 'Image'].includes(n.getClassName()))"
    >
        <button
            class="flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors before:pointer-events-none before:invisible before:absolute before:bottom-full before:left-1/2 before:mb-1 before:-translate-x-1/2 before:whitespace-nowrap before:rounded before:bg-background before:p-1 before:text-3xs/none before:shadow-md before:shadow-black/5 before:content-[attr(title)] hover:bg-foreground/10 hover:before:visible [&.open]:bg-foreground/10"
            title="{{ __('Corner Radius') }}"
            :class="{ 'active': 'cornerRadius' }"
            @click.prevent="setActiveTooltipDropdown('cornerRadius')"
        >
            <x-tabler-border-corners class="size-4" />
        </button>
        <div
            class="absolute left-1/2 top-full mt-2 -translate-x-1/2 rounded-md bg-background p-1 shadow-lg shadow-black/5"
            x-cloak
            x-show="activeTooltipDropdown === 'cornerRadius'"
        >
            @include('creative-suite::editor.includes.tooltip-items.corner-radius')
        </div>
    </div>

    <button
        class="relative flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors before:pointer-events-none before:invisible before:absolute before:bottom-full before:left-1/2 before:mb-1 before:-translate-x-1/2 before:whitespace-nowrap before:rounded before:bg-background before:p-1 before:text-3xs/none before:shadow-md before:shadow-black/5 before:content-[attr(title)] hover:bg-foreground/10 hover:before:visible"
        title="{{ __('Duplicate') }}"
        @click.prevent="cloneSelectedNodes"
    >
        <x-tabler-copy class="size-4" />
    </button>

    <div class="relative">
        <button
            class="relative flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors before:pointer-events-none before:invisible before:absolute before:bottom-full before:left-1/2 before:mb-1 before:-translate-x-1/2 before:whitespace-nowrap before:rounded before:bg-background before:p-1 before:text-3xs/none before:shadow-md before:shadow-black/5 before:content-[attr(title)] hover:bg-foreground/10 hover:before:visible [&.open]:bg-foreground/10"
            title="{{ __('Opacity') }}"
            :class="{ 'active': 'opacity' }"
            @click.prevent="setActiveTooltipDropdown('opacity')"
        >
            <x-tabler-brightness class="size-4" />
        </button>
        <div
            class="absolute left-1/2 top-full mt-2 -translate-x-1/2 rounded-md bg-background p-1 shadow-lg shadow-black/5"
            x-cloak
            x-show="activeTooltipDropdown === 'opacity'"
        >
            @include('creative-suite::editor.includes.tooltip-items.opacity')
        </div>
    </div>

    <button
        class="relative flex min-h-6 min-w-6 items-center justify-center gap-2 rounded px-1 text-center transition-colors before:pointer-events-none before:invisible before:absolute before:bottom-full before:left-1/2 before:mb-1 before:-translate-x-1/2 before:whitespace-nowrap before:rounded before:bg-background before:p-1 before:text-3xs/none before:shadow-md before:shadow-black/5 before:content-[attr(title)] hover:bg-foreground/10 hover:before:visible"
        title="{{ __('Delete') }}"
        @click.prevent="destroySelectedNodes"
    >
        <x-tabler-circle-minus class="size-4" />
    </button>
</div>
