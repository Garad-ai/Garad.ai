@php
    $drClickAction = '';
    if ($app_is_demo) {
        $drClickAction = 'return toastr.info(\'' . e(__('This feature is disabled in Demo version.')) . '\')';
    } elseif (!auth()->check()) {
        $drClickAction = 'return toastr.warning(\'' . e(__('Please login to use Deep Research.')) . '\')';
    } elseif (!auth()->user()->isAdmin() && !auth()->user()->relationPlan?->checkOpenAiItem('deep_research')) {
        $drClickAction = 'return toastr.warning(\'' . e(__('Your current plan does not include Deep Research. Please upgrade your plan.')) . '\')';
    }
@endphp

<button
    class="lqd-chat-deep-research-btn group"
    id="deep_research_button"
    x-data="deepResearchButtonStatus"
    :class="{ 'active': hasToolSelected('deep_research') }"
    type="button"
    title="{{ __('Deep Research') }}"
    onclick="{!! $drClickAction !!}"
    @if (!filled($drClickAction)) @click="toggleToolSelection('deep_research')" @endif
>
    <x-tabler-telescope class="size-4.5 shrink-0 opacity-75" />
    <span class="truncate">
        {{ __('Deep Research') }}
    </span>

    <x-tabler-check class="invisible ms-auto size-4.5 shrink-0 group-[&.active]:visible" />
</button>

@push('css')
    <style>
        @keyframes deep-research-shimmer {
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        .deep-research-shimmer-text {
            background: linear-gradient(90deg,
                    hsl(var(--heading-foreground) / 0.4) 0%,
                    hsl(var(--heading-foreground) / 0.8) 40%,
                    hsl(var(--heading-foreground) / 1) 50%,
                    hsl(var(--heading-foreground) / 0.8) 60%,
                    hsl(var(--heading-foreground) / 0.4) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: deep-research-shimmer 2s linear infinite;
        }

        .deep-research-sources-section {
            width: 100%;
            margin-top: 0;
            padding-top: 0;
            border-top: none;
        }

        .deep-research-sources-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: hsl(var(--heading-foreground) / 0.5);
            list-style: none;
            user-select: none;
            padding: 4px 0;
            transition: color 0.15s;
        }

        .deep-research-sources-toggle:hover {
            color: hsl(var(--heading-foreground) / 0.75);
        }

        .deep-research-sources-toggle::-webkit-details-marker {
            display: none;
        }

        .deep-research-sources-toggle .dr-chevron {
            width: 14px;
            height: 14px;
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        details[open]>.deep-research-sources-toggle .dr-chevron {
            transform: rotate(90deg);
        }

        .deep-research-sources-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding-bottom: 4px;
        }

        .deep-research-source-chip {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            background: hsl(var(--heading-foreground) / 0.04);
            border: 1px solid hsl(var(--heading-foreground) / 0.08);
            font-size: 12px;
            color: hsl(var(--heading-foreground) / 0.65);
            text-decoration: none;
            transition: all 0.15s;
            max-width: 280px;
            cursor: pointer;
        }

        .deep-research-source-chip:hover {
            background: hsl(var(--primary) / 0.08);
            border-color: hsl(var(--primary) / 0.2);
            color: hsl(var(--primary));
        }

        .deep-research-source-chip img {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .deep-research-source-chip-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .deep-research-source-preview {
            display: none;
            position: absolute;
            bottom: calc(100% + 8px);
            left: 0;
            z-index: 50;
            width: 300px;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid hsl(var(--heading-foreground) / 0.08);
            background: hsl(var(--background));
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            pointer-events: none;
        }

        .deep-research-source-chip:hover .deep-research-source-preview {
            display: block;
        }

        .deep-research-source-preview-domain {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: hsl(var(--heading-foreground) / 0.45);
            margin-bottom: 6px;
        }

        .deep-research-source-preview-domain img {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .deep-research-source-preview-title {
            font-size: 13px;
            font-weight: 600;
            color: hsl(var(--heading-foreground));
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Inline citation globe icons — CSS-only, keeps <a> tags intact for Canvas */
        .dr-cite-link {
            font-size: 0 !important;
            color: transparent !important;
            text-decoration: none !important;
            display: inline !important;
            position: relative;
            vertical-align: baseline;
        }

        .dr-cite-link::after {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            vertical-align: text-bottom;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20'/%3E%3Cpath d='M2 12h20'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.4;
            transition: opacity 0.15s;
            cursor: pointer;
            margin: 0 1px;
        }

        .dr-cite-link:hover::after {
            opacity: 0.8;
        }

        /* Canvas override — keep text visible so links are selectable & deletable */
        #tiptap-editor-element .tiptap.ProseMirror .dr-cite-link {
            font-size: 11px !important;
            color: hsl(var(--heading-foreground) / 0.3) !important;
            background: hsl(var(--heading-foreground) / 0.05);
            border-radius: 3px;
            padding: 1px 2px;
        }

        #tiptap-editor-element .tiptap.ProseMirror .dr-cite-link::after {
            margin-left: 2px;
            vertical-align: middle;
        }

        .dr-cite-paren {
            display: none;
        }

        /* Floating citation tooltip */
        #dr-cite-tooltip {
            display: none;
            position: fixed;
            z-index: 9999;
            min-width: 200px;
            max-width: 300px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid hsl(var(--heading-foreground) / 0.08);
            background: hsl(var(--background));
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            pointer-events: none;
            text-align: left;
        }

        #dr-cite-tooltip.visible {
            display: block;
        }

        #dr-cite-tooltip .tt-domain {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: hsl(var(--heading-foreground) / 0.45);
            margin-bottom: 4px;
        }

        #dr-cite-tooltip .tt-domain img {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        #dr-cite-tooltip .tt-title {
            font-size: 13px;
            font-weight: 600;
            color: hsl(var(--heading-foreground));
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Activity / Thinking Steps */
        .deep-research-activity {
            width: 100%;
            margin-top: 10px;
        }

        .deep-research-activity-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .deep-research-activity-header .deep-research-cancel-btn {
            margin-left: auto;
            font-size: 12px;
            font-weight: 400;
            color: hsl(var(--heading-foreground) / 0.4);
            cursor: pointer;
            background: none;
            border: none;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.15s;
        }

        .deep-research-activity-header .deep-research-cancel-btn:hover {
            color: hsl(0 70% 50%);
            background: hsl(0 70% 50% / 0.08);
        }

        .deep-research-activity-steps {
            display: flex;
            flex-direction: column;
            gap: 0;
            padding-left: 12px;
            border-left: 2px solid hsl(var(--primary) / 0.2);
            max-height: 260px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .deep-research-activity-steps::-webkit-scrollbar {
            width: 3px;
        }

        .deep-research-activity-steps::-webkit-scrollbar-thumb {
            background: hsl(var(--heading-foreground) / 0.1);
            border-radius: 3px;
        }

        .deep-research-activity-step {
            padding: 4px 0;
            font-size: 13px;
            line-height: 1.5;
            color: hsl(var(--heading-foreground) / 0.5);
            animation: dr-step-fade-in 0.3s ease;
        }

        .deep-research-activity-step:last-child {
            color: hsl(var(--heading-foreground) / 0.85);
        }

        .deep-research-activity-step-search {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: hsl(var(--primary) / 0.7);
        }

        .deep-research-activity-step-search svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        @keyframes dr-step-fade-in {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Final report collapsible thinking */
        .deep-research-thought-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: hsl(var(--heading-foreground) / 0.45);
            list-style: none;
            user-select: none;
            padding: 6px 0;
            margin-bottom: 4px;
            transition: color 0.15s;
        }

        .deep-research-thought-toggle:hover {
            color: hsl(var(--heading-foreground) / 0.7);
        }

        .deep-research-thought-toggle::-webkit-details-marker {
            display: none;
        }

        .deep-research-thought-toggle .dr-chevron {
            width: 14px;
            height: 14px;
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        details[open]>.deep-research-thought-toggle .dr-chevron {
            transform: rotate(90deg);
        }
    </style>
@endpush

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('deepResearchButtonStatus', () => ({
                status: false,
                _pollInterval: null,
                _currentSessionId: null,
                _currentMessageId: null,
                _currentBubble: null,
                _isSubmitting: false,

                addEventListener() {
                    const btn = document.getElementById('deep_research_button');
                    if (btn) {
                        btn.addEventListener('click', () => {
                            @if (auth()->user()?->isAdmin() || auth()->user()?->relationPlan?->checkOpenAiItem('deep_research'))
                                this.toggle();
                            @endif
                        });
                    }
                },

                toggle() {
                    this.status = !this.status;

                    if (this.status && document.getElementById('chatbot_council_mode')?.value === '1') {
                        document.getElementById('chatbot_council_mode').value = '0';
                        localStorage.setItem('chatCouncilMode', '0');
                        toastr.info('{{ __('Council Mode has been disabled. Deep Research uses its own AI engine.') }}');
                        window.dispatchEvent(new CustomEvent('council-mode-changed', {
                            detail: {
                                enabled: false
                            }
                        }));
                    }
                },

                init() {
                    Alpine.store('deepResearchStatus', this);
                    this.addEventListener();
                    this._interceptSend();

                    // Restore thinking sections for old deep research messages on page load
                    setTimeout(() => this._restoreThinkingSections(), 1500);

                    // Watch Canvas tiptap for citation styling
                    this._initCanvasCiteObserver();

                    this.$watch('selectedTools', value => {
                        if (!value.includes('deep_research')) {
                            this.status = false;
                        }
                    });
                },

                _interceptSend() {
                    const sendBtn = document.getElementById('send_message_button');
                    if (!sendBtn) {
                        console.warn('[DeepResearch] send_message_button not found, retrying...');
                        setTimeout(() => this._interceptSend(), 500);
                        return;
                    }

                    sendBtn.addEventListener('click', (e) => {
                        if (!this.status) return;
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        this._startDeepResearch();
                    }, true);

                    // Intercept Enter key on prompt — both keydown and keypress
                    // (main chat uses keypress, so we must block both)
                    const promptEl = document.getElementById('prompt');
                    if (promptEl) {
                        const enterHandler = (e) => {
                            if (!this.status) return;
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                // Only start once (keydown fires first)
                                if (e.type === 'keydown') {
                                    this._startDeepResearch();
                                }
                            }
                        };
                        promptEl.addEventListener('keydown', enterHandler, true);
                        promptEl.addEventListener('keypress', enterHandler, true);
                    }
                },

                async _startDeepResearch() {
                    if (this._isSubmitting) return;
                    this._isSubmitting = true;

                    const promptEl = document.getElementById('prompt');
                    const query = promptEl?.value?.trim();
                    if (!query) {
                        this._isSubmitting = false;
                        return;
                    }

                    const chatId = document.getElementById('chat_id')?.value;
                    if (!chatId) {
                        toastr.warning('{{ __('Please start a chat first.') }}');
                        this._isSubmitting = false;
                        return;
                    }

                    console.log('[DeepResearch] Starting research:', {
                        query,
                        chatId
                    });

                    // Add user message bubble
                    this._addUserBubble(query);
                    promptEl.value = '';
                    promptEl.dispatchEvent(new Event('input'));

                    // Add thinking bubble
                    const thinkingBubble = this._createThinkingBubble();
                    console.log('[DeepResearch] Thinking bubble created:', thinkingBubble?.tagName, thinkingBubble?.parentNode?.className);

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                        document.querySelector('input[name="_token"]')?.value;

                    try {
                        const response = await fetch('/dashboard/user/deep-research/start', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                prompt: query,
                                chat_id: chatId
                            }),
                        });

                        console.log('[DeepResearch] Response status:', response.status);

                        let data;
                        try {
                            data = await response.json();
                        } catch (parseErr) {
                            console.error('[DeepResearch] Failed to parse response:', parseErr);
                            thinkingBubble.remove();
                            toastr.error('{{ __('Server returned an invalid response.') }}');
                            this._isSubmitting = false;
                            return;
                        }

                        console.log('[DeepResearch] Response data:', data);

                        if (!response.ok) {
                            thinkingBubble.remove();
                            toastr.error(data.error || '{{ __('Failed to start research.') }}');
                            this._isSubmitting = false;
                            return;
                        }

                        this._currentSessionId = data.session_id;
                        this._currentMessageId = data.message_id;
                        this._currentBubble = thinkingBubble;
                        const autoCanvas = data.auto_canvas;

                        // Set message ID on the bubble for canvas integration
                        thinkingBubble.setAttribute('data-message-id', data.message_id);

                        // Start polling
                        this._pollInterval = setInterval(async () => {
                            await this._pollResearch(thinkingBubble, data.session_id, autoCanvas);
                        }, 5000);
                    } catch (err) {
                        console.error('[DeepResearch] Fetch error:', err);
                        thinkingBubble.remove();
                        toastr.error(err.message || '{{ __('Failed to start research.') }}');
                        this._isSubmitting = false;
                    }
                },

                async _pollResearch(thinkingBubble, sessionId, autoCanvas) {
                    try {
                        const response = await fetch(`/dashboard/user/deep-research/poll/${sessionId}`, {
                            headers: {
                                'Accept': 'application/json'
                            },
                        });
                        const data = await response.json();

                        console.log('[DeepResearch] Poll result:', data.status);

                        if (data.status === 'researching') {
                            this._updateThinkingSteps(thinkingBubble, data.thinking_steps || []);
                        } else if (data.status === 'completed') {
                            clearInterval(this._pollInterval);
                            this._pollInterval = null;
                            this._isSubmitting = false;
                            this._replaceWithReport(thinkingBubble, data);
                            this._openInCanvas(thinkingBubble);
                        } else if (data.status === 'failed' || data.status === 'cancelled') {
                            clearInterval(this._pollInterval);
                            this._pollInterval = null;
                            this._isSubmitting = false;
                            thinkingBubble.remove();
                            toastr.error(data.error || '{{ __('Research failed or was cancelled.') }}');
                        }
                    } catch (err) {
                        console.warn('[DeepResearch] Poll error (will retry):', err.message);
                    }
                },

                async _cancelResearch() {
                    if (!this._currentSessionId) return;

                    clearInterval(this._pollInterval);
                    this._pollInterval = null;
                    this._isSubmitting = false;

                    // Remove the thinking bubble
                    if (this._currentBubble) {
                        this._currentBubble.remove();
                        this._currentBubble = null;
                    }

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                        document.querySelector('input[name="_token"]')?.value;

                    try {
                        await fetch(`/dashboard/user/deep-research/cancel/${this._currentSessionId}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        });
                    } catch (err) {
                        // Best effort
                    }

                    toastr.info('{{ __('Research cancelled.') }}');
                },

                _addUserBubble(text) {
                    const template = document.querySelector('#chat_user_bubble');
                    const chatsContainer = document.querySelector('.chats-container');
                    if (!template || !chatsContainer) return;

                    const bubble = template.content.cloneNode(true).firstElementChild;
                    const contentEl = bubble.querySelector('.chat-content');
                    if (contentEl) {
                        contentEl.innerHTML = this._escapeHtml(text);
                    }

                    // Toggle conversation state
                    const chatsWrap = document.querySelector('.chats-wrap');
                    if (chatsWrap) {
                        chatsWrap.classList.remove('conversation-not-started');
                        chatsWrap.classList.add('conversation-started');
                    }

                    chatsContainer.append(bubble);
                    if (typeof scrollConversationArea === 'function') {
                        scrollConversationArea({
                            smooth: true
                        });
                    }
                },

                _createThinkingBubble() {
                    const template = document.querySelector('#chat_ai_bubble');
                    const chatsContainer = document.querySelector('.chats-container');

                    if (!template || !chatsContainer) {
                        console.error('[DeepResearch] Missing template or container');
                        return document.createElement('div');
                    }

                    const fragment = template.content.cloneNode(true);
                    const bubble = fragment.firstElementChild;

                    if (!bubble) {
                        console.error('[DeepResearch] Template has no element children');
                        return document.createElement('div');
                    }

                    bubble.classList.add('deep-research-active');

                    // Set content with visible activity area
                    const contentEl = bubble.querySelector('.chat-content');
                    if (contentEl) {
                        contentEl.innerHTML = `
                            <div class="deep-research-activity">
                                <div class="deep-research-activity-header">
                                    <span class="deep-research-shimmer-text">{{ __('Thinking...') }}</span>
                                    <button class="deep-research-cancel-btn" onclick="Alpine.store('deepResearchStatus')._cancelResearch()">{{ __('Cancel') }}</button>
                                </div>
                                <div class="deep-research-activity-steps"></div>
                            </div>
                        `;
                    }

                    // Hide the typing dots so only the research UI shows
                    const typingEl = bubble.querySelector('.lqd-typing');
                    if (typingEl) typingEl.style.display = 'none';

                    chatsContainer.append(bubble);

                    if (typeof scrollConversationArea === 'function') {
                        scrollConversationArea({
                            smooth: true
                        });
                    }

                    return bubble;
                },

                _updateThinkingSteps(bubble, steps) {
                    if (!bubble || !steps.length) return;
                    const container = bubble.querySelector('.deep-research-activity-steps');
                    if (!container) return;

                    const searchIcon =
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';

                    // Build steps HTML — search steps get icon, thinking steps are plain text
                    const stepsHtml = steps.map(s => {
                        const text = this._escapeHtml(s.text || '');
                        if (s.type === 'search') {
                            return `<div class="deep-research-activity-step deep-research-activity-step-search">${searchIcon} {{ __('Searched') }} "${text}"</div>`;
                        }
                        return `<div class="deep-research-activity-step">${text}</div>`;
                    }).join('');

                    container.innerHTML = stepsHtml;

                    // Auto-scroll to bottom of steps container
                    container.scrollTop = container.scrollHeight;

                    if (typeof scrollConversationArea === 'function') {
                        scrollConversationArea({
                            smooth: true
                        });
                    }
                },

                _replaceWithReport(bubble, data) {
                    if (!bubble) return;

                    // Use MagicAI's formatString if available, otherwise basic markdown
                    const reportHtml = typeof formatString === 'function' ?
                        formatString(data.report || '') :
                        this._basicMarkdown(data.report || '');

                    // Ensure message ID and title are set on the bubble for Canvas save
                    if (this._currentMessageId) {
                        bubble.setAttribute('data-message-id', this._currentMessageId);
                    }
                    bubble.setAttribute('data-title', '{{ __('Deep Research') }}');

                    // Update the AI bubble content
                    bubble.classList.remove('loading', 'animating-words');
                    bubble.classList.add('animating-words-done');

                    const contentEl = bubble.querySelector('.chat-content');
                    const contentContainer = bubble.querySelector('.chat-content-container');

                    if (contentEl && contentContainer) {
                        // Set report inside .chat-content (this is what Canvas reads)
                        contentEl.innerHTML = `<div class="prose dark:prose-invert max-w-none">${reportHtml}</div>`;
                        this._styleInlineCitations(contentEl);

                        // The template-cloned bubble wraps .chat-content in an inline-flex div.
                        // Restructure so .chat-content is a direct child of .chat-content-container.
                        if (contentEl.parentNode !== contentContainer) {
                            const actionsWrap = contentContainer.querySelector('.lqd-chat-actions-wrap');
                            while (contentContainer.firstChild) contentContainer.firstChild.remove();
                            contentContainer.appendChild(contentEl);
                            if (actionsWrap) contentContainer.appendChild(actionsWrap);
                        }

                        // Hide the typing dots (may have been removed by restructure, but just in case)
                        const typingEl = bubble.querySelector('.lqd-typing');
                        if (typingEl) typingEl.style.display = 'none';

                        // Build correct order inside .chat-content-container:
                        // 1) Open in Canvas button
                        const canvasEditBtnTemplate = document.querySelector('#canvas_edit_btn_block');
                        if (canvasEditBtnTemplate) {
                            const canvasEditBtn = canvasEditBtnTemplate.content.cloneNode(true).firstElementChild;
                            if (canvasEditBtn) {
                                contentContainer.insertAdjacentElement('afterbegin', canvasEditBtn);
                            }
                        }

                        // 2) Thinking toggle (before .chat-content, after Canvas button)
                        const thinkingHtml = this._renderThinkingSteps(data.thinking_steps || [], data.duration || 0);
                        if (thinkingHtml) {
                            const thinkingWrapper = document.createElement('div');
                            thinkingWrapper.innerHTML = thinkingHtml;
                            contentContainer.insertBefore(thinkingWrapper.firstElementChild, contentEl);
                        }

                        // 3) .chat-content (report) — already in place

                        // 4) Sources at end of container
                        const sourcesHtml = this._renderSources(data.sources || []);
                        if (sourcesHtml) {
                            const sourcesWrapper = document.createElement('div');
                            sourcesWrapper.innerHTML = sourcesHtml;
                            contentContainer.appendChild(sourcesWrapper.firstElementChild);
                        }
                    }

                    if (typeof scrollConversationArea === 'function') {
                        scrollConversationArea({
                            smooth: true
                        });
                    }
                },

                _renderThinkingSteps(steps, durationSeconds) {
                    if (!steps || !steps.length) return '';

                    const searchIcon =
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';
                    const chevron =
                        '<svg class="dr-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';

                    const stepsHtml = steps.map(s => {
                        const text = this._escapeHtml(s.text || '');
                        if (s.type === 'search') {
                            return `<div class="deep-research-activity-step deep-research-activity-step-search">${searchIcon} {{ __('Searched') }} "${text}"</div>`;
                        }
                        return `<div class="deep-research-activity-step">${text}</div>`;
                    }).join('');

                    // Format duration for the toggle label
                    let durationLabel = '';
                    if (durationSeconds) {
                        const mins = Math.floor(durationSeconds / 60);
                        const secs = durationSeconds % 60;
                        durationLabel = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
                    }

                    const toggleText = durationLabel ?
                        `{{ __('Thought for') }} ${durationLabel}` :
                        `{{ __('Thinking process') }}`;

                    return `
                        <div class="deep-research-activity mb-4">
                            <details>
                                <summary class="deep-research-thought-toggle">
                                    ${chevron}
                                    ${toggleText} · ${steps.length} {{ __('steps') }}
                                </summary>
                                <div class="deep-research-activity-steps" style="max-height:320px">
                                    ${stepsHtml}
                                </div>
                            </details>
                        </div>
                    `;
                },

                _renderSources(sources) {
                    if (!sources || !sources.length) return '';

                    // Deduplicate by base URL (strip #fragment)
                    const seen = new Map();
                    sources.forEach(s => {
                        const url = s.url || '';
                        const baseUrl = url.split('#')[0];
                        if (!baseUrl || seen.has(baseUrl)) return;
                        seen.set(baseUrl, {
                            url: baseUrl,
                            title: s.title || ''
                        });
                    });
                    const unique = Array.from(seen.values());
                    if (!unique.length) return '';

                    const chevron =
                        '<svg class="dr-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';

                    let chipsHtml = '';
                    unique.forEach((s) => {
                        const url = s.url || '#';
                        let domain = '';
                        try {
                            domain = new URL(url).hostname.replace('www.', '');
                        } catch (e) {
                            domain = url;
                        }
                        const favicon = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=32`;
                        const displayTitle = this._escapeHtml(s.title || domain);
                        const escapedUrl = this._escapeHtml(url);

                        chipsHtml += `<a class="deep-research-source-chip" href="${escapedUrl}" target="_blank" rel="noopener noreferrer">
                            <img src="${favicon}" alt="" onerror="this.style.display='none'">
                            <span class="deep-research-source-chip-text">${displayTitle}</span>
                            <div class="deep-research-source-preview">
                                <div class="deep-research-source-preview-domain">
                                    <img src="${favicon}" alt="" onerror="this.style.display='none'">
                                    <span>${this._escapeHtml(domain)}</span>
                                </div>
                                <div class="deep-research-source-preview-title">${displayTitle}</div>
                            </div>
                        </a>`;
                    });

                    return `<div class="deep-research-sources-section">
                        <details>
                            <summary class="deep-research-sources-toggle">
                                ${chevron}
                                {{ __('Sources') }} (${unique.length})
                            </summary>
                            <div class="deep-research-sources-list">
                                ${chipsHtml}
                            </div>
                        </details>
                    </div>`;
                },

                _basicMarkdown(text) {
                    if (!text) return '';
                    let html = this._escapeHtml(text);
                    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
                    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
                    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
                    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
                    html = html.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
                    html = html.replace(/\n\n/g, '</p><p>');
                    html = html.replace(/\n/g, '<br>');
                    return '<p>' + html + '</p>';
                },

                _openInCanvas(bubble) {
                    // Wait a tick for Alpine to bind @click on the newly inserted canvas button
                    setTimeout(() => {
                        const canvasBtn = bubble?.querySelector('.lqd-chat-bubble-canvas-trigger');
                        if (canvasBtn) {
                            canvasBtn.click();
                        }
                    }, 100);
                },

                _restoreThinkingSections() {
                    const chatId = document.getElementById('chat_id')?.value;
                    if (!chatId) return;

                    fetch(`/dashboard/user/deep-research/chat-sessions/${chatId}`, {
                            headers: {
                                'Accept': 'application/json'
                            },
                        })
                        .then(r => r.ok ? r.json() : [])
                        .then(sessions => {
                            sessions.forEach(session => {
                                const bubble = document.querySelector(`.lqd-chat-ai-bubble[data-message-id="${session.message_id}"]`);
                                if (!bubble) return;

                                const contentContainer = bubble.querySelector('.chat-content-container');
                                const contentEl = bubble.querySelector('.chat-content');
                                if (!contentContainer || !contentEl) return;

                                // Clean old markdown leftovers from .chat-content:
                                // 1) Old italic "*Thought for Xm Xs · N steps*" line at the top
                                // 2) Old markdown sources section "---\n**Sources:**\n1. [title](url)..."
                                this._cleanOldMarkdownFromContent(contentEl);
                                // Replace inline citation links with globe icons
                                this._styleInlineCitations(contentEl);

                                // Order inside .chat-content-container:
                                // 1) Canvas button (already rendered by server)
                                // 2) Thinking toggle (before .chat-content)
                                // 3) .chat-content (report)
                                // 4) Sources (at end of container)
                                if (session.thinking_steps?.length && !bubble.querySelector('.deep-research-activity')) {
                                    const thinkingHtml = this._renderThinkingSteps(session.thinking_steps, session.duration || 0);
                                    if (thinkingHtml) {
                                        const wrapper = document.createElement('div');
                                        wrapper.innerHTML = thinkingHtml;
                                        contentContainer.insertBefore(wrapper.firstElementChild, contentEl);
                                    }
                                }

                                if (session.sources?.length && !bubble.querySelector('.deep-research-sources-section')) {
                                    const sourcesHtml = this._renderSources(session.sources);
                                    if (sourcesHtml) {
                                        const wrapper = document.createElement('div');
                                        wrapper.innerHTML = sourcesHtml;
                                        contentContainer.appendChild(wrapper.firstElementChild);
                                    }
                                }
                            });
                        })
                        .catch(() => {});
                },

                _cleanOldMarkdownFromContent(contentEl) {
                    // Remove old italic "Thought for..." line rendered by markdown-it as <em>
                    const firstChild = contentEl.querySelector('p:first-child, em:first-child');
                    if (firstChild) {
                        const target = firstChild.tagName === 'EM' ? firstChild : firstChild.querySelector('em');
                        if (target && /^Thought for\s+\d+/.test(target.textContent)) {
                            const parent = target.closest('p') || target;
                            parent.remove();
                        }
                    }

                    // Remove old stats line like "12m 11 Sources 73 Searches"
                    contentEl.querySelectorAll('p').forEach(p => {
                        if (/^\d+[ms]\s+\d+\s+Sources\s+\d+\s+Searches$/i.test(p.textContent.trim())) {
                            p.remove();
                        }
                    });

                    // Remove embedded sources section (with or without <hr>)
                    // Pattern 1: <hr> + <strong>Sources:</strong> + list
                    const hrs = contentEl.querySelectorAll('hr');
                    hrs.forEach(hr => {
                        let next = hr.nextElementSibling;
                        if (!next) return;
                        const strong = next.querySelector('strong');
                        if (strong && /^Sources/i.test(strong.textContent)) {
                            const toRemove = [hr];
                            let el = next;
                            while (el) {
                                toRemove.push(el);
                                el = el.nextElementSibling;
                            }
                            toRemove.forEach(n => n.remove());
                        }
                    });

                    // Pattern 2: <p><strong>Sources:</strong></p> + <ol>/<ul> (no <hr>, Gemini format)
                    contentEl.querySelectorAll('p, h2, h3').forEach(el => {
                        const strong = el.querySelector('strong') || el;
                        if (!/^Sources:?$/i.test(strong.textContent.trim())) return;
                        const toRemove = [el];
                        let next = el.nextElementSibling;
                        while (next && (next.tagName === 'OL' || next.tagName === 'UL' || (next.tagName === 'P' && next.querySelector('a')))) {
                            toRemove.push(next);
                            next = next.nextElementSibling;
                        }
                        toRemove.forEach(n => n.remove());
                    });
                },

                _styleInlineCitations(contentEl) {
                    if (!contentEl) return;
                    const isCanvas = contentEl.closest('#tiptap-editor-element') !== null;

                    const links = Array.from(contentEl.querySelectorAll('a[href]'));
                    links.forEach(a => {
                        if (a.closest('.deep-research-sources-section')) return;
                        if (a.closest('.deep-research-activity')) return;
                        if (a.classList.contains('dr-cite-link')) return;

                        // Only style domain-like link text (e.g. "apnews.com", "en.wikipedia.org")
                        const text = a.textContent.trim();
                        if (!/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i.test(text)) return;

                        const url = a.href;
                        let domain = '';
                        try {
                            domain = new URL(url).hostname.replace('www.', '');
                        } catch (e) {
                            return;
                        }

                        // Add class + data attrs — keep the <a> tag intact for Canvas compatibility
                        a.classList.add('dr-cite-link');
                        a.setAttribute('data-dr-domain', domain);
                        a.setAttribute('data-dr-title', text || domain);
                        a.setAttribute('target', '_blank');
                        a.setAttribute('rel', 'noopener noreferrer');

                        // Wrap surrounding parentheses (skip inside Canvas — ProseMirror manages that DOM)
                        if (!isCanvas) {
                            const prev = a.previousSibling;
                            const next = a.nextSibling;
                            if (prev && prev.nodeType === 3 && prev.textContent.endsWith('(')) {
                                prev.textContent = prev.textContent.slice(0, -1);
                                const paren = document.createElement('span');
                                paren.className = 'dr-cite-paren';
                                paren.textContent = '(';
                                a.parentNode.insertBefore(paren, a);
                            }
                            if (next && next.nodeType === 3 && next.textContent.startsWith(')')) {
                                next.textContent = next.textContent.slice(1);
                                const paren = document.createElement('span');
                                paren.className = 'dr-cite-paren';
                                paren.textContent = ')';
                                a.parentNode.insertBefore(paren, a.nextSibling);
                            }
                        }
                    });

                    this._initCiteTooltip();
                },

                _initCiteTooltip() {
                    if (document.getElementById('dr-cite-tooltip')) return;

                    const tooltip = document.createElement('div');
                    tooltip.id = 'dr-cite-tooltip';
                    tooltip.innerHTML =
                        '<div class="tt-domain"><img src="" alt="" onerror="this.style.display=\'none\'"><span></span></div><div class="tt-title"></div>';
                    document.body.appendChild(tooltip);

                    document.addEventListener('mouseover', (e) => {
                        const link = e.target.closest('.dr-cite-link');
                        if (!link) return;

                        // Read data attrs first; fall back to extracting from href (Canvas strips data attrs)
                        let domain = link.getAttribute('data-dr-domain') || '';
                        let title = link.getAttribute('data-dr-title') || '';
                        if (!domain && link.href) {
                            try {
                                domain = new URL(link.href).hostname.replace('www.', '');
                            } catch (ex) {}
                        }
                        if (!title) title = link.textContent.trim() || domain;
                        const favicon = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=32`;

                        const img = tooltip.querySelector('.tt-domain img');
                        img.src = favicon;
                        img.style.display = '';
                        tooltip.querySelector('.tt-domain span').textContent = domain;
                        tooltip.querySelector('.tt-title').textContent = title;

                        // Position above the link
                        const rect = link.getBoundingClientRect();
                        tooltip.classList.add('visible');
                        const ttRect = tooltip.getBoundingClientRect();
                        let top = rect.top - ttRect.height - 8;
                        let left = rect.left;
                        if (top < 4) top = rect.bottom + 8;
                        if (left + ttRect.width > window.innerWidth - 8) left = window.innerWidth - ttRect.width - 8;
                        tooltip.style.top = top + 'px';
                        tooltip.style.left = left + 'px';
                    });

                    document.addEventListener('mouseout', (e) => {
                        const link = e.target.closest('.dr-cite-link');
                        if (!link) return;
                        tooltip.classList.remove('visible');
                    });
                },

                _initCanvasCiteObserver() {
                    let debounceTimer = null;
                    const self = this;
                    const applyToCanvas = () => {
                        const tiptap = document.querySelector('#tiptap-editor-element .tiptap.ProseMirror');
                        if (tiptap) {
                            self._styleInlineCitations(tiptap);
                        }
                    };

                    const observer = new MutationObserver(() => {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(applyToCanvas, 150);
                    });

                    const startObserving = () => {
                        const target = document.getElementById('tiptap-editor-element');
                        if (target) {
                            observer.observe(target, {
                                childList: true,
                                subtree: true
                            });
                        } else {
                            setTimeout(startObserving, 1000);
                        }
                    };
                    startObserving();
                },

                _escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                },
            }));
        });
    </script>
@endpush
