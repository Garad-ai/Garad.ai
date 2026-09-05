@php
    $theme = setting('dash_theme', 'default');
@endphp

@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
    'layout_wide' => true,
])

@section('title', __('AI Image Edit'))

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.add("navbar-shrinked");
            localStorage.setItem('lqdNavbarShrinked', true);
        })();
    </script>
@endpush

@push('css')
    <style>
        body {
            --background: 0 0% 98%;
            --surface-background: 0 0% 100%;
            --surface-foreground: 0 0% 20%;
            background-image: none !important;
        }

        body.theme-dark {
            --background: 216 19% 11%;
            --surface-background: 216 19% 11%;
            --surface-foreground: 0 0% 100%;
        }

        @media (min-width: 992px) {
            .lqd-page-content-wrap {
                min-height: 100vh;
            }

            .lqd-page-content-container {
                display: flex;
                flex-direction: column;
            }

            .lqd-header,
            .lqd-navbar,
            .lqd-navbar-expander {
                display: none !important;
            }
        }
    </style>
@endpush

@if ($theme === 'bolt')
    @push('css')
        <style>
            @media (min-width: 992px) {
                .lqd-page-wrapper {
                    padding-inline-start: var(--body-padding) !important;
                }
            }
        </style>
    @endpush
@endif

@if ($theme === 'bolt' || $theme === 'social-media-dashboard')
    @push('css')
        <style>
            :root {
                --body-padding: 0 !important;
            }
        </style>
    @endpush
@endif

@if ($theme === 'social-media-dashboard' || $theme === 'social-media-agent-dashboard')
    @push('css')
        <style>
            @media (min-width: 992px) {
                .lqd-page-wrapper {
                    padding-inline-start: 0 !important;
                }
            }
        </style>
    @endpush
@endif

@section('content')
    <div
        @class([
            'lqd-img-editor relative flex overflow-hidden [--navbar-height:60px] [--sidebar-w:76px] lg:h-screen',
            'h-[calc(100vh-var(--header-height)-var(--bottom-menu-height))]' => auth()->check(),
            'h-screen' => !auth()->check(),
        ])
        style="background-image: radial-gradient(hsl(var(--foreground)/20%) 1px, transparent 0px); background-size: 28px 28px; background-position: center;"
        x-data="imageProEditor"
    >
        {{-- Top Navbar --}}
        <header
            class="lqd-img-editor-header fixed inset-x-[var(--body-padding,0px)] top-[calc(var(--body-padding,var(--navbar-height,0px)))+var(--header-height,var(--header-h,0px))] z-30 flex h-[--navbar-height] items-center justify-between gap-1 px-3 py-2 sm:px-4 sm:py-4 md:px-6 lg:top-[var(--body-padding,0px)] lg:ps-[--sidebar-w]"
        >
            <div class="flex items-center gap-1">
                {{-- Mobile history toggle --}}
                <x-button
                    class="size-8 shrink-0 rounded-full bg-surface-background text-3xs font-semibold text-surface-foreground hover:bg-primary hover:text-primary-foreground lg:hidden"
                    size="none"
                    type="button"
                    @click="mobile.showHistory = !mobile.showHistory"
                >
                    <x-tabler-history class="size-5" />
                </x-button>

                {{-- Tool Tabs --}}
                <nav
                    class="flex items-center gap-2 max-lg:hidden"
                    x-show="currentImage"
                    x-cloak
                >
                    <template
                        x-for="tab in tabs"
                        :key="tab.key"
                    >
                        <x-button
                            class="rounded-full bg-surface-background px-3 py-2 text-3xs font-semibold text-surface-foreground backdrop-blur-md hover:bg-primary hover:text-primary-foreground max-lg:w-full max-lg:justify-start max-lg:rounded-md max-lg:text-start [&.active]:bg-primary/5 [&.active]:text-primary"
                            ::class="{ 'active': selectedTab === tab.key }"
                            type="button"
                            x-text="tab.label"
                            @click="selectTab(tab.key)"
                        ></x-button>
                    </template>
                </nav>

                {{-- Mobile tab dropdown --}}
                <div
                    class="max-sm:whitespace-nowrap lg:hidden"
                    x-show="currentImage"
                    x-cloak
                    x-data="{ open: false }"
                    @click.outside="open = false"
                >
                    <button
                        class="rounded-full bg-surface-background px-3 py-2 text-3xs font-semibold text-surface-foreground hover:bg-primary hover:text-primary-foreground lg:hidden"
                        type="button"
                        @click="open = !open"
                    >
                        <span x-text="tabs.find(t => t.key === selectedTab)?.label"></span>
                        <x-tabler-chevron-down class="ms-1 inline size-3" />
                    </button>
                    <div
                        class="flex flex-col items-start gap-2 max-lg:absolute max-lg:start-0 max-lg:top-full max-lg:z-20 max-lg:mt-1 max-lg:rounded-lg max-lg:bg-surface-background/90 max-lg:p-2 max-lg:shadow-lg max-lg:shadow-black/10 max-lg:backdrop-blur-md lg:contents"
                        :class="{ 'hidden': !open }"
                    >
                        <template
                            x-for="tab in tabs"
                            :key="tab.key"
                        >
                            <button
                                class="rounded-md px-3 py-1.5 text-start text-2xs font-semibold transition hover:bg-foreground/5 max-lg:w-full [&.active]:bg-primary [&.active]:text-primary-foreground lg:[&.active]:bg-primary/5 lg:[&.active]:text-primary"
                                :class="{ 'active': selectedTab === tab.key }"
                                type="button"
                                x-text="tab.label"
                                @click="selectTab(tab.key); open = false"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-1">
                {{-- Brush controls (Visual mode only) --}}
                <div
                    class="flex items-center gap-0.5 rounded-full bg-surface-background/90 px-1.5 py-1 text-2xs font-medium shadow-sm backdrop-blur-sm max-sm:absolute max-sm:end-3 max-sm:top-full max-sm:-mt-1 md:gap-1"
                    x-show="mode === 'visual' && currentImage"
                    x-cloak
                    x-transition
                >
                    {{-- Undo / Redo --}}
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5 disabled:pointer-events-none disabled:opacity-30"
                        type="button"
                        title="{{ __('Undo') }} (Ctrl+Z)"
                        ::disabled="!canUndo"
                        @click="undo()"
                    >
                        <x-tabler-arrow-back-up class="size-3.5" />
                    </button>
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5 disabled:pointer-events-none disabled:opacity-30"
                        type="button"
                        title="{{ __('Redo') }} (Ctrl+Shift+Z)"
                        ::disabled="!canRedo"
                        @click="redo()"
                    >
                        <x-tabler-arrow-forward-up class="size-3.5" />
                    </button>

                    <div class="mx-0.5 h-4 w-px bg-foreground/10"></div>

                    {{-- Brush size --}}
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5"
                        type="button"
                        title="{{ __('Decrease brush size') }}"
                        @click="brushSize = Math.max(10, brushSize - 10)"
                    >
                        <x-tabler-brush class="size-3" />
                    </button>
                    <span class="inline-flex min-w-[2rem] items-center justify-center gap-1 tabular-nums">
                        <span x-text="brushSize"></span>
                    </span>
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5"
                        type="button"
                        title="{{ __('Increase brush size') }}"
                        @click="brushSize = Math.min(100, brushSize + 10)"
                    >
                        <x-tabler-brush class="size-[18px]" />
                    </button>
                </div>

                <div
                    class="mx-0.5 h-4 w-px bg-foreground/15 max-sm:!hidden md:mx-1"
                    x-cloak
                    x-show="mode === 'visual' && currentImage"
                ></div>

                {{-- Zoom Controls --}}
                <div
                    class="flex items-center gap-0.5 rounded-full bg-surface-background/90 px-1.5 py-1 text-2xs font-medium shadow-sm backdrop-blur-sm transition-opacity"
                    x-cloak
                    x-show="currentImage && !busy"
                    x-transition
                >
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5"
                        type="button"
                        title="{{ __('Zoom out') }}"
                        @click="zoomBy(0.8)"
                    >
                        <x-tabler-zoom-out class="size-3.5" />
                    </button>
                    <button
                        class="inline-flex min-w-[3rem] items-center justify-center rounded-full px-1 py-0.5 text-center tabular-nums transition hover:bg-foreground/5"
                        type="button"
                        title="{{ __('Fit to screen') }}"
                        @click="fitToScreen()"
                        x-text="Math.round(zoom * 100) + '%'"
                    ></button>
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5"
                        type="button"
                        title="{{ __('Zoom in') }}"
                        @click="zoomBy(1.25)"
                    >
                        <x-tabler-zoom-in class="size-3.5" />
                    </button>
                </div>

                <div
                    class="mx-0.5 h-4 w-px bg-foreground/15 md:mx-1"
                    x-cloak
                    x-show="currentImage"
                ></div>

                {{-- Download Button --}}
                <x-button
                    class="bg-surface-background/90 py-1 pe-2 ps-3 text-2xs font-medium text-surface-foreground shadow-sm backdrop-blur-sm max-sm:size-8 max-sm:p-0"
                    ::class="{ 'pointer-events-none opacity-50': !currentImage || busy }"
                    hover-variant="primary"
                    x-cloak
                    x-show="currentImage"
                    @click.prevent="downloadImage"
                >
                    <span class="hidden sm:inline">
                        @lang('Download')
                    </span>
                    <span class="inline-grid size-7 place-content-center rounded-full border border-foreground/5">
                        <x-tabler-download class="size-4" />
                    </span>
                </x-button>

                {{-- Close Button --}}
                <x-button
                    class="hidden size-7 lg:inline-flex"
                    variant="ghost"
                    size="none"
                    href="{{ route(auth()->check() ? 'dashboard.user.ai-image-pro.index' : 'ai-image-pro.index') }}"
                    title="{{ __('Close') }}"
                >
                    <x-tabler-x class="size-5" />
                </x-button>
            </div>
        </header>

        {{-- Left Sidebar --}}
        <aside
            class="lqd-img-editor-sidebar pointer-events-none fixed bottom-[var(--body-padding,0px)] start-[var(--body-padding,0px)] top-[var(--body-padding,0px)] z-20 flex w-[--sidebar-w] flex-col justify-between gap-2 px-1.5"
        >
            {{-- Logo --}}
            <div class="pointer-events-auto flex h-[--navbar-height] w-full items-center justify-center">
                <div class="hidden w-full lg:block">
                    <x-navbar.navbar-logo />
                </div>
            </div>

            {{-- Image History --}}
            <div
                class="pointer-events-auto relative w-full transition max-lg:-translate-x-full max-lg:[&.active]:translate-x-0"
                :class="{ 'active': mobile.showHistory }"
            >
                <div
                    class="no-scrollbar flex h-[min(285px,50vh)] flex-col items-center gap-1.5 overflow-y-auto overscroll-contain rounded-md bg-surface-background px-2.5 py-3 shadow-md shadow-black/5"
                    x-show="imageHistory.length > 0 || historyLoading"
                    x-cloak
                    x-transition
                    @scroll="handleHistoryScroll($event)"
                >
                    {{-- Upload new image --}}
                    <x-button
                        class="size-[42px] shrink-0"
                        size="none"
                        type="button"
                        title="{{ __('Upload new image') }}"
                        @click="$refs.fileInput.click()"
                    >
                        <x-tabler-plus class="size-5" />
                    </x-button>

                    <template
                        x-for="(image, index) in imageHistory"
                        :key="image.id || index"
                    >
                        <button
                            class="inline-flex size-12 shrink-0 overflow-hidden rounded-md opacity-70 transition hover:scale-105 hover:opacity-100 [&.active]:opacity-100"
                            :class="{ 'active': currentImage?.url === image.url }"
                            type="button"
                            @click="loadImageFromHistory(image); mobile.showHistory = false"
                        >
                            <img
                                class="size-full object-cover object-center"
                                :src="`${(image.thumbnail || image.url).startsWith('upload') ? '/' : ''}${image.thumbnail || image.url}`"
                                :alt="image.title || ''"
                                loading="lazy"
                            >
                        </button>
                    </template>

                    {{-- Loading spinner --}}
                    <div
                        class="flex shrink-0 items-center justify-center py-1"
                        x-show="historyLoading"
                        x-cloak
                    >
                        <x-tabler-loader-2 class="size-4 animate-spin text-primary" />
                    </div>
                </div>
            </div>

            <div class="pointer-events-auto h-[--navbar-height]"></div>
        </aside>

        {{-- Main Content --}}
        <div
            class="flex grow flex-col overflow-hidden"
            x-ref="canvasContainer"
        >
            <main class="relative flex flex-1 flex-col">
                {{-- Canvas Viewport --}}
                <div
                    class="lqd-img-editor-canvas relative flex-1 touch-none"
                    x-ref="canvasViewport"
                    @wheel.prevent="handleCanvasWheel($event)"
                    @mousedown="handleCanvasMouseDown($event)"
                    @mousemove.window="handleCanvasMouseMove($event)"
                    @mouseup.window="handleCanvasMouseUp($event)"
                    @touchstart="handleTouchStart($event)"
                    @touchmove.prevent="handleTouchMove($event)"
                    @touchend="handleTouchEnd($event)"
                    @touchcancel="handleTouchEnd($event)"
                    :class="{
                        'cursor-grab select-none': isSpaceHeld && !isPanning,
                        'cursor-grabbing select-none': isPanning,
                        'cursor-crosshair': mode === 'visual' && currentImage && !isSpaceHeld && !isPanning,
                    }"
                >
                    {{-- Empty State --}}
                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center gap-4 px-5 py-20 text-center"
                        x-show="!currentImage && !busy"
                    >
                        <h2 class="mb-2 text-pretty text-[30px] font-medium">
                            <span class="block text-[0.75em]">
                                <span class="opacity-50">
                                    {{ __('AI Image Edit') }}
                                </span>
                                👋
                            </span>
                            {{ __('Select an image to get started.') }}
                        </h2>
                        <x-button
                            type="button"
                            size="lg"
                            @click="$refs.fileInput.click()"
                        >
                            <x-tabler-upload class="size-4" />
                            {{ __('Upload Image') }}
                        </x-button>
                    </div>

                    {{-- Loading State --}}
                    <div
                        class="absolute inset-0 flex items-center justify-center"
                        x-show="busy && !currentImage"
                        x-cloak
                    >
                        <x-tabler-loader-2 class="size-8 animate-spin text-primary" />
                    </div>

                    {{-- Pannable / Zoomable Canvas --}}
                    <div
                        class="absolute will-change-transform"
                        :class="{ 'invisible': !canvasReady }"
                        :style="`transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: 0 0;`"
                        x-show="currentImage"
                        x-cloak
                    >
                        <figure
                            class="lqd-loading-skeleton relative"
                            :class="{ 'lqd-is-loading': busy }"
                        >
                            <img
                                class="max-w-none rounded-lg shadow-lg transition-opacity duration-300"
                                x-ref="generatedImage"
                                :src="currentImage?.url || ''"
                                :alt="currentImage?.title || ''"
                                @load="onImageLoad()"
                                crossorigin="anonymous"
                            >

                            {{-- Shimmer overlay while generating --}}
                            <div
                                class="!absolute !inset-0 !rounded-lg"
                                data-lqd-skeleton-el
                                x-show="busy"
                                x-cloak
                            ></div>

                            {{-- Highlight Canvas Overlay --}}
                            <canvas
                                class="absolute inset-0 size-full touch-auto rounded-lg opacity-50"
                                x-ref="highlightCanvas"
                                x-show="mode === 'visual'"
                                x-cloak
                                @mousedown.prevent="onCanvasPointerDown($event)"
                                @mousemove.prevent="onCanvasPointerMove($event)"
                                @mouseup.window.prevent="onCanvasPointerUp()"
                                @touchstart="onCanvasTouchStart($event)"
                                @touchmove="onCanvasTouchMove($event)"
                                @touchend.prevent="onCanvasPointerUp()"
                                @touchcancel.prevent="onCanvasPointerUp()"
                            ></canvas>
                        </figure>
                    </div>
                </div>

                {{-- Bottom Prompt Bar --}}
                <div
                    class="pointer-events-none z-10 p-5 max-md:pb-2.5 sm:pb-11"
                    x-cloak
                    x-show="currentImage"
                    x-ref="promptBar"
                >
                    <div class="relative mx-auto w-[min(655px,100%)]">
                        {{-- "Editing selected area" indicator --}}
                        <div
                            class="pointer-events-auto absolute -inset-x-2.5 -bottom-2 -top-8 z-0 flex items-start justify-center rounded-[20px] bg-[#FFF174] text-center"
                            x-show="mode === 'visual' && hasHighlights"
                            x-cloak
                            x-transition
                        >
                            <button
                                class="group inline-flex items-center gap-1 py-1.5 text-2xs font-medium text-black"
                                type="button"
                                title="{{ __('Clear selection') }}"
                                @click="clearHighlights()"
                            >
                                {{ __('Editing selected area') }}
                                <span class="inline-grid size-5 place-items-center rounded-full transition group-hover:bg-black/20">
                                    <x-tabler-x class="size-4" />
                                </span>
                            </button>
                        </div>

                        <form
                            class="pointer-events-auto relative z-1 rounded-[18px] bg-surface-background p-2 shadow-2xl shadow-black/10 transition md:p-4"
                            @submit.prevent="generate()"
                        >
                            <div class="flex items-center gap-1 rounded-[10px] border px-2.5 max-md:flex-wrap max-md:pt-2 md:gap-3">
                                <x-forms.input
                                    class:container="max-md:w-full"
                                    class="whitespace-nowrap border-none bg-transparent py-0 pe-8 ps-2 text-foreground"
                                    type="select"
                                    x-model="mode"
                                >
                                    <option
                                        class="text-black"
                                        value="visual"
                                    >
                                        {{ __('Visual') }}
                                    </option>
                                    <option
                                        class="text-black"
                                        value="text"
                                    >
                                        {{ __('Text') }}
                                    </option>
                                </x-forms.input>

                                <div class="flex grow flex-wrap items-center gap-1 md:gap-3">
                                    <div class="inline-flex h-px w-full shrink-0 bg-foreground/10 md:h-6 md:w-px"></div>

                                    {{-- Prompt Input --}}
                                    <x-forms.input
                                        class:container="grow"
                                        class="resize-none rounded-none border-none bg-transparent px-0 pb-0 pt-5 placeholder:truncate placeholder:text-foreground focus:!border-transparent focus:!ring-0 focus-visible:outline-none focus-visible:ring-0 sm:text-sm"
                                        type="textarea"
                                        x-model="prompt"
                                        rows="2"
                                        x-ref="promptInput"
                                        ::placeholder="promptPlaceholder"
                                        @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $el.closest('form').requestSubmit(); }"
                                    />

                                    <x-button
                                        class="shrink-0 px-4 py-3.5"
                                        type="submit"
                                        @click="if (isDemo) { $event.preventDefault(); toastr?.info('{{ __('This feature is disabled in demo mode.') }}'); }"
                                        title="{{ __('Generate') }}"
                                        ::disabled="busy || (!prompt.trim() && selectedTab !== 'remove_background')"
                                        disabled
                                    >
                                        <span x-text="busy ? '{{ __('Generating') }}' : '{{ __('Generate') }}'">
                                            {{ __('Generate') }}
                                        </span>

                                        <x-tabler-loader-2
                                            class="size-5 animate-spin"
                                            x-show="busy"
                                            x-cloak
                                        />
                                    </x-button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>

        {{-- Hidden file input --}}
        <input
            class="hidden"
            type="file"
            accept="image/*"
            x-ref="fileInput"
            @change="handleFileUpload($event)"
        >
    </div>
@endsection

@push('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('imageProEditor', () => ({
                    currentImage: null,
                    imageHistory: [],
                    historyPage: 0,
                    historyHasMore: true,
                    historyLoading: false,
                    prompt: '',
                    busy: false,
                    isDemo: {{ $app_is_demo ? 'true' : 'false' }},
                    isDragging: false,
                    _imageIdCounter: 0,

                    selectedTab: 'smart_edit',
                    mode: 'visual',

                    tabs: [{
                            key: 'smart_edit',
                            label: '{{ __('Smart Edit') }}',
                            defaultMode: 'visual'
                        },
                        {
                            key: 'restyle',
                            label: '{{ __('Restyle') }}',
                            defaultMode: 'text'
                        },
                        {
                            key: 'remove_background',
                            label: '{{ __('Remove Background') }}',
                            defaultMode: 'text'
                        },
                        {
                            key: 'replace_background',
                            label: '{{ __('Replace Background') }}',
                            defaultMode: 'text'
                        },
                        {
                            key: 'reimagine',
                            label: '{{ __('Reimagine') }}',
                            defaultMode: 'text'
                        },
                    ],

                    // Chat/model state
                    chatId: {{ $chat?->id ?? 'null' }},
                    activeImageModels: @json($activeImageModels ?? []),
                    _selectedModelKey: '',

                    // Canvas zoom & pan state
                    canvasReady: false,
                    zoom: 1,
                    panX: 0,
                    panY: 0,
                    isPanning: false,
                    isSpaceHeld: false,
                    _lastMouseX: 0,
                    _lastMouseY: 0,
                    _touchStartDist: 0,
                    _touchStartZoom: 1,
                    _touchStartMidX: 0,
                    _touchStartMidY: 0,
                    _touchStartPanX: 0,
                    _touchStartPanY: 0,
                    _activeTouches: 0,
                    minZoom: 0.05,
                    maxZoom: 10,

                    // Highlighting state
                    painting: false,
                    brushSize: 40,
                    hasHighlights: false,
                    _canvasCtx: null,
                    _undoStack: [],
                    _redoStack: [],
                    _maxUndoSteps: 30,

                    // Polling state
                    _activePolls: new Map(),
                    _processedRecords: new Set(),

                    mobile: {
                        showHistory: false,
                    },

                    get promptPlaceholder() {
                        const placeholders = {
                            smart_edit: "{{ __('Describe what you want to add, remove or change') }}",
                            restyle: "{{ __('Describe the style you want (e.g., watercolor, cyberpunk, oil painting)') }}",
                            remove_background: "{{ __('Click Generate to remove the background') }}",
                            replace_background: "{{ __('Describe the new background...') }}",
                            reimagine: "{{ __('Describe how you want to reimagine this image') }}",
                        };
                        return placeholders[this.selectedTab] || placeholders.smart_edit;
                    },

                    get currentModel() {
                        if (!this._selectedModelKey || !this.activeImageModels[this._selectedModelKey]) {
                            const keys = Object.keys(this.activeImageModels);
                            return keys.length > 0 ? this.activeImageModels[keys[0]] : null;
                        }
                        return this.activeImageModels[this._selectedModelKey];
                    },

                    init() {
                        // Select first model
                        const modelKeys = Object.keys(this.activeImageModels);
                        const saved = localStorage.getItem('aiImageProEditorModel');

                        if (saved && modelKeys.includes(saved)) {
                            this._selectedModelKey = saved;
                        } else if (modelKeys[0]) {
                            this._selectedModelKey = modelKeys[0];
                        }

                        // Keyboard listeners for space-to-pan
                        this._handleKeyDown = (e) => {
                            if (e.code === 'Space' && !e.target.matches('input, textarea, [contenteditable]')) {
                                e.preventDefault();
                                this.isSpaceHeld = true;
                            }

                            if ((e.metaKey || e.ctrlKey) && e.key === 'z' && !e.shiftKey) {
                                e.preventDefault();
                                this.undo();
                            }
                            if ((e.metaKey || e.ctrlKey) && e.key === 'z' && e.shiftKey) {
                                e.preventDefault();
                                this.redo();
                            }
                        };
                        this._handleKeyUp = (e) => {
                            if (e.code === 'Space') {
                                this.isSpaceHeld = false;
                                this.isPanning = false;
                            }
                        };
                        window.addEventListener('keydown', this._handleKeyDown);
                        window.addEventListener('keyup', this._handleKeyUp);

                        // Drag and drop on the viewport
                        const viewport = this.$refs.canvasViewport;
                        if (viewport) {
                            viewport.addEventListener('dragover', (e) => {
                                e.preventDefault();
                                this.isDragging = true;
                            });
                            viewport.addEventListener('dragleave', (e) => {
                                e.preventDefault();
                                this.isDragging = false;
                            });
                            viewport.addEventListener('drop', (e) => {
                                e.preventDefault();
                                this.isDragging = false;
                                const files = e.dataTransfer?.files;
                                if (files?.length > 0 && files[0].type.startsWith('image/')) {
                                    this.loadImageFromFile(files[0]);
                                }
                            });
                        }

                        // Reset zoom/pan when image is cleared
                        this.$watch('currentImage', (val) => {
                            this.canvasReady = false;
                            if (!val) {
                                this.zoom = 1;
                                this.panX = 0;
                                this.panY = 0;
                            }
                        });

                        // Load pending image from sessionStorage
                        this.$nextTick(() => {
                            const pending = sessionStorage.getItem('pendingImageForEditor');
                            if (pending) {
                                try {
                                    const data = JSON.parse(pending);
                                    sessionStorage.removeItem('pendingImageForEditor');
                                    if (data.url) {
                                        this.loadImageFromUrl(data.url, data.title || 'image');
                                    }
                                } catch (e) {
                                    console.error('Failed to load pending image:', e);
                                }
                            }
                        });

                        // Load image history from AI Image Pro
                        this.loadImageHistory();

                        // Cleanup on unload
                        window.addEventListener('beforeunload', () => {
                            this._activePolls.forEach((interval) => clearInterval(interval));
                        });
                    },

                    destroy() {
                        window.removeEventListener('keydown', this._handleKeyDown);
                        window.removeEventListener('keyup', this._handleKeyUp);
                        this._activePolls.forEach((interval) => clearInterval(interval));
                    },

                    // --- Image loading ---

                    loadImageFromUrl(url, title = 'image') {
                        this.busy = true;
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.onload = () => {
                            this.setCurrentImage({
                                url,
                                title
                            });
                            this.busy = false;
                        };
                        img.onerror = () => {
                            this.setCurrentImage({
                                url,
                                title
                            });
                            this.busy = false;
                        };
                        img.src = url;
                    },

                    loadImageFromFile(file) {
                        const url = URL.createObjectURL(file);
                        this.setCurrentImage({
                            url,
                            title: file.name,
                            _file: file,
                        });
                    },

                    loadImageFromHistory(image) {
                        this.currentImage = image;
                        this.clearHighlights();
                    },

                    setCurrentImage(image) {
                        image.id = ++this._imageIdCounter;

                        if (this.currentImage && this.currentImage.url !== image.url) {
                            const exists = this.imageHistory.some(h => h.url === this.currentImage.url);
                            if (!exists) {
                                this.imageHistory.unshift({
                                    ...this.currentImage
                                });
                            }
                        }
                        this.currentImage = image;
                        this.clearHighlights();
                    },

                    handleFileUpload(event) {
                        const file = event.target.files?.[0];
                        if (file && file.type.startsWith('image/')) {
                            this.loadImageFromFile(file);
                        }
                        event.target.value = '';
                    },

                    // --- Image History (paginated) ---

                    async loadImageHistory() {
                        if (this.historyLoading || !this.historyHasMore) return;
                        this.historyLoading = true;

                        try {
                            const nextPage = this.historyPage + 1;
                            const url = `{{ route('ai-image-pro.completed-images') }}?page=${nextPage}&per_page=10`;
                            const response = await fetch(url, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                },
                            });

                            if (!response.ok) return;
                            const data = await response.json();

                            if (data.images?.length > 0) {
                                const mapped = data.images.flatMap(img =>
                                    (img.generated_images || []).map((imgUrl, idx) => ({
                                        id: `history-${img.id}-${idx}`,
                                        url: imgUrl,
                                        thumbnail: img.thumbnails?.[idx] || imgUrl,
                                        title: img.prompt || 'image',
                                    }))
                                );
                                this.imageHistory.push(...mapped);
                            }

                            this.historyPage = data.page;
                            this.historyHasMore = data.has_more;
                        } catch (e) {
                            console.error('Failed to load image history:', e);
                        } finally {
                            this.historyLoading = false;
                        }
                    },

                    handleHistoryScroll(event) {
                        const el = event.target;
                        const threshold = 40;
                        if (el.scrollTop + el.clientHeight >= el.scrollHeight - threshold) {
                            this.loadImageHistory();
                        }
                    },

                    // --- Tab switching ---

                    selectTab(key) {
                        this.selectedTab = key;
                        const tab = this.tabs.find(t => t.key === key);
                        if (tab) {
                            this.mode = tab.defaultMode;
                        }
                        if (key !== 'smart_edit') {
                            this.clearHighlights();
                        }
                        this.prompt = '';
                    },

                    // --- Canvas zoom & pan ---

                    handleCanvasWheel(e) {
                        if (e.metaKey || e.ctrlKey) {
                            const rect = this.$refs.canvasViewport.getBoundingClientRect();
                            const mouseX = e.clientX - rect.left;
                            const mouseY = e.clientY - rect.top;

                            const factor = e.deltaY > 0 ? 0.95 : 1.05;
                            const newZoom = Math.max(this.minZoom, Math.min(this.maxZoom, this.zoom * factor));
                            const scale = newZoom / this.zoom;

                            this.panX = mouseX - (mouseX - this.panX) * scale;
                            this.panY = mouseY - (mouseY - this.panY) * scale;
                            this.zoom = newZoom;
                        } else {
                            this.panX -= e.deltaX;
                            this.panY -= e.deltaY;
                        }
                    },

                    handleCanvasMouseDown(e) {
                        if (this.isSpaceHeld || e.button === 1) {
                            e.preventDefault();
                            this.isPanning = true;
                            this._lastMouseX = e.clientX;
                            this._lastMouseY = e.clientY;
                        }
                    },

                    handleCanvasMouseMove(e) {
                        if (!this.isPanning) return;
                        e.preventDefault();

                        this.panX += e.clientX - this._lastMouseX;
                        this.panY += e.clientY - this._lastMouseY;
                        this._lastMouseX = e.clientX;
                        this._lastMouseY = e.clientY;
                    },

                    handleCanvasMouseUp(e) {
                        this.isPanning = false;
                    },

                    _getTouchDist(t1, t2) {
                        return Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
                    },

                    _getTouchMid(t1, t2) {
                        return {
                            x: (t1.clientX + t2.clientX) / 2,
                            y: (t1.clientY + t2.clientY) / 2,
                        };
                    },

                    handleTouchStart(e) {
                        if (!this.currentImage && !this.busy) return;
                        if (this.painting) return;

                        this._activeTouches = e.touches.length;

                        if (e.touches.length === 1) {
                            if (this.mode === 'visual' && !this.isSpaceHeld) return;
                            this.isPanning = true;
                            this._lastMouseX = e.touches[0].clientX;
                            this._lastMouseY = e.touches[0].clientY;
                        } else if (e.touches.length === 2) {
                            this.isPanning = false;
                            const [t1, t2] = e.touches;
                            this._touchStartDist = this._getTouchDist(t1, t2);
                            this._touchStartZoom = this.zoom;
                            const mid = this._getTouchMid(t1, t2);
                            this._touchStartMidX = mid.x;
                            this._touchStartMidY = mid.y;
                            this._touchStartPanX = this.panX;
                            this._touchStartPanY = this.panY;
                        }
                    },

                    handleTouchMove(e) {
                        if (this.painting) return;
                        if (e.touches.length === 1 && this._activeTouches === 1) {
                            const t = e.touches[0];
                            this.panX += t.clientX - this._lastMouseX;
                            this.panY += t.clientY - this._lastMouseY;
                            this._lastMouseX = t.clientX;
                            this._lastMouseY = t.clientY;
                        } else if (e.touches.length === 2) {
                            const [t1, t2] = e.touches;
                            const dist = this._getTouchDist(t1, t2);
                            const mid = this._getTouchMid(t1, t2);
                            const rect = this.$refs.canvasViewport.getBoundingClientRect();
                            const midX = mid.x - rect.left;
                            const midY = mid.y - rect.top;

                            const newZoom = Math.max(
                                this.minZoom,
                                Math.min(this.maxZoom, this._touchStartZoom * (dist / this._touchStartDist))
                            );
                            const scale = newZoom / this.zoom;

                            this.panX = midX - (midX - this.panX) * scale;
                            this.panY = midY - (midY - this.panY) * scale;
                            this.zoom = newZoom;

                            const dx = mid.x - this._touchStartMidX;
                            const dy = mid.y - this._touchStartMidY;
                            this.panX += dx;
                            this.panY += dy;
                            this._touchStartMidX = mid.x;
                            this._touchStartMidY = mid.y;
                        }
                    },

                    handleTouchEnd(e) {
                        this._activeTouches = e.touches.length;

                        if (e.touches.length === 0) {
                            this.isPanning = false;
                        } else if (e.touches.length === 1) {
                            this._lastMouseX = e.touches[0].clientX;
                            this._lastMouseY = e.touches[0].clientY;
                            this._activeTouches = 1;
                        }
                    },

                    onImageLoad() {
                        this.canvasReady = false;
                        this.fitToScreen();
                        this.initHighlightCanvas();
                        this.$nextTick(() => {
                            this.canvasReady = true;
                        });
                    },

                    fitToScreen() {
                        const viewport = this.$refs.canvasViewport;
                        const img = this.$refs.generatedImage;
                        if (!viewport || !img || !img.naturalWidth || !img.naturalHeight) return;

                        const w = viewport.clientWidth;
                        const h = viewport.clientHeight;
                        if (w <= 0 || h <= 0) return;

                        const pad = Math.min(w, h) < 500 ? 24 : 80;
                        const scale = Math.min((w - pad) / img.naturalWidth, (h - pad) / img.naturalHeight);

                        this.zoom = scale;
                        this.panX = (w - img.naturalWidth * scale) / 2;
                        this.panY = (h - img.naturalHeight * scale) / 2;
                    },

                    zoomBy(factor) {
                        const viewport = this.$refs.canvasViewport;
                        if (!viewport) return;

                        const cx = viewport.clientWidth / 2;
                        const cy = viewport.clientHeight / 2;
                        const newZoom = Math.max(this.minZoom, Math.min(this.maxZoom, this.zoom * factor));
                        const s = newZoom / this.zoom;

                        this.panX = cx - (cx - this.panX) * s;
                        this.panY = cy - (cy - this.panY) * s;
                        this.zoom = newZoom;
                    },

                    // --- Highlighting / Painting ---

                    get canUndo() {
                        return this._undoStack.length;
                    },

                    get canRedo() {
                        return this._redoStack.length;
                    },

                    initHighlightCanvas() {
                        const canvas = this.$refs.highlightCanvas;
                        const img = this.$refs.generatedImage;
                        if (!canvas || !img) return;

                        canvas.width = img.naturalWidth;
                        canvas.height = img.naturalHeight;

                        this._canvasCtx = canvas.getContext('2d');
                        this._canvasCtx.clearRect(0, 0, canvas.width, canvas.height);
                        this.hasHighlights = false;
                        this._undoStack = [];
                        this._redoStack = [];
                    },

                    _saveSnapshot() {
                        const canvas = this.$refs.highlightCanvas;
                        if (!canvas || !this._canvasCtx) return;
                        const snapshot = this._canvasCtx.getImageData(0, 0, canvas.width, canvas.height);
                        this._undoStack.push(snapshot);
                        if (this._undoStack.length > this._maxUndoSteps) {
                            this._undoStack.shift();
                        }
                        this._redoStack = [];
                    },

                    _restoreSnapshot(snapshot) {
                        if (!this._canvasCtx || !snapshot) return;
                        this._canvasCtx.putImageData(snapshot, 0, 0);
                        this._updateHasHighlights();
                    },

                    _updateHasHighlights() {
                        const canvas = this.$refs.highlightCanvas;
                        if (!canvas || !this._canvasCtx) {
                            this.hasHighlights = false;
                            return;
                        }
                        const data = this._canvasCtx.getImageData(0, 0, canvas.width, canvas.height).data;
                        for (let i = 3; i < data.length; i += 4) {
                            if (data[i] > 0) {
                                this.hasHighlights = true;
                                return;
                            }
                        }
                        this.hasHighlights = false;
                    },

                    undo() {
                        if (this._undoStack.length === 0) return;
                        const canvas = this.$refs.highlightCanvas;
                        if (!canvas || !this._canvasCtx) return;

                        const currentState = this._canvasCtx.getImageData(0, 0, canvas.width, canvas.height);
                        this._redoStack.push(currentState);

                        const prev = this._undoStack.pop();
                        this._restoreSnapshot(prev);
                    },

                    redo() {
                        if (this._redoStack.length === 0) return;
                        const canvas = this.$refs.highlightCanvas;
                        if (!canvas || !this._canvasCtx) return;

                        const currentState = this._canvasCtx.getImageData(0, 0, canvas.width, canvas.height);
                        this._undoStack.push(currentState);

                        const next = this._redoStack.pop();
                        this._restoreSnapshot(next);
                    },

                    _lastPaintX: 0,
                    _lastPaintY: 0,

                    _getCanvasXY(clientX, clientY) {
                        const canvas = this.$refs.highlightCanvas;
                        const rect = canvas.getBoundingClientRect();
                        return {
                            x: (clientX - rect.left) * (canvas.width / rect.width),
                            y: (clientY - rect.top) * (canvas.height / rect.height),
                            scale: canvas.width / rect.width,
                        };
                    },

                    onCanvasPointerDown(e) {
                        if (this.isSpaceHeld || this.isPanning || this.mode !== 'visual') return;

                        const {
                            x,
                            y,
                            scale
                        } = this._getCanvasXY(e.clientX, e.clientY);
                        this._beginStroke(x, y, scale);
                    },

                    onCanvasPointerMove(e) {
                        this._continueStroke(e.clientX, e.clientY);
                    },

                    onCanvasPointerUp() {
                        this._endStroke();
                    },

                    onCanvasTouchStart(e) {
                        if (this.isSpaceHeld || this.mode !== 'visual' || e.touches.length !== 1) return;
                        e.preventDefault();
                        e.stopPropagation();

                        const touch = e.touches[0];
                        const {
                            x,
                            y,
                            scale
                        } = this._getCanvasXY(touch.clientX, touch.clientY);
                        this._beginStroke(x, y, scale);
                    },

                    onCanvasTouchMove(e) {
                        if (!this.painting || e.touches.length !== 1) return;
                        e.preventDefault();
                        e.stopPropagation();

                        const touch = e.touches[0];
                        this._continueStroke(touch.clientX, touch.clientY);
                    },

                    _beginStroke(x, y, scale) {
                        this._saveSnapshot();
                        this.painting = true;
                        this._lastPaintX = x;
                        this._lastPaintY = y;

                        const ctx = this._canvasCtx;
                        if (!ctx) return;

                        ctx.fillStyle = 'rgb(251, 191, 36)';
                        ctx.beginPath();
                        ctx.arc(x, y, (this.brushSize * scale) / 2, 0, Math.PI * 2);
                        ctx.fill();

                        this.hasHighlights = true;
                    },

                    _continueStroke(clientX, clientY) {
                        if (!this.painting || !this._canvasCtx) return;

                        const {
                            x,
                            y,
                            scale
                        } = this._getCanvasXY(clientX, clientY);
                        const ctx = this._canvasCtx;
                        const lineW = this.brushSize * scale;

                        ctx.strokeStyle = 'rgb(251, 191, 36)';
                        ctx.lineWidth = lineW;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';

                        ctx.beginPath();
                        ctx.moveTo(this._lastPaintX, this._lastPaintY);
                        ctx.lineTo(x, y);
                        ctx.stroke();

                        this._lastPaintX = x;
                        this._lastPaintY = y;
                        this.hasHighlights = true;
                    },

                    _endStroke() {
                        if (!this.painting) return;
                        this.painting = false;
                    },

                    clearHighlights() {
                        const canvas = this.$refs.highlightCanvas;
                        if (canvas && this._canvasCtx) {
                            if (this.hasHighlights) {
                                this._saveSnapshot();
                            }
                            this._canvasCtx.clearRect(0, 0, canvas.width, canvas.height);
                        }
                        this.hasHighlights = false;
                    },

                    async getMaskImageBlob() {
                        const srcCanvas = this.$refs.highlightCanvas;
                        const img = this.$refs.generatedImage;
                        if (!srcCanvas || !img || !this.hasHighlights) return null;

                        const w = img.naturalWidth;
                        const h = img.naturalHeight;

                        const maskCanvas = document.createElement('canvas');
                        maskCanvas.width = w;
                        maskCanvas.height = h;
                        const ctx = maskCanvas.getContext('2d');

                        ctx.drawImage(img, 0, 0, w, h);

                        const srcCtx = srcCanvas.getContext('2d');
                        const srcData = srcCtx.getImageData(0, 0, w, h);
                        const maskData = ctx.getImageData(0, 0, w, h);

                        for (let i = 3; i < srcData.data.length; i += 4) {
                            if (srcData.data[i] > 0) {
                                maskData.data[i - 3] = 0;
                                maskData.data[i - 2] = 0;
                                maskData.data[i - 1] = 0;
                                maskData.data[i] = 0;
                            }
                        }

                        ctx.putImageData(maskData, 0, 0);

                        return new Promise((resolve) => {
                            maskCanvas.toBlob(resolve, 'image/png');
                        });
                    },

                    async getOriginalImageBlob() {
                        if (this.currentImage?._file) {
                            return this.currentImage._file;
                        }

                        if (this.currentImage?.url) {
                            try {
                                const response = await fetch(this.currentImage.url);
                                return await response.blob();
                            } catch (e) {
                                console.error('Failed to fetch image:', e);
                                return null;
                            }
                        }

                        return null;
                    },

                    // --- Generation ---

                    async generate() {
                        if (this.busy) return;
                        if (!this.currentImage) {
                            if (window.toastr) toastr.error('{{ __('Please upload an image first') }}');
                            return;
                        }

                        const userPrompt = this.prompt.trim();
                        if (!userPrompt && this.selectedTab !== 'remove_background') {
                            if (window.toastr) toastr.error('{{ __('Please enter a prompt') }}');
                            return;
                        }

                        this.busy = true;

                        try {
                            const imageBlob = await this.getOriginalImageBlob();

                            if (!imageBlob) {
                                if (window.toastr) toastr.error('{{ __('Failed to prepare the image') }}');
                                this.busy = false;
                                return;
                            }

                            const formData = new FormData();
                            formData.append('_token', '{{ csrf_token() }}');
                            formData.append('prompt', userPrompt);
                            formData.append('template_type', 'chatPro-image');

                            formData.append('edit_tab', this.selectedTab);
                            formData.append('edit_mode', this.mode);
                            formData.append('edit_has_highlights', this.hasHighlights ? '1' : '0');

                            if (this.chatId) {
                                formData.append('chat_id', this.chatId);
                            }

                            const imageFile = new File([imageBlob], 'image.png', {
                                type: 'image/png'
                            });
                            formData.append('image_reference', imageFile);

                            if (this.mode === 'visual' && this.hasHighlights) {
                                const maskBlob = await this.getMaskImageBlob();
                                if (maskBlob) {
                                    const maskFile = new File([maskBlob], 'mask.png', {
                                        type: 'image/png'
                                    });
                                    formData.append('mask_image', maskFile);
                                }
                            }

                            await this.sendStreamRequest(formData);

                        } catch (error) {
                            console.error('Generation error:', error);
                            if (window.toastr) toastr.error('{{ __('An error occurred during generation') }}');
                            this.busy = false;
                        }
                    },

                    async sendStreamRequest(formData) {
                        let receivedImageRecord = false;
                        const abortController = new AbortController();

                        await fetchEventSource('/dashboard/user/generator/generate-stream-edit-image', {
                            openWhenHidden: true,
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                            },
                            body: formData,
                            signal: abortController.signal,

                            onmessage: (event) => {
                                const data = event.data;

                                if (event.event === 'image_record' && !receivedImageRecord) {
                                    receivedImageRecord = true;
                                    const recordId = parseInt(data, 10);
                                    if (recordId && !this._processedRecords.has(recordId)) {
                                        this._processedRecords.add(recordId);
                                        this.startImagePolling(recordId);
                                    }
                                }

                                if (event.event === 'message' || (!event.event && data === '[DONE]')) {
                                    // Stream done
                                }
                            },

                            onclose: () => {
                                if (!receivedImageRecord) {
                                    this.busy = false;
                                }
                            },

                            onerror: (err) => {
                                console.error('Stream error:', err);

                                if (err?.response?.clone) {
                                    err.response
                                        .clone()
                                        .text()
                                        .then((body) => {
                                            console.groupCollapsed('AI Image stream raw response');
                                            console.log(body);
                                            console.groupEnd();
                                        })
                                        .catch((bodyError) => {
                                            console.warn('Unable to read stream response body:', bodyError);
                                        });
                                }

                                if (window.toastr) toastr.error('{{ __('An error occurred during generation') }}');
                                this.busy = false;
                                throw err;
                            },
                        });
                    },

                    async downloadImage() {
                        if (!this.currentImage?.url) return;

                        try {
                            const img = this.$refs.generatedImage;
                            const canvas = document.createElement('canvas');
                            canvas.width = img.naturalWidth;
                            canvas.height = img.naturalHeight;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(img, 0, 0);

                            const blob = await new Promise((resolve) => {
                                canvas.toBlob(resolve, 'image/png');
                            });

                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = (this.currentImage.title || 'image').replace(/\.[^.]+$/, '').split(/\s+/).slice(0, 5).join('-') + '.png';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                        } catch (e) {
                            console.error('Download failed:', e);
                            if (window.toastr) toastr.error('{{ __('Failed to download image') }}');
                        }
                    },

                    startImagePolling(recordId) {
                        if (this._activePolls.has(recordId)) {
                            clearInterval(this._activePolls.get(recordId));
                        }

                        let attempts = 0;
                        const maxAttempts = 60;

                        const checkStatus = async () => {
                            attempts++;

                            if (attempts > maxAttempts) {
                                clearInterval(pollInterval);
                                this._activePolls.delete(recordId);
                                this._processedRecords.delete(recordId);
                                if (window.toastr) toastr.error('{{ __('Image generation timed out') }}');
                                this.busy = false;
                                return;
                            }

                            try {
                                const response = await fetch(`/dashboard/user/generator/check-image-status/${recordId}`, {
                                    method: 'GET',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                        'Accept': 'application/json',
                                    },
                                });

                                if (!response.ok) return;

                                const data = await response.json();

                                if (data.status === 'completed' && data.paths?.length > 0) {
                                    clearInterval(pollInterval);
                                    this._activePolls.delete(recordId);
                                    this._processedRecords.delete(recordId);

                                    const newImageUrl = data.paths[0];
                                    const title = this.prompt || 'edited image';

                                    this.setCurrentImage({
                                        url: newImageUrl,
                                        title,
                                    });

                                    if (!this.imageHistory.some(h => h.url === newImageUrl)) {
                                        this.imageHistory.unshift({
                                            id: `edit-${recordId}`,
                                            url: newImageUrl,
                                            title: title,
                                        });
                                    }

                                    this.prompt = '';
                                    this.busy = false;

                                    if (window.toastr) {
                                        toastr.remove();
                                        toastr.success('{{ __('Image edited successfully') }}');
                                    }
                                } else if (data.status === 'failed') {
                                    clearInterval(pollInterval);
                                    this._activePolls.delete(recordId);
                                    this._processedRecords.delete(recordId);

                                    if (window.toastr) toastr.error(data.error || '{{ __('Image generation failed') }}');
                                    this.busy = false;
                                }
                            } catch (error) {
                                console.error('Polling error:', error);
                            }
                        };

                        checkStatus();
                        const pollInterval = setInterval(checkStatus, 5000);
                        this._activePolls.set(recordId, pollInterval);
                    },
                }));
            });
        })();
    </script>
@endpush
