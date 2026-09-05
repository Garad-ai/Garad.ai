@php
    $aiTemplateEnabled = \App\Helpers\Classes\MarketplaceHelper::isRegistered('creative-suite-ai-template');
@endphp

<style>
    .lqd-cs-frames-wrapper {
        transform-origin: 0 0;
        will-change: transform;
        backface-visibility: hidden;
    }

    .lqd-cs-frame-label {
        position: absolute;
        bottom: 100%;
        left: 0;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 500;
        color: hsl(var(--foreground) / 0.45);
        white-space: nowrap;
        pointer-events: auto;
        user-select: none;
        transform-origin: bottom left;
        transform: scale(calc(1 / var(--zoom-level, 1)));
    }

    body.is-panning,
    .lqd-cs-canvas-container-wrap.is-panning {
        cursor: grab !important;
    }

    body.is-panning-active,
    .lqd-cs-canvas-container-wrap.is-panning-active {
        cursor: grabbing !important;
    }
</style>

<div
    class="lqd-cs-canvas-wrap h-screen w-screen overflow-hidden transition-all"
    x-ref="editorCanvasWrap"
>
    <div
        class="lqd-cs-canvas-inner relative grid h-screen w-screen grid-cols-1 overflow-hidden"
        x-ref="editorCanvasInner"
    >
        <div
            class="relative z-1 col-start-1 col-end-1 row-start-1 row-end-1 flex h-screen w-screen flex-col justify-between gap-4 overflow-y-auto pb-6 pe-5 ps-[calc(var(--sidebar-w)+1.25rem)] pt-[calc(var(--header-h)+1.5rem)] max-lg:pb-[calc(var(--sidebar-h)+1.5rem)]"
            x-show="showWelcomeScreen"
        >
            <div class="lqd-cs-start-panel group grid shrink grow place-items-center">
                <div class="col-start-1 col-end-1 row-start-1 row-end-1 m-auto grid h-[min(450px,100%)] w-[min(100%,720px)] place-items-center">
                    <div
                        class="col-start-1 col-end-1 row-start-1 row-end-1 flex h-full w-full min-w-0 shrink grow flex-col items-center justify-center gap-6 overflow-y-auto rounded-3xl bg-background/50 py-5 text-center backdrop-blur-3xl transition-colors"
                        @if ($aiTemplateEnabled) x-show="!generatingTemplate"
						x-transition @endif
                    >
                        <a
                            class="peer absolute start-0 top-0 z-2 h-full w-full"
                            href="#"
                            @click.prevent="$nextTick(() => activeTool = 'templates')"
                        ></a>
                        <span
                            class="inline-grid size-[53px] shrink-0 place-items-center rounded-full bg-heading-foreground text-heading-background transition-all peer-hover:scale-110 peer-hover:shadow-lg peer-hover:shadow-black/10"
                        >
                            <x-tabler-plus class="size-7" />
                        </span>
                        <p class="mb-0 text-base font-bold">
                            {{ __('Select a template from library') }}
                        </p>
                        <div class="mx-auto flex w-[min(400px,85%)] items-center gap-8 text-2xs font-medium">
                            <span class="inline-block h-px grow bg-foreground/10"></span>
                            {{ __('or') }}
                            <span class="inline-block h-px grow bg-foreground/10"></span>
                        </div>

                        <x-button
                            class="relative z-3 px-8 py-3.5 outline-foreground/5 hover:bg-primary hover:text-primary-foreground hover:shadow-primary/15 hover:outline-primary"
                            variant="outline"
                            @click.prevent="$nextTick(() => activeTool = 'resize')"
                        >
                            {{ __('Choose a preset size') }}
                        </x-button>
                    </div>

                    @if ($aiTemplateEnabled)
                        <div
                            class="col-start-1 col-end-1 row-start-1 row-end-1 flex h-full w-full min-w-0 shrink grow flex-col items-center justify-center rounded-3xl bg-background/90 py-5 text-center ring-4 ring-background/40 backdrop-blur-3xl transition-colors"
                            x-show="generatingTemplate"
                            x-cloak
                            x-transition
                        >
                            <x-outline-glow
                                class="[--glow-color-primary:238_71%_79%] [--glow-color-secondary:166_74%_45%] [--outline-glow-iteration:999999] [--outline-glow-w:4px]"
                                effect="3"
                            />
                            <p class="mb-3 text-sm font-bold">
                                {{ __('Preparing your design...') }}
                            </p>
                            <p class="m-0 text-sm">
                                {{ __('This might take a while.') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="lg:mb-9">
                @includeIf('creative-suite-ai-template::partials.template-form')
            </div>
        </div>

        <div
            class="lqd-cs-canvas-container-wrap lqd-loading-skeleton max-w-screen col-start-1 col-end-1 row-start-1 row-end-1 h-screen max-h-screen w-screen select-none overflow-hidden"
            x-ref="canvasViewport"
            :class="{ 'lqd-is-loading': annotationSubmitting || annotationAnalyzing }"
        >
            {{-- Frames wrapper — receives pan/zoom transforms --}}
            <div
                class="lqd-cs-frames-wrapper col-start-1 col-end-1 row-start-1 row-end-1 flex translate-x-[--zoom-offset-x] translate-y-[--zoom-offset-y] scale-[--zoom-level] items-start gap-16"
                x-ref="framesWrapper"
            >
                {{-- Reference image frame --}}
                <div
                    class="lqd-cs-reference-frame relative shrink-0"
                    x-show="lastGeneratedReferenceImage && showReferenceFrame && !showWelcomeScreen"
                    x-cloak
                    x-ref="referenceFrame"
                >
                    <div class="lqd-cs-frame-label flex items-center gap-2">
                        {{ __('Reference') }}
                        <button
                            class="inline-grid size-4 place-items-center rounded-sm opacity-0 transition-opacity hover:bg-foreground/10 group-hover:opacity-100 [.lqd-cs-reference-frame:hover_&]:opacity-100"
                            type="button"
                            @click="showReferenceFrame = false; $nextTick(() => fitToScreen())"
                            title="{{ __('Hide reference') }}"
                        >
                            <x-tabler-x class="size-3" />
                        </button>
                    </div>
                    <img
                        class="block rounded-sm border border-foreground/5 bg-background shadow-sm"
                        :src="lastGeneratedReferenceImage"
                        :style="{ height: (stage?.height() ?? 720) + 'px', width: 'auto' }"
                        alt="{{ __('Reference Image') }}"
                    />
                </div>

                {{-- Canvas frame --}}
                <div class="lqd-cs-canvas-frame relative shrink-0">
                    <div
                        class="lqd-cs-frame-label"
                        x-show="lastGeneratedReferenceImage && showReferenceFrame && !showWelcomeScreen"
                        x-cloak
                    >
                        {{ __('Canvas') }}
                    </div>
                    <div
                        class="lqd-cs-canvas-container grid touch-none select-none grid-cols-1 place-content-center place-items-center overflow-hidden transition-colors focus:outline-none [&_.konvajs-content]:w-full"
                        id="lqd-cs-canvas-container"
                        x-ref="editorCanvasContainer"
                        tabindex="0"
                        :class="{ 'bg-background shadow-sm': !showWelcomeScreen }"
                        style="-webkit-tap-highlight-color: transparent;"
                    >
                    </div>
                </div>
            </div>

            {{-- Show reference toggle (appears when reference exists but is hidden) --}}
            <button
                class="absolute bottom-4 start-1/2 z-10 flex -translate-x-1/2 items-center gap-1.5 rounded-lg bg-background/90 px-3 py-1.5 text-2xs font-medium shadow-lg shadow-black/5 backdrop-blur-md transition-colors hover:bg-foreground/5"
                x-show="lastGeneratedReferenceImage && !showReferenceFrame && !showWelcomeScreen"
                x-cloak
                x-transition
                type="button"
                @click="showReferenceFrame = true; $nextTick(() => fitToScreen())"
            >
                <x-tabler-photo class="size-3.5" />
                {{ __('Show Reference') }}
            </button>

            @include('creative-suite::editor.includes.tooltip')
        </div>
    </div>
</div>
