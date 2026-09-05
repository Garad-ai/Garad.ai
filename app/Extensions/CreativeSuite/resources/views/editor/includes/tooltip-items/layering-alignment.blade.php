<div class="space-y-2">
    <div class="grid grid-cols-2 gap-0.5 border-b pb-2">
        <p class="col-span-2 mb-0.5 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Layering') }}
        </p>

        <button
            class="col-span-1 inline-flex w-full place-items-center gap-1 rounded-s-md bg-foreground/5 p-1 text-3xs font-medium transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
            :disabled="!selectedNodes.length || nodes.length <= 1"
            @click.prevent="moveNode('moveUp')"
            type="button"
        >
            <x-tabler-chevron-up class="size-4" />
            {{ __('Up') }}
        </button>
        <button
            class="col-span-1 inline-flex w-full place-items-center gap-1 rounded-e-md bg-foreground/5 p-1 text-3xs font-medium transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
            :disabled="!selectedNodes.length || nodes.length <= 1"
            @click.prevent="moveNode('moveDown')"
            type="button"
        >
            <x-tabler-chevron-down class="size-4" />
            {{ __('Down') }}
        </button>

        <button
            class="col-span-1 inline-flex w-full place-items-center gap-1 rounded-s-md bg-foreground/5 p-1 text-3xs font-medium transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
            :disabled="!selectedNodes.length || nodes.length <= 1"
            @click.prevent="moveNode('moveToTop')"
            type="button"
        >
            <x-tabler-chevrons-up class="size-4" />
            {{ __('To Up') }}
        </button>
        <button
            class="col-span-1 inline-flex w-full place-items-center gap-1 rounded-e-md bg-foreground/5 p-1 text-3xs font-medium transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
            :disabled="!selectedNodes.length || nodes.length <= 1"
            @click.prevent="moveNode('moveToBottom')"
            type="button"
        >
            <x-tabler-chevrons-down class="size-4" />
            {{ __('To Bottom') }}
        </button>
    </div>

    <div class="grid grid-cols-2 gap-1">
        <p class="col-span-2 mb-0.5 cursor-default text-4xs font-semibold opacity-85">
            {{ __('Alignment') }}
        </p>

        <div class="grid grid-cols-3 gap-px">
            <button
                class="inline-grid h-6 w-full place-items-center rounded-s-md bg-foreground/5 transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="!selectedNodes.length"
                @click.prevent="alignSelectedNodes('left')"
                type="button"
            >
                <x-tabler-layout-align-left class="size-4" />
            </button>
            <button
                class="inline-grid h-6 w-full place-items-center rounded-none bg-foreground/5 transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="!selectedNodes.length"
                @click.prevent="alignSelectedNodes('center')"
                type="button"
            >
                <x-tabler-layout-align-center class="size-4" />
            </button>
            <button
                class="inline-grid h-6 w-full place-items-center rounded-e-md bg-foreground/5 transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="!selectedNodes.length"
                @click.prevent="alignSelectedNodes('right')"
                type="button"
            >
                <x-tabler-layout-align-right class="size-4" />
            </button>
        </div>
        <div class="grid grid-cols-3 gap-px">
            <button
                class="inline-grid h-6 w-full place-items-center rounded-s-md bg-foreground/5 transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="!selectedNodes.length"
                @click.prevent="alignSelectedNodes('top')"
                type="button"
            >
                <x-tabler-layout-align-top class="size-4" />
            </button>
            <button
                class="inline-grid h-6 w-full place-items-center rounded-none bg-foreground/5 transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="!selectedNodes.length"
                @click.prevent="alignSelectedNodes('middle')"
                type="button"
            >
                <x-tabler-layout-align-middle class="size-4" />
            </button>
            <button
                class="inline-grid h-6 w-full place-items-center rounded-e-md bg-foreground/5 transition-all hover:bg-primary hover:text-primary-foreground disabled:pointer-events-none disabled:opacity-50"
                :disabled="!selectedNodes.length"
                @click.prevent="alignSelectedNodes('bottom')"
                type="button"
            >
                <x-tabler-layout-align-bottom class="size-4" />
            </button>
        </div>
    </div>
</div>
