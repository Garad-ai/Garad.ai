{{-- Vertical Agent Canvas --}}
<div class="relative flex h-full min-h-0 flex-1 overflow-hidden bg-foreground/[1%]">
    {{-- Dot Grid --}}
    <div
        class="absolute inset-0"
        style="background-image: radial-gradient(circle, hsl(var(--foreground) / 0.05) 1px, transparent 1px); background-size: 24px 24px;"
    ></div>

    {{-- Vertical scrollable canvas --}}
    <div class="relative z-10 h-full w-full overflow-auto overscroll-contain">
        <div
            class="flex min-h-full flex-col items-center justify-start gap-3 px-4 py-12"
            :style="'zoom: ' + (zoom / 100)"
        >
            {{-- Trigger Node --}}
            @include('ai-agent::workflows.builder.nodes.trigger-node')

            <button
                class="group/btn flex flex-col items-center gap-2 p-1"
                @click="openAddStepModal(0)"
                type="button"
                :data-order="0"
            >
                <span class="h-3 w-px bg-foreground/10"></span>

                <span
                    class="inline-grid size-6 place-items-center rounded-button transition group-hover/btn:scale-110 group-hover/btn:bg-primary group-hover/btn:text-primary-foreground"
                >
                    <x-tabler-plus class="size-4" />
                </span>

                <template x-if="steps.length > 0">
                    <span class="h-3 w-px bg-foreground/10"></span>
                </template>
            </button>

            {{-- Steps --}}
            <template
                x-for="(step, index) in steps"
                :key="step.id"
            >
                <div
                    class="flex w-full flex-col items-center gap-3"
                    :class="step.type === 'path' ? 'w-full' : ''"
                >
                    {{-- Step node (regular) --}}
                    <template x-if="step.type !== 'path'">
                        @include('ai-agent::workflows.builder.nodes.step-node')
                    </template>

                    {{-- Path node (branching) --}}
                    <template x-if="step.type === 'path'">
                        <div class="flex w-full flex-col items-center">
                            @include('ai-agent::workflows.builder.nodes.path-node')
                        </div>
                    </template>

                    <button
                        class="group/btn flex flex-col items-center gap-2 p-1"
                        @click="openAddStepModal(index + 1)"
                        type="button"
                        :data-order="index + 1"
                    >
                        <span class="h-3 w-px bg-foreground/10"></span>

                        <span
                            class="inline-grid size-6 place-items-center rounded-button transition group-hover/btn:scale-110 group-hover/btn:bg-primary group-hover/btn:text-primary-foreground"
                        >
                            <x-tabler-plus class="size-4" />
                        </span>

                        <template x-if="index < steps.length - 1">
                            <span class="h-3 w-px bg-foreground/10"></span>
                        </template>
                    </button>
                </div>
            </template>

            {{-- Empty steps hint --}}
            <template x-if="steps.length === 0 && triggerConfigured">
                <div
                    class="w-[min(275px,100%)] cursor-pointer rounded-xl border border-foreground/10 bg-foreground/5 p-4 transition hover:-translate-y-0.5 hover:border-background hover:bg-background hover:shadow-xl hover:shadow-black/5"
                    @click="openAddStepModal(0)"
                >
                    <x-tabler-circle-dashed class="mb-2 size-8 text-foreground/20" />
                    <p class="mb-0.5 text-2xs font-medium">
                        {{ __('No steps yet') }}
                    </p>
                    <p class="m-0 text-2xs">
                        {{ __('Click + to add your first action step.') }}
                    </p>
                </div>
            </template>

            {{-- Bottom spacing --}}
            <div class="h-16"></div>
        </div>
    </div>

    {{-- Zoom Controls --}}
    <div class="absolute bottom-4 start-4 z-20 flex items-center gap-1 rounded-full border bg-background p-1 shadow-sm">
        <button
            class="grid size-8 place-items-center rounded-full text-foreground/60 hover:bg-foreground/5 hover:text-foreground"
            @click="zoomOut()"
            type="button"
        >
            <x-tabler-minus class="size-4" />
        </button>
        <button
            class="grid place-items-center rounded-full px-2 py-1 text-2xs font-medium text-foreground/60 hover:bg-foreground/5 hover:text-foreground"
            @click="resetZoom()"
            x-text="zoom + '%'"
            type="button"
        ></button>
        <button
            class="grid size-8 place-items-center rounded-full text-foreground/60 hover:bg-foreground/5 hover:text-foreground"
            @click="zoomIn()"
            type="button"
        >
            <x-tabler-plus class="size-4" />
        </button>
    </div>
</div>
