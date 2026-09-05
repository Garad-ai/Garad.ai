<h4 class="mb-3 border-b pb-2 text-sm">
    {{ __('Resize') }}
</h4>

<div class="space-y-4">
    <div class="space-y-1">
        <x-forms.input
            class:label="text-2xs"
            type="checkbox"
            switcher
            label="{{ __('Adaptive Resize') }}"
            x-model="adaptiveResize"
            size="sm"
        />
        <p class="m-0 text-3xs/3 font-medium text-label opacity-80">
            {{ __('Objects will automatically adapt to the new canvas size.') }}
        </p>
    </div>

    <div
        class="flex gap-1"
        x-data="{ preserveAspectRatio: true }"
    >
        <div class="grid grow grid-cols-2 gap-1">
            <div
                class="group relative flex w-20 min-w-full font-semibold uppercase"
                x-data="dynamicInput({ min: 200 })"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-arrow-autofit-width class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                    :value="stage?.width() ?? null"
                    @input="handleStageResize({width: $event.target.value, preserveAspectRatio}); !nodes.length && (showWelcomeScreen = false)"
                >
            </div>
            <div
                class="group relative flex w-20 min-w-full font-semibold uppercase"
                x-data="dynamicInput({ min: 200 })"
            >
                <span
                    class="absolute start-0 top-0 grid h-full w-6 cursor-ew-resize select-none place-items-center text-4xs opacity-80"
                    x-ref="dynamicLabel"
                >
                    <x-tabler-arrow-autofit-height class="size-4" />
                </span>
                <input
                    class="col-start-1 col-end-1 row-start-1 row-end-1 w-full appearance-none rounded-md border border-foreground/10 bg-transparent py-0.5 pe-1 ps-6 text-base transition-all focus:border-primary focus:outline-none group-[&.dragging]:border-primary sm:text-3xs"
                    x-ref="dynamicInput"
                    :value="stage?.height() ?? null"
                    @input="handleStageResize({height: $event.target.value, preserveAspectRatio}); !nodes.length && (showWelcomeScreen = false)"
                >
            </div>
        </div>

        <div class="flex rounded-lg bg-foreground/5 p-0.5">
            <button
                class="active inline-grid size-6 shrink-0 place-items-center rounded-md bg-foreground/5 transition-all [&.active]:bg-background [&.active]:shadow-md [&.active]:shadow-black/5"
                :class="{ active: preserveAspectRatio }"
                type="button"
                @click.prevent="preserveAspectRatio = !preserveAspectRatio"
            >
                <x-tabler-aspect-ratio class="size-4" />
            </button>
        </div>
    </div>

    <hr>

    <ul class="space-y-0.5">
        @foreach ($sizes as $key => $size)
            <li class="flex flex-col">
                <button
                    class="-mx-3 flex items-center justify-between gap-1 rounded-md px-3 py-2 text-xs font-medium transition-all hover:bg-foreground/5 hover:shadow-lg hover:shadow-black/5"
                    type="button"
                    @click.prevent="handleStageResize({ width: {{ $size['width'] }}, height: {{ $size['height'] }} }); !nodes.length && (showWelcomeScreen = false)"
                >
                    {{ $size['label'] }}
                    <span class="text-3xs opacity-60">
                        {{ $size['width'] }}x{{ $size['height'] }}
                    </span>
                </button>
            </li>
        @endforeach
    </ul>
</div>
