@php
    $alignments = [
        'left' => [
            'label' => __('Align Left'),
            'icon' => 'tabler-align-left',
        ],
        'center' => [
            'label' => __('Align Center'),
            'icon' => 'tabler-align-center',
        ],
        'right' => [
            'label' => __('Align Right'),
            'icon' => 'tabler-align-right',
        ],
        // 'justify' => [
        //     'label' => __('Align Justify'),
        //     'icon' => 'tabler-align-justified',
        // ],
    ];
@endphp

<div
    class="flex gap-2.5"
    x-cloak
    x-show="selectedNodes.find(n => ['Text', 'TextPath'].includes(n.getClassName()))"
>
    <div
        class="relative col-span-2"
        @font-selected.window="selectedNodesProps = { fontFamily: $event.detail.font }"
        @click.outside="activeDropdown === 'typography' && (activeDropdown = null);"
    >
        <button
            class="flex w-full min-w-56 cursor-pointer items-center justify-between gap-1 rounded-xl border border-foreground/5 px-2 py-3 text-base font-medium leading-none text-foreground/90 transition-all hover:bg-foreground/[2%] sm:text-2xs"
            type="button"
            @click.prevent="activeDropdown = activeDropdown === 'typography' ? null : 'typography'"
            :style="{ 'fontFamily': selectedFont + ', sans-serif' }"
        >
            <span x-text="selectedFont || '{{ __('Choose a Font') }}'"></span>

            <x-tabler-chevron-down
                class="size-3.5 transition-transform"
                ::class="{ 'rotate-180': activeDropdown === 'typography' }"
            />
        </button>

        <div
            class="absolute -start-2 top-full z-9 mt-4 min-w-52 rounded-xl bg-background pb-3 text-xs shadow-lg shadow-black/15"
            x-cloak
            x-show="activeDropdown === 'typography'"
            x-transition
        >
            <form class="relative p-3">
                <x-tabler-search class="absolute start-6 top-1/2 size-4 -translate-y-1/2" />
                <input
                    class="w-full rounded-md border border-foreground/5 bg-input-background py-2 pe-2 ps-8 text-2xs"
                    type="search"
                    placeholder="{{ __('Search for a font') }}"
                    @input.debounce.50ms="$event.target.value.length>= 3 && onFontsSearchInput()"
                    x-model="fontsSearchString"
                    x-trap="activeDropdown === 'typography'"
                />
            </form>

            <ul class="max-h-80 overflow-y-auto overscroll-contain px-3">
                <template
                    x-for="(font, index) in showingGoogleFonts"
                    :key="font"
                >
                    <li
                        class="cursor-pointer rounded-md p-2 text-2xs font-medium text-foreground/90 transition-all hover:bg-foreground/[2%]"
                        @click="addToGoogleFontLoadQueue(font); selectedFont = font; activeDropdown === 'typography' && (activeDropdown = null);"
                        x-init="index <= 10 && addToGoogleFontLoadQueue(font, true)"
                        x-intersect:enter.once="addToGoogleFontLoadQueue(font, true)"
                        :style="{ 'fontFamily': font + ', sans-serif' }"
                    >
                        <span x-text="font"></span>
                    </li>
                </template>
                <li
                    class="min-h-px opacity-0"
                    x-show="!fontsSearchString.trim()"
                    x-intersect="loadMoreFonts"
                ></li>
                <li
                    class="cursor-default rounded-md p-2 text-2xs font-medium text-black/60 transition-all"
                    x-show="showingGoogleFonts.length === 0"
                >
                    {{ __('No fonts found') }}
                </li>
            </ul>
        </div>
    </div>

    <x-forms.input
        class="min-w-28 shrink-0 rounded-xl border border-foreground/10 bg-transparent sm:text-2xs"
        ::value="selectedNodesProps.fontStyle?.split(' ')?.at(1) || 400"
        @input="selectedNodesProps = { fontStyle: $event.target.value }"
        type="select"
        value="400"
    >
        <option value="300">{{ __('Light') }}</option>
        <option value="400">{{ __('Regular') }}</option>
        <option value="500">{{ __('Medium') }}</option>
        <option value="600">{{ __('SemiBold') }}</option>
        <option value="700">{{ __('Bold') }}</option>
        <option value="800">{{ __('ExtraBold') }}</option>
        <option value="900">{{ __('Black') }}</option>
    </x-forms.input>

    <div
        class="group relative flex w-16 shrink-0 text-3xs font-medium"
        x-data="dynamicInput({
            relativeValue: () => selectedNodes.length > 1,
            min: 5,
            onInput(value) { selectedNodesProps = { fontSize: value } },
        })"
        @nodes-selected.window="updateValue(selectedNodesProps.fontSize, true, false);"
    >
        <span
            class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
            x-ref="dynamicLabel"
        >
            <x-tabler-text-size class="size-4" />
        </span>
        <input
            class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-xl border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-2xs"
            x-ref="dynamicInput"
        >
    </div>

    <div
        class="group relative flex w-16 shrink-0 text-3xs font-medium"
        x-data="dynamicInput({
            relativeValue: () => selectedNodes.length > 1,
            onInput(value) { selectedNodesProps = { letterSpacing: value } },
        })"
        @nodes-selected.window="updateValue(selectedNodesProps.letterSpacing, true, false);"
    >
        <span
            class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
            x-ref="dynamicLabel"
        >
            <x-tabler-letter-spacing class="size-4" />
        </span>
        <input
            class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-xl border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-2xs"
            x-ref="dynamicInput"
        >
    </div>

    <div
        class="group relative flex w-16 shrink-0 text-3xs font-medium"
        x-data="dynamicInput({
            relativeValue: () => selectedNodes.length > 1,
            step: 0.1,
            onInput(value) { selectedNodesProps = { lineHeight: value } },
        })"
        @nodes-selected.window="updateValue(selectedNodesProps.lineHeight, true, false);"
        x-show="!selectedNodes.find(n => n.getClassName() === 'TextPath')"
    >
        <span
            class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
            x-ref="dynamicLabel"
        >
            <x-tabler-line-height class="size-4" />
        </span>
        <input
            class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-xl border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-2xs"
            x-ref="dynamicInput"
        >
    </div>

    <div
        class="flex items-center"
        x-data="liquidColorPicker({ colorVal: selectedNodesProps.fill })"
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
            :value="selectedNodesProps.fill"
            @input="selectedNodesProps = { fill: $event.target.value }"
            @change="picker.setColor($event.target.value)"
            @keydown.enter.prevent="picker?.setColor($event.target.value);"
            @focus="picker.open(); $el.select();"
            x-effect="selectedNodes.length === 1 && $nextTick(() => picker.setColor(selectedNodesProps.fill))"
        />
    </div>

    <div class="flex items-center gap-1 self-center">
        {{-- <button
            class="inline-grid size-7 place-items-center self-center rounded-lg transition-all hover:bg-foreground/10 [&.active]:bg-primary [&.active]:text-primary-foreground"
            type="button"
            title="{{ __('Bold') }}"
            :class="{ 'active': selectedNodesProps.fontStyle?.includes('700') }"
            @click.prevent="selectedNodesProps = { fontStyle: selectedNodesProps.fontStyle?.includes('700') ? '400' : '700' }; selectionTransformer.forceUpdate();"
        >
            <x-tabler-bold class="size-[18px]" />
        </button> --}}

        <button
            class="inline-grid size-7 place-items-center self-center rounded-lg transition-all hover:bg-foreground/10 [&.active]:bg-primary [&.active]:text-primary-foreground"
            type="button"
            title="{{ __('Italic') }}"
            :class="{ 'active': selectedNodesProps.fontStyle?.includes('italic') }"
            @click.prevent="selectedNodesProps = { fontStyle: selectedNodesProps.fontStyle?.includes('italic') ? 'normal' : 'italic' }; selectionTransformer.forceUpdate();"
        >
            <x-tabler-italic class="size-[18px]" />
        </button>

        <button
            class="inline-grid size-7 place-items-center self-center rounded-lg transition-all hover:bg-foreground/10 [&.active]:bg-primary [&.active]:text-primary-foreground"
            type="button"
            title="{{ __('Underline') }}"
            :class="{ 'active': selectedNodesProps.textDecoration == 'underline' }"
            @click.prevent="selectedNodesProps = { textDecoration: selectedNodesProps.textDecoration == 'underline' ? '' : 'underline' }; selectionTransformer.forceUpdate();"
        >
            <x-tabler-underline class="size-[18px]" />
        </button>
    </div>

    {{-- <div
        class="group relative flex w-16 min-w-full font-semibold uppercase"
        x-data="dynamicInput({ relativeValue: () => selectedNodes.length > 1, step: 0.1 })"
        :class="{ 'opacity-50 pointer-events-none': selectedNodes.find(n => n.getClassName() === 'TextPath') }"
    >
        <span
            class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
            x-ref="dynamicLabel"
        >
            <x-tabler-line-height class="size-4" />
        </span>
        <input
            class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-xl border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base sm:text-2xs transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary"
            x-ref="dynamicInput"
            :value="selectedNodesProps.lineHeight"
            @input="selectedNodesProps = { lineHeight: $event.target.value }"
            @focus="selectedNodes.length > 1 && $el.select()"
        >
    </div> --}}

    {{-- <div
        class="group relative flex w-16 min-w-full font-semibold uppercase"
        x-data="dynamicInput({ relativeValue: () => selectedNodes.length > 1 })"
    >
        <span
            class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
            x-ref="dynamicLabel"
        >
            <x-tabler-letter-spacing class="size-4" />
        </span>
        <input
            class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-xl border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base sm:text-2xs transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary"
            x-ref="dynamicInput"
            :value="selectedNodesProps.letterSpacing"
            @input="selectedNodesProps = { letterSpacing: $event.target.value }"
            @focus="selectedNodes.length > 1 && $el.select()"
        >
    </div> --}}

    <div class="col-span-2 flex justify-between rounded-lg bg-foreground/5 p-0.5">
        @foreach ($alignments as $key => $alignment)
            <button
                class="flex shrink-0 grow items-center justify-center rounded-md px-2 py-1 text-center text-3xs/none font-semibold transition-all [&.active]:bg-background [&.active]:shadow-md [&.active]:shadow-black/5"
                :class="{ active: selectedNodesProps.align === '{{ $key }}' }"
                @click.prevent="selectedNodesProps = {align: '{{ $key }}'}"
                type="button"
                title="{{ $alignment['label'] }}"
            >
                <x-dynamic-component
                    class="size-3.5"
                    :component="$alignment['icon']"
                />
            </button>
        @endforeach
    </div>
</div>
