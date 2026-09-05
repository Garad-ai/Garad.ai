@if (class_exists(\App\Extensions\AiChatProHighlightToAsk\System\Services\HighlightToAskService::class) &&
        \App\Extensions\AiChatProHighlightToAsk\System\Services\HighlightToAskService::isEnabled())
    <template x-teleport="body">
        <div
            class="fixed z-[9999] select-none"
            x-data="lqdHighlightToAsk"
            :class="popoverVisible ? 'pointer-events-auto visible opacity-100' : 'pointer-events-none invisible opacity-0'"
            :style="{ top: popoverTop + 'px', left: popoverLeft + 'px' }"
        >
            <button
                class="lqd-highlight-ask-btn flex select-none items-center gap-1.5 whitespace-nowrap rounded-xl border-none bg-surface-background px-3 py-2.5 text-2xs font-normal shadow-[0_22px_55px_hsl(0_0%_0%/15%)] transition hover:scale-105 hover:shadow-[0_25px_60px_hsl(0_0%_0%/15%)] active:scale-95 active:shadow-[0_15px_40px_hsl(0_0%_0%/20%)]"
                type="button"
                @click.prevent="insertHighlightChip"
            >
                <x-tabler-arrow-forward-up class="size-4" />
                {{ __('Ask a Follow up') }}
            </button>
        </div>
    </template>

    @push('script')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('lqdHighlightToAsk', () => ({
                    popoverVisible: false,
                    popoverTop: -9999,
                    popoverLeft: -9999,
                    activeSelection: null,

                    _selectionChangeTimer: null,
                    _activeRange: null,
                    _mouseIsDown: false,

                    _scrollRafPending: false,

                    init() {
                        document.addEventListener('mousedown', (e) => {
                            this._mouseIsDown = true;
                            if (!this.$el?.contains(e.target)) this.hidePopover();
                        });
                        document.addEventListener('mouseup', (e) => {
                            this._mouseIsDown = false;
                            this.onMouseUp(e);
                        });
                        document.addEventListener('selectionchange', () => this.onSelectionChange());
                        document.addEventListener('scroll', () => {
                            if (this._scrollRafPending) return;
                            this._scrollRafPending = true;
                            requestAnimationFrame(() => {
                                this._scrollRafPending = false;
                                this.onScroll();
                            });
                        }, true);

                        // Cleanup from chat switch
                        document.addEventListener('highlight-to-ask:cleanup', () => this.cleanup());
                    },

                    getSelectionInChat() {
                        const selection = window.getSelection();
                        if (!selection || selection.isCollapsed || !selection.rangeCount) return null;

                        const text = selection.toString().trim();
                        if (!text || text.length < 3) return null;

                        const range = selection.getRangeAt(0);
                        const container = range.commonAncestorContainer;
                        const el = container.nodeType === Node.TEXT_NODE ? container.parentElement : container;

                        const bubble = el?.closest('.lqd-chat-ai-bubble');
                        if (!bubble) return null;

                        if (el?.closest('.lqd-entity-highlight')) return null;

                        return {
                            text,
                            range
                        };
                    },

                    showPopover(range) {
                        this._activeRange = range;
                        this.repositionPopover();
                        this.popoverVisible = true;
                    },

                    repositionPopover() {
                        if (!this._activeRange) return;

                        const rect = this._activeRange.getBoundingClientRect();

                        if (rect.width === 0 && rect.height === 0) {
                            this.hidePopover();
                            return;
                        }

                        const popRect = this.$el.getBoundingClientRect();
                        let top = rect.top - popRect.height - 8;
                        let left = rect.left + (rect.width / 2) - (popRect.width / 2);

                        if (top < 4) {
                            top = rect.bottom + 8;
                        }
                        left = Math.max(4, Math.min(left, window.innerWidth - popRect.width - 4));

                        this.popoverTop = top;
                        this.popoverLeft = left;
                    },

                    hidePopover() {
                        this.popoverVisible = false;
                        this.activeSelection = null;
                        this._activeRange = null;
                    },

                    insertHighlightChip() {
                        if (!this.activeSelection) return;

                        const store = Alpine.store('chatsV2');

                        if (store?.addToolSelection) {
                            store.addToolSelection('follow-up-' + this.activeSelection.text);
                        }

                        window.getSelection()?.removeAllRanges();
                        document.querySelector('#prompt')?.focus();

                        this.hidePopover();
                    },

                    // --- Event handlers ---
                    onMouseUp(e) {
                        if (this.$el?.contains(e.target)) return;

                        setTimeout(() => {
                            const sel = this.getSelectionInChat();

                            if (sel) {
                                this.activeSelection = sel;
                                this.showPopover(sel.range);
                            } else {
                                this.hidePopover();
                            }
                        }, 10);
                    },

                    onScroll() {
                        if (this.popoverVisible) this.repositionPopover();
                    },

                    onSelectionChange() {
                        // Skip while mouse is actively dragging — mouseup will handle it
                        if (this._mouseIsDown) return;

                        // Mobile: debounced selection detection for long-press
                        clearTimeout(this._selectionChangeTimer);
                        this._selectionChangeTimer = setTimeout(() => {
                            const sel = this.getSelectionInChat();
                            if (sel) {
                                this.activeSelection = sel;
                                this.showPopover(sel.range);
                            }
                        }, 300);
                    },

                    cleanup() {
                        this.hidePopover();

                        const store = Alpine.store('chatsV2');

                        if (store?.selectedTools) {
                            const followUps = store.selectedTools.filter(t => t.startsWith('follow-up-'));
                            followUps.forEach(t => store.removeToolSelection(t));
                        }

                        window.getSelection()?.removeAllRanges();
                    },
                }));
            });
        </script>
    @endpush
@endif
