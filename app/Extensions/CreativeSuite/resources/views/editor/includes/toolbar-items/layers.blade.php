<form class="relative mb-3">
    <x-tabler-search class="absolute start-3 top-1/2 z-2 size-4 -translate-y-1/2" />
    <x-forms.input
        class="ps-8"
        type="search"
        placeholder="{{ __('Search for layers') }}"
        size="sm"
        x-model.throttle.50ms="layersSearchString"
    />
</form>

<div x-ref="layers">
    {{-- TODO: use svg <use> for icons --}}
    <template
        class="lqd-cs-layers-list-template"
        x-for="(node, index) in nodes.reverse()"
        :key="node.id()"
    >
        <div
            class="lqd-cs-layer flex cursor-default items-center gap-1.5 rounded-md p-1 transition-colors [&.selected]:bg-foreground/5"
            x-show="node.name().toLowerCase().includes(layersSearchString.trim().toLowerCase()) || node.getType().toLowerCase().includes(layersSearchString.trim().toLowerCase())"
            :class="{ 'selected': selectedNodes.find(n => n.id() === node.id()) }"
            :data-id="node.id()"
        >
            <span
                class="lqd-cs-layer-handle relative inline-grid size-5 shrink-0 cursor-grab select-none place-items-center before:absolute before:start-1/2 before:top-1/2 before:size-10 before:-translate-x-1/2 before:-translate-y-1/2 active:cursor-grabbing"
            >
                <x-tabler-grip-vertical class="size-4" />
            </span>
            <span
                class="pointer-events-none opacity-60"
                x-text="node.getType()"
            ></span>

            <span class="grow font-medium">
                <input
                    class="text-ellipse inline-block w-full overflow-hidden border-none bg-transparent bg-none p-0"
                    type="text"
                    :value="node.name()"
                    @change="node.name($event.target.value)"
                    @keydown.enter="$event.target.blur()"
                    @keydown.esc="$event.target.blur()"
                    @focus="$event.target.select()"
                />
            </span>

            <div class="ms-auto flex gap-1.5">
                <x-button
                    class="relative size-8 shrink-0"
                    size="none"
                    variant="outline"
                    @click.prevent="node.visible(!node.visible()); node.draggable(node.visible()); node.setAttr('locked', !node.visible())"
                    title="{{ __('Toggle Visibility') }}"
                >
                    <x-tabler-eye class="pointer-events-none size-4" />
                    <span
                        class="pointer-events-none absolute left-1/2 top-1/2 inline-block h-4 w-[1.5px] -translate-x-1/2 -translate-y-1/2 -rotate-45 rounded bg-current"
                        x-show="!node.visible()"
                    ></span>
                </x-button>

                <x-button
                    class="relative ms-auto size-8 shrink-0"
                    size="none"
                    variant="outline"
                    @click.prevent="node.setAttr('locked', !node.getAttr('locked')); node.draggable(false); deselectNodes({id: node.id()});"
                    title="{{ __('Lock/Unlock Layer') }}"
                >
                    <x-tabler-lock class="pointer-events-none size-4" />
                    <span
                        class="pointer-events-none absolute left-1/2 top-1/2 inline-block h-4 w-[1.5px] -translate-x-1/2 -translate-y-1/2 -rotate-45 rounded bg-current"
                        x-show="node.visible() && node.getAttr('locked')"
                    ></span>
                </x-button>

                <x-button
                    class="ms-auto inline-grid size-8 shrink-0 place-items-center"
                    size="none"
                    variant="outline"
                    hover-variant="danger"
                    @click.prevent="destroyNodes({id: node.id()})"
                >
                    <x-tabler-trash class="pointer-events-none size-4" />
                </x-button>
            </div>
        </div>
    </template>
</div>
