@php
    $theme = setting('dash_theme', 'default');

    $imageStyles = [
        '' => 'None',
        '3d_render' => '3D Render',
        'anime' => 'Anime',
        'cartoon' => 'Cartoon',
        'cyberpunk' => 'Cyberpunk',
        'impressionism' => 'Impressionism',
        'line' => 'Line Art',
        'low_poly' => 'Low Poly',
        'minimalism' => 'Minimalism',
        'origami' => 'Origami',
        'pencil' => 'Pencil Drawing',
        'pixel' => 'Pixel',
        'pop' => 'Pop',
        'realistic' => 'Realistic',
        'retro' => 'Retro',
        'steampunk' => 'Steampunk',
        'vector' => 'Vector',
        'watercolor' => 'Watercolor',
    ];
@endphp

@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
    'layout_wide' => true,
])

@section('title', __('Realtime Image Generator'))

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
            'lqd-rt-gen relative flex overflow-hidden [--navbar-height:60px] [--sidebar-w:76px] lg:h-screen',
            'h-[calc(100vh-var(--header-height)-var(--bottom-menu-height))]' => auth()->check(),
            'h-screen' => !auth()->check(),
        ])
        style="background-image: radial-gradient(hsl(var(--foreground)/20%) 1px, transparent 0px); background-size: 28px 28px; background-position: center;"
        x-data="realtimeImageGenerator"
    >
        {{-- Top Navbar --}}
        <header
            class="lqd-rt-gen-header fixed inset-x-[var(--body-padding,0px)] top-[calc(var(--body-padding,var(--navbar-height,0px)))+var(--header-height,var(--header-h,0px))] z-50 flex h-[--navbar-height] items-center gap-1 px-4 py-4 md:px-6 lg:top-[var(--body-padding,0px)] lg:z-10 lg:ps-[--sidebar-w]"
        >
            <x-button
                class="size-8 rounded-full bg-surface-background text-3xs font-semibold text-surface-foreground hover:bg-primary hover:text-primary-foreground lg:hidden"
                size="none"
                size="none"
                type="button"
                @click="mobile.showHistory = !mobile.showHistory"
            >
                <x-tabler-history class="size-5" />
            </x-button>

            <div
                class="flex items-center gap-3"
                x-show="lastImage"
                x-cloak
                @click.outside="mobile.showOpenIn = false"
            >
                <x-button
                    class="rounded-full bg-surface-background px-3 py-2 text-3xs font-semibold text-surface-foreground hover:bg-primary hover:text-primary-foreground lg:hidden"
                    type="button"
                    @click="mobile.showOpenIn = !mobile.showOpenIn"
                >
                    {{ __('Open in:') }}
                </x-button>

                <div
                    class="flex flex-col items-start gap-2 max-lg:absolute max-lg:start-6 max-lg:top-full max-lg:rounded-lg max-lg:bg-surface-background/50 max-lg:p-2 max-lg:shadow-lg max-lg:shadow-black/10 max-lg:backdrop-blur-md lg:contents"
                    :class="{ 'hidden': !mobile.showOpenIn }"
                >
                    <x-button
                        class="rounded-full bg-surface-background px-3 py-2 text-3xs font-semibold text-surface-foreground hover:bg-primary hover:text-primary-foreground max-lg:w-full max-lg:justify-start max-lg:rounded-md max-lg:text-start"
                        type="button"
                        x-show="lastImage"
                        @click="editWithEditor(lastImage)"
                    >
                        {{ __('Open with Image Editor') }}
                    </x-button>

                    @if ($isCreativeSuiteInstalled ?? false)
                        <x-button
                            class="rounded-full bg-surface-background px-3 py-2 text-3xs font-semibold text-surface-foreground hover:bg-primary hover:text-primary-foreground max-lg:w-full max-lg:justify-start max-lg:rounded-md max-lg:text-start"
                            type="button"
                            x-show="lastImage"
                            @click="openWithCreativeSuite(lastImage)"
                        >
                            {{ __('Open with Creative Suite') }}
                        </x-button>
                    @endif
                </div>
            </div>

            <div class="ms-auto flex items-center justify-end gap-2">
                {{-- Zoom Controls --}}
                <div
                    class="flex items-center gap-0.5 rounded-full bg-surface-background/90 px-1.5 py-1 text-2xs font-medium shadow-sm backdrop-blur-sm transition-opacity"
                    :class="{ 'opacity-0 pointer-events-none': !lastImage && !busy }"
                >
                    <button
                        class="inline-flex size-7 items-center justify-center rounded-full transition hover:bg-foreground/5"
                        type="button"
                        title="{{ __('Zoom out') }}"
                        @click="zoomBy(0.8)"
                    >
                        <x-tabler-minus class="size-3.5" />
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
                        <x-tabler-plus class="size-3.5" />
                    </button>
                </div>

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
            class="lqd-rt-gen-sidebar pointer-events-none fixed bottom-[var(--body-padding,0px)] start-[var(--body-padding,0px)] top-[var(--body-padding,0px)] z-10 flex w-[--sidebar-w] flex-col justify-between gap-2 px-1.5 lg:z-20"
        >

            {{-- Logo --}}
            <div class="pointer-events-auto flex h-[--navbar-height] w-full items-center justify-center">
                <div class="hidden w-full lg:block">
                    <x-navbar.navbar-logo />
                </div>
            </div>

            {{-- Generation History --}}
            <div
                class="pointer-events-auto relative w-full transition max-lg:-translate-x-full max-lg:[&.active]:translate-x-0"
                :class="{ 'active': mobile.showHistory }"
            >
                <div
                    class="no-scrollbar flex h-[min(285px,50vh)] flex-col items-center gap-1.5 overflow-y-auto overscroll-contain rounded-md bg-surface-background px-2.5 py-3 shadow-md shadow-black/5"
                    x-show="allImages.length > 0"
                    x-cloak
                    x-transition
                >
                    <x-button
                        class="size-[42px] shrink-0"
                        size="none"
                        type="button"
                        title="{{ __('New generation') }}"
                        @click="prompt = ''; debouncedPrompt = ''; lastImage = null"
                    >
                        <x-tabler-plus class="size-5" />
                    </x-button>

                    <template
                        x-for="(image, index) in allImages"
                        :key="image.id"
                    >
                        <button
                            class="inline-flex size-12 shrink-0 overflow-hidden rounded-md opacity-70 transition hover:scale-105 hover:opacity-100 [&.active]:opacity-100"
                            :class="{ 'active': lastImage?.id === image.id }"
                            type="button"
                            @click="selectImage(image); mobile.showHistory = false"
                        >
                            <img
                                class="size-full object-cover object-center"
                                :src="image.image_url"
                                :alt="image.prompt"
                                loading="lazy"
                            >
                        </button>
                    </template>
                </div>
            </div>

            <div class="pointer-events-auto h-[--navbar-height]">

            </div>
        </aside>

        <div
            class="flex grow flex-col overflow-hidden"
            x-ref="canvasContainer"
        >
            {{-- Center: Image Display + Prompt --}}
            <main class="relative flex flex-1 flex-col">
                {{-- Image Display Area / Canvas Viewport --}}
                <div
                    class="lqd-rt-gen-canvas relative flex-1 touch-none"
                    x-ref="canvasViewport"
                    @wheel.prevent="handleCanvasWheel($event)"
                    @mousedown="handleCanvasMouseDown($event)"
                    @mousemove.window="handleCanvasMouseMove($event)"
                    @mouseup.window="handleCanvasMouseUp()"
                    @touchstart="handleTouchStart($event)"
                    @touchmove.prevent="handleTouchMove($event)"
                    @touchend="handleTouchEnd($event)"
                    @touchcancel="handleTouchEnd($event)"
                    :class="{
                        'cursor-grab select-none': isSpaceHeld && !isPanning,
                        'cursor-grabbing select-none': isPanning,
                    }"
                >
                    {{-- Empty State --}}
                    <div
                        class="absolute inset-0 flex items-center justify-center px-5 py-20 text-center"
                        x-show="!lastImage && !busy"
                    >
                        <h2 class="m-0 text-pretty text-[24px] font-medium">
                            <span class="block text-[0.75em]">
                                <span class="opacity-50">
                                    {{ __('Realtime Image Generator') }}
                                </span>
                                👋
                            </span>
                            {{ __('Start typing to see your image appear.') }}
                        </h2>
                    </div>

                    {{-- Loading State (first generation) --}}
                    <div
                        class="absolute inset-0 flex items-center justify-center"
                        x-show="busy && !lastImage"
                        x-cloak
                    >
                        <x-tabler-loader-2 class="size-8 animate-spin text-primary" />
                    </div>

                    {{-- Zoomable / Pannable Canvas --}}
                    <div
                        class="absolute will-change-transform"
                        :style="`transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: 0 0;`"
                        x-show="lastImage"
                        x-cloak
                    >
                        <figure class="relative">
                            <img
                                class="max-w-none rounded-lg shadow-lg transition-opacity duration-300"
                                :class="{ 'opacity-50': busy }"
                                x-ref="generatedImage"
                                :src="lastImage?.image_url || ''"
                                :alt="lastImage?.prompt || ''"
                                @load="fitToScreen()"
                            >

                            {{-- Spinner on image while regenerating --}}
                            <div
                                class="absolute end-3 top-3"
                                x-show="busy"
                                x-cloak
                            >
                                <x-tabler-loader-2 class="size-5 animate-spin text-primary" />
                            </div>
                        </figure>
                    </div>
                </div>

                {{-- Bottom Prompt Bar --}}
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 z-10 p-5 sm:pb-12"
                    x-ref="promptBar"
                >
                    <x-cost-preview class="pointer-events-auto mx-auto mt-0 mb-2 w-[min(595px,100%)] justify-end" />
                    <form
                        class="pointer-events-auto mx-auto flex w-[min(595px,100%)] items-center gap-1 rounded-2xl bg-surface-background pe-5 shadow-2xl shadow-black/10 transition focus-within:ring-2 focus-within:ring-primary/10 dark:border md:rounded-3xl"
                        @submit.prevent="changePrompt"
                    >
                        @csrf

                        <x-forms.input
                            class:container="grow"
                            class="min-h-16 border-none bg-transparent px-5 py-3 placeholder:text-foreground focus:!border-transparent focus:!ring-0 focus-visible:outline-none focus-visible:ring-0 sm:text-sm md:min-h-[88px]"
                            name="prompt"
                            placeholder="{{ __('Describe your idea') }}"
                            autocomplete="off"
                            x-model="prompt"
                            x-ref="promptInput"
                            @input="onPromptInput"
                        />

                        <x-button
                            class="size-11 shrink-0 md:size-[50px]"
                            size="none"
                            type="submit"
                            title="{{ __('Generate') }}"
                            ::disabled="busy || !prompt.trim()"
                        >
                            <svg
                                x-show="!busy"
                                width="17"
                                height="16"
                                viewBox="0 0 17 16"
                                fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M5.61131 13.2107L5.98931 13.1337C6.0958 13.1131 6.1918 13.056 6.26083 12.9724C6.32985 12.8887 6.36761 12.7836 6.36761 12.6752C6.36761 12.5667 6.32985 12.4616 6.26083 12.378C6.1918 12.2943 6.0958 12.2373 5.98931 12.2167L5.61131 12.1397C5.14482 12.045 4.71658 11.8149 4.38008 11.4782C4.04359 11.1416 3.81376 10.7132 3.71931 10.2467L3.64231 9.86866C3.62171 9.76217 3.56468 9.66617 3.48102 9.59714C3.39735 9.52811 3.29227 9.49036 3.18381 9.49036C3.07534 9.49036 2.97026 9.52811 2.88659 9.59714C2.80293 9.66617 2.74591 9.76217 2.72531 9.86866L2.6483 10.2467C2.5538 10.7131 2.32393 11.1413 1.98743 11.4778C1.65093 11.8143 1.22271 12.0442 0.756305 12.1387L0.378305 12.2157C0.271815 12.2363 0.175815 12.2933 0.106785 12.3769C0.0377549 12.4606 0 12.5657 0 12.6742C0 12.7826 0.0377549 12.8877 0.106785 12.9714C0.175815 13.055 0.271815 13.1121 0.378305 13.1327L0.756305 13.2097C1.22258 13.3041 1.6507 13.5338 1.98719 13.8701C2.32368 14.2064 2.55362 14.6344 2.6483 15.1007L2.72531 15.4787C2.74591 15.5852 2.80293 15.6812 2.88659 15.7502C2.97026 15.8192 3.07534 15.857 3.18381 15.857C3.29227 15.857 3.39735 15.8192 3.48102 15.7502C3.56468 15.6812 3.62171 15.5852 3.64231 15.4787L3.71931 15.1007C3.81411 14.6345 4.04409 14.2066 4.38056 13.8703C4.71703 13.534 5.14508 13.3052 5.61131 13.2107Z"
                                />
                                <path
                                    d="M14.7761 6.36263L16.1681 6.08063C16.3069 6.05221 16.4317 5.97673 16.5212 5.86695C16.6108 5.75717 16.6597 5.61982 16.6597 5.47813C16.6597 5.33644 16.6108 5.1991 16.5212 5.08932C16.4317 4.97954 16.3069 4.90405 16.1681 4.87563L14.7761 4.59563C14.1057 4.45959 13.4902 4.12906 13.0064 3.64532C12.5227 3.16159 12.1922 2.54607 12.0561 1.87563L11.7741 0.483631C11.7443 0.346631 11.6686 0.223943 11.5594 0.135961C11.4503 0.047978 11.3143 0 11.1741 0C11.0339 0 10.8979 0.047978 10.7888 0.135961C10.6796 0.223943 10.6039 0.346631 10.5741 0.483631L10.2921 1.87563C10.1562 2.54613 9.8257 3.16171 9.34195 3.64547C8.85819 4.12922 8.24261 4.45971 7.57211 4.59563L6.18011 4.87763C6.0413 4.90605 5.91656 4.98153 5.82698 5.09132C5.7374 5.2011 5.68848 5.33844 5.68848 5.48013C5.68848 5.62182 5.7374 5.75917 5.82698 5.86895C5.91656 5.97873 6.0413 6.05421 6.18011 6.08263L7.57211 6.36463C8.24266 6.50043 8.85831 6.83088 9.34209 7.31466C9.82587 7.79843 10.1563 8.41408 10.2921 9.08463L10.5741 10.4766C10.6039 10.6136 10.6796 10.7363 10.7888 10.8243C10.8979 10.9123 11.0339 10.9603 11.1741 10.9603C11.3143 10.9603 11.4503 10.9123 11.5594 10.8243C11.6686 10.7363 11.7443 10.6136 11.7741 10.4766L12.0561 9.08463C12.1922 8.41419 12.5227 7.79868 13.0064 7.31494C13.4902 6.8312 14.1057 6.50068 14.7761 6.36463V6.36263Z"
                                />
                            </svg>
                            <x-tabler-loader-2
                                class="size-5 animate-spin"
                                x-show="busy"
                                x-cloak
                            />
                        </x-button>
                    </form>
                </div>
            </main>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('realtimeImageGenerator', () => ({
                    prompt: '',
                    debouncedPrompt: '',
                    lastImage: null,
                    busy: false,
                    requestSent: false,
                    newImages: [],
                    serverImages: @json($formattedImages ?? []),
                    isDemo: {{ $app_is_demo ? 'true' : 'false' }},

                    // Canvas zoom & pan state
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

                    mobile: {
                        showHistory: false,
                        showOpenIn: false
                    },

                    init() {
                        this.changePrompt = Alpine.debounce(this.changePrompt.bind(this), 350);

                        if (this.serverImages.length > 0) {
                            this.lastImage = this.serverImages[0];
                        }

                        this.$refs.promptInput?.focus();

                        // Keyboard listeners for space-to-pan
                        this._handleKeyDown = (e) => {
                            if (e.code === 'Space' && !e.target.matches('input, textarea, [contenteditable]')) {
                                e.preventDefault();
                                this.isSpaceHeld = true;
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

                        // Reset zoom/pan when image is cleared
                        this.$watch('lastImage', (val) => {
                            if (!val) {
                                this.zoom = 1;
                                this.panX = 0;
                                this.panY = 0;
                            }
                        });

                        // Dispatch generator for cost preview
                        this.$nextTick(() => {
                            this.$dispatch('generator-changed', {
                                generator: '{{ \App\Domains\Entity\Enums\EntityEnum::BLACK_FOREST_LABS_FLUX_1_SCHNELL->value }}',
                            });
                        });
                    },

                    get allImages() {
                        const newIds = new Set(this.newImages.map(img => img.id));
                        const filtered = this.serverImages.filter(img => !newIds.has(img.id));

                        return [...this.newImages, ...filtered];
                    },

                    isAdvancedImageInstalled: {{ $isAdvancedImageInstalled ?? false ? 'true' : 'false' }},
                    isCreativeSuiteInstalled: {{ $isCreativeSuiteInstalled ?? false ? 'true' : 'false' }},

                    destroy() {
                        window.removeEventListener('keydown', this._handleKeyDown);
                        window.removeEventListener('keyup', this._handleKeyUp);
                    },

                    // --- Canvas zoom & pan ---

                    handleCanvasWheel(e) {
                        if (e.metaKey || e.ctrlKey) {
                            // Zoom toward cursor
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
                            // Pan via scroll / trackpad
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

                    handleCanvasMouseUp() {
                        this.isPanning = false;
                    },

                    // --- Touch support ---

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
                        if (!this.lastImage && !this.busy) return;

                        this._activeTouches = e.touches.length;

                        if (e.touches.length === 1) {
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

                            // Pinch zoom
                            const newZoom = Math.max(
                                this.minZoom,
                                Math.min(this.maxZoom, this._touchStartZoom * (dist / this._touchStartDist))
                            );
                            const scale = newZoom / this.zoom;

                            // Zoom toward pinch midpoint
                            this.panX = midX - (midX - this.panX) * scale;
                            this.panY = midY - (midY - this.panY) * scale;
                            this.zoom = newZoom;

                            // Two-finger pan
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

                    fitToScreen() {
                        this.$nextTick(() => requestAnimationFrame(() => {
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
                        }));
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

                    selectImage(image) {
                        this.lastImage = image;
                        this.prompt = image.prompt || '';
                        this.debouncedPrompt = image.prompt || '';
                    },

                    editWithEditor(image) {
                        if (!image || !image.image_url) {
                            if (window.toastr) {
                                toastr.error('{{ __('No image to edit') }}');
                            }
                            return;
                        }

                        sessionStorage.setItem('pendingImageForEditor', JSON.stringify({
                            url: image.image_url,
                            title: image.prompt || 'image',
                        }));
                        window.location.href = '{{ route(auth()->check() ? 'dashboard.user.ai-image-pro.edit' : 'ai-image-pro.edit') }}';
                    },

                    openWithCreativeSuite(image) {
                        if (!this.isCreativeSuiteInstalled) {
                            if (window.toastr) {
                                toastr.error('{{ __('Creative Suite extension is not installed') }}');
                            }
                            return;
                        }

                        if (!image || !image.image_url) {
                            if (window.toastr) {
                                toastr.error('{{ __('No image to open') }}');
                            }
                            return;
                        }

                        sessionStorage.setItem('pendingImageForCreativeSuite', JSON.stringify({
                            url: image.image_url,
                            prompt: image.prompt || '',
                            width: image.width || null,
                            height: image.height || null,
                        }));
                        @if (($isCreativeSuiteInstalled ?? false) && Route::has('dashboard.user.creative-suite.index'))
                            window.location.href = '{{ route('dashboard.user.creative-suite.index') }}';
                        @endif
                    },

                    onPromptInput() {
                        if (this.isDemo) {
                            if (window.toastr) {
                                toastr.remove();
                                toastr.info('{{ __('This feature is disabled in demo mode.') }}');
                            }
                            return;
                        }

                        if (this.prompt.trim().length === 0 || this.debouncedPrompt.trim() === this.prompt.trim()) {
                            return;
                        }

                        this.busy = true;
                        this.changePrompt();
                    },

                    async changePrompt() {
                        if (this.isDemo) {
                            this.busy = false;
                            this.requestSent = false;
                            return;
                        }

                        const currentPrompt = this.prompt.trim();

                        if (currentPrompt.length === 0) {
                            this.busy = false;

                            return;
                        }

                        this.busy = true;
                        this.requestSent = true;
                        this.debouncedPrompt = currentPrompt;

                        const formData = new FormData();
                        formData.append('prompt', currentPrompt);

                        try {
                            const response = await fetch('{{ route('ai-image-pro.realtime.generate') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                },
                                body: formData,
                            });

                            const data = await response.json();

                            if (response.ok && data.success) {
                                const imageData = data.data;
                                this.newImages.unshift(imageData);
                                this.lastImage = imageData;

                                if (window.toastr) {
                                    toastr.remove();
                                    toastr.success(data.message || '{{ __('Image generated successfully') }}');
                                }
                            } else {
                                if (window.toastr) {
                                    toastr.remove();
                                    toastr.error(data.message || '{{ __('Failed to generate image') }}');
                                }
                            }
                        } catch (error) {
                            console.error('Realtime generation error:', error);
                            if (window.toastr) {
                                toastr.remove();
                                toastr.error('{{ __('An error occurred while generating the image') }}');
                            }
                        } finally {
                            this.busy = false;
                            this.requestSent = false;
                        }
                    },
                }));
            });
        })();
    </script>
@endpush
