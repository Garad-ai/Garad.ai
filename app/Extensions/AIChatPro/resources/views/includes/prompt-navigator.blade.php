{{-- Desktop: vertical lines on the right side --}}
<div
    class="lqd-prompt-nav group z-30 transition-all lg:invisible lg:absolute lg:end-5 lg:top-1/2 lg:-translate-y-1/2 lg:opacity-0 lg:[&.is-visible]:visible lg:[&.is-visible]:opacity-100"
    x-data="promptNavigator"
    x-cloak
    :class="{ 'is-visible': isVisible }"
>
    <div
        class="transition-all max-lg:absolute max-lg:start-0 max-lg:top-20 max-lg:z-10 max-lg:h-0 max-lg:w-full max-lg:overflow-y-auto lg:relative lg:flex lg:items-center lg:gap-4 max-lg:[&.is-active]:h-[calc(100%-80px)]"
        :class="{ 'is-visible': isVisible, 'is-active': mobilePromptNavShow }"
        @mouseleave="hoveredLineIndex = -1; hoveredPromptIndex = -1; showList = false"
    >
        {{-- Prompt list popover (to the left of lines) --}}
        <div
            class="{{-- blade-formatter-disable --}}
				no-scrollbar px-3.5 py-2 transition-all overflow-x-hidden
				lg:invisible lg:max-h-[260px] lg:w-72 lg:translate-x-1 lg:overflow-y-auto lg:rounded-2xl lg:bg-surface-background lg:opacity-0 lg:shadow-[0_4px_12px_hsl(0_0%_0%/5%)]
				lg:[&.is-active]:visible lg:[&.is-active]:translate-x-0 lg:[&.is-active]:opacity-100
				max-lg:bg-background
				{{-- blade-formatter-enable --}}"
            :class="{ 'is-active': showList }"
            x-ref="promptList"
            @mouseenter="showList = true"
            @mouseleave="scrollLineIntoView(activeIndex)"
        >
            <template
                x-for="(p, i) in prompts"
                :key="'list-' + i"
            >
                <button
                    class="block w-full cursor-pointer truncate rounded-lg border-none bg-transparent py-2.5 text-start text-2xs leading-tight text-foreground/70 transition-all [&.is-active]:bg-foreground/10 [&.is-active]:px-3 [&.is-active]:font-medium [&.is-active]:text-foreground [&.is-hover]:bg-foreground/10 [&.is-hover]:px-3 [&.is-hover]:text-foreground"
                    :class="{
                        'is-hover': hoveredPromptIndex === i,
                        'is-active': activeIndex === i && hoveredPromptIndex !== i
                    }"
                    type="button"
                    @click="scrollTo(i)"
                    @mouseenter="hoveredPromptIndex = i; highlightLineForPrompt(i)"
                    x-text="truncate(p.text, 55)"
                >
                </button>
            </template>
        </div>

        {{-- Lines (max 5 visible, scrollable, hidden scrollbar) --}}
        <div
            class="lqd-prompt-nav-lines no-scrollbar flex max-h-[75px] flex-col items-center gap-2.5 overflow-y-auto py-2 max-lg:hidden"
            x-ref="linesContainer"
            @mouseenter="showList = true"
        >
            <template
                x-for="(line, i) in lines"
                :key="'line-' + i"
            >
                <button
                    class="relative h-0.5 w-5 shrink-0 rounded-full bg-foreground/15 transition-all before:absolute before:-inset-y-1.5 before:inset-x-0 hover:bg-foreground/50 [&.is-active]:bg-foreground [&.is-hover]:bg-foreground/50"
                    :class="{ 'is-hover': hoveredLineIndex === i, 'is-active': activeIndex === i && hoveredLineIndex !== i }"
                    type="button"
                    @click="scrollToLine(i)"
                    @mouseenter="hoveredLineIndex = i; hoveredPromptIndex = lines[i]?.promptIndex ?? -1; scrollPromptIntoView(lines[i]?.promptIndex ?? -1)"
                >
                    <span
                        class="sr-only"
                        x-text="'{{ __('Go to prompt') }} ' + (i + 1)"
                    ></span>
                </button>
            </template>
        </div>
    </div>
</div>

@pushOnce('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('promptNavigator', () => ({
                minPrompts: 5,
                prompts: [],
                lines: [],
                activeIndex: -1,
                isVisible: false,
                hoveredLineIndex: -1,
                hoveredPromptIndex: -1,
                showList: false,
                elements: [],
                observer: null,
                observedTarget: null,
                scrollArea: null,
                scrollPending: false,
                programmaticScroll: false,

                init() {
                    document.addEventListener('chat:navigator-refresh', () => {
                        setTimeout(() => this.refresh(), 100);
                    });

                    this.$nextTick(() => {
                        this.refresh();
                    });
                },

                bindScroll() {
                    const area = document.querySelector('.conversation-area');

                    if (!area || area === this.scrollArea) return;

                    this.scrollArea = area;

                    area.addEventListener('scroll', () => {
                        if (this.scrollPending) return;
                        this.scrollPending = true;
                        requestAnimationFrame(() => {
                            this.updateActiveIndex();
                            this.scrollPending = false;
                        });
                    }, {
                        passive: true
                    });
                },

                refresh() {
                    const container = document.querySelector('.chats-container');
                    if (!container) {
                        this.elements = [];
                        this.prompts = [];
                        this.lines = [];
                        this.activeIndex = -1;
                        this.isVisible = false;
                        this.syncVisible(false);
                        return;
                    }

                    const bubbles = container.querySelectorAll(':scope > .lqd-chat-user-bubble');

                    this.elements = Array.from(bubbles).map(el => ({
                        text: el.querySelector('.chat-content')?.textContent?.trim() || '',
                        el
                    }));

                    this.prompts = this.elements.map(e => ({
                        text: e.text
                    }));

                    this.lines = this.prompts.map((p, i) => ({
                        text: p.text.length > 80 ? p.text.substring(0, 80) + '...' : p.text,
                        promptIndex: i
                    }));

                    this.isVisible = this.elements.length >= this.minPrompts;
                    this.syncVisible(this.isVisible);

                    if (this.isVisible) {
                        this.updateActiveIndex();
                    }

                    this.setupObserver();
                    this.bindScroll();

                    this.$nextTick(() => {
                        this.scrollLineIntoView(this.activeIndex);
                    })
                },

                syncVisible(isVisible) {
                    this.promptNavVisible = isVisible;
                },

                setupObserver() {
                    const target = document.querySelector('.chats-container');
                    if (this.observer && this.observedTarget === target) return;

                    if (this.observer) {
                        this.observer.disconnect();
                        this.observer = null;
                        this.observedTarget = null;
                    }

                    if (!target) return;

                    this.observer = new MutationObserver(() => this.refresh());
                    this.observer.observe(target, {
                        childList: true
                    });
                    this.observedTarget = target;
                },

                updateActiveIndex() {
                    if (this.programmaticScroll) return;

                    const area = document.querySelector('.conversation-area');

                    if (!area || !this.elements.length) return;

                    const areaRect = area.getBoundingClientRect();
                    const areaCenter = areaRect.top + areaRect.height / 2;
                    let closest = 0;
                    let closestDist = Infinity;

                    for (let i = 0; i < this.elements.length; i++) {
                        const el = this.elements[i].el;

                        if (!el || !el.isConnected) continue;

                        const elRect = el.getBoundingClientRect();
                        const elCenter = elRect.top + elRect.height / 2;
                        const dist = Math.abs(elCenter - areaCenter);

                        if (dist < closestDist) {
                            closestDist = dist;
                            closest = i;
                        }
                    }

                    this.activeIndex = closest;

                    this.scrollLineIntoView(closest);
                },

                scrollTo(index) {
                    const entry = this.elements[index];
                    if (!entry?.el) return;

                    const area = document.querySelector('.conversation-area');

                    if (!area) return;

                    this.programmaticScroll = true;
                    this.activeIndex = index;
                    this.scrollLineIntoView(index);

                    const areaRect = area.getBoundingClientRect();
                    const elRect = entry.el.getBoundingClientRect();
                    const offset = elRect.top - areaRect.top + area.scrollTop - (areaRect.height / 2) + (elRect.height / 2);

                    area.scrollTo({
                        top: Math.max(0, offset),
                        behavior: 'smooth'
                    });

                    // Re-enable scroll-based tracking after animation settles
                    clearTimeout(this._scrollTimer);
                    this._scrollTimer = setTimeout(() => {
                        this.programmaticScroll = false;
                    }, 600);

                    this.mobilePromptNavShow = false;
                },

                scrollToLine(lineIndex) {
                    const line = this.lines[lineIndex];

                    if (line) this.scrollTo(line.promptIndex);
                },

                highlightLineForPrompt(promptIndex) {
                    const idx = this.lines.findIndex(l => l.promptIndex === promptIndex);

                    this.hoveredLineIndex = idx !== -1 ? idx : 0;

                    this.scrollLineIntoView(this.hoveredLineIndex);
                },

                scrollLineIntoView(lineIndex) {
                    if (lineIndex < 0) return;

                    const btn = this.$refs.linesContainer.querySelectorAll('button')[lineIndex];

                    if (!btn) return;

                    btn.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                },

                scrollPromptIntoView(promptIndex) {
                    if (promptIndex < 0) return;

                    const btn = this.$refs.promptList.querySelectorAll('button')[promptIndex];

                    if (!btn) return;

                    btn.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                },

                truncate(text, maxLen = 50) {
                    if (!text) return '';

                    return text.length > maxLen ? text.substring(0, maxLen) + '...' : text;
                }
            }));
        });
    </script>
@endPushOnce
