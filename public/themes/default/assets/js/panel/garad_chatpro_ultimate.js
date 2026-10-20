(() => {
    'use strict';

    const VERSION = '2026.08.19-ultimate.1';
    const ROOT_CLASS = 'garad-ultimate-ready';
    const TOOL_LABELS = {
        image: ['Image', 'Create an image'],
        video: ['Video', 'Create a video'],
        pdf: ['PDF', 'Create a PDF'],
        word: ['Word', 'Create a Word document'],
        excel: ['Excel', 'Create a spreadsheet'],
        slides: ['Slides', 'Create a presentation'],
    };

    const state = {
        menu: null,
        trigger: null,
        chip: null,
        chipLabel: null,
        chipClear: null,
        search: null,
        currentMode: null,
        initializedForm: null,
    };

    const svg = (name) => {
        const icons = {
            plus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
            upload: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>',
            image: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m5 18 5-5 3 3 2-2 4 4"/></svg>',
            video: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="13" height="14" rx="3"/><path d="m16 10 5-3v10l-5-3z"/></svg>',
            pdf: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M9 15h6M9 12h4"/></svg>',
            word: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="m8 9 2 7 2-5 2 5 2-7"/></svg>',
            excel: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 10h16M10 4v16M6.5 13.5l2 3m0-3-2 3"/></svg>',
            slides: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4M7 8h10M7 12h6"/></svg>',
            web: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>',
            research: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m14.5 14.5 5 5M10 7v6M7 10h6"/></svg>',
            file: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/></svg>',
            agents: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7a4 4 0 1 1 8 0v2H8z"/><rect x="5" y="9" width="14" height="10" rx="3"/><path d="M9 13h.01M15 13h.01M9 17h6"/></svg>',
            canvas: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5z"/><path d="m8 15 7-7 2 2-7 7-3 1z"/></svg>',
            skills: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5h6M9 3h6v4H9z"/><rect x="5" y="5" width="14" height="16" rx="2"/><path d="M8 11h8M8 15h6"/></svg>',
            prompts: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16M8 7h8M8 11h6"/></svg>',
            connectors: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12h8M7 8v3a1 1 0 0 0 1 1M17 8v3a1 1 0 0 1-1 1M8 12v4a4 4 0 0 0 8 0v-4"/><path d="M7 5v3M17 5v3"/></svg>',
            brand: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3M22 12h-3M12 22v-3M2 12h3"/></svg>',
            memory: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4a3 3 0 0 1 6 0 3 3 0 0 1 3 3 3 3 0 0 1 1 5 3 3 0 0 1-1 5 3 3 0 0 1-3 3 3 3 0 0 1-6 0 3 3 0 0 1-3-3 3 3 0 0 1-1-5 3 3 0 0 1 1-5 3 3 0 0 1 3-3z"/><path d="M12 5v14M8 9h4M12 14h4"/></svg>',
            temp: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 7v5l3 2M17 3h4v4"/></svg>',
            voice: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M9 21h6"/></svg>',
            close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>',
            search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg>',
        };
        return icons[name] || icons.plus;
    };

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    function exactButtonText(button) {
        return (button.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function markLegacyControls(form) {
        const legacyWords = new Set(['upload', 'upload files', 'image', 'video', 'pdf', 'word', 'excel', 'slides']);
        $$('button, a', form).forEach((el) => {
            if (el.closest('.garad-u-menu') || el.closest('.garad-u-plus')) return;
            const text = exactButtonText(el);
            if (legacyWords.has(text) && !el.closest('#chat-options')) {
                el.classList.add('garad-u-legacy-pill');
            }
        });

        $$('.garad-capability-strip, .garad-chat-capabilities, .garad-quick-tools', form)
            .forEach((el) => el.classList.add('garad-u-legacy-strip'));
        $$('.garad-create-tool-picker', form).forEach((el) => el.classList.add('garad-u-native-create-picker'));
    }

    function available(selector) {
        return !!$(selector);
    }

    function clickElement(selector) {
        const el = $(selector);
        if (!el) return false;
        el.click();
        return true;
    }

    function focusPrompt() {
        const prompt = $('#prompt');
        if (!prompt) return;
        prompt.focus();
        try {
            const len = prompt.value.length;
            prompt.setSelectionRange(len, len);
        } catch (_) {}
    }

    function setPrompt(value, mode = 'replace') {
        const prompt = $('#prompt');
        if (!prompt) return false;
        const existing = String(prompt.value || '').trim();
        const next = mode === 'append' && existing ? `${existing}\n${value}` : value;
        prompt.value = next;
        prompt.dispatchEvent(new Event('input', { bubbles: true }));
        prompt.dispatchEvent(new Event('change', { bubbles: true }));
        focusPrompt();
        return true;
    }

    function setMode(label, icon, type = null) {
        state.currentMode = { label, icon, type };
        if (!state.chip || !state.chipLabel) return;
        state.chipLabel.textContent = label;
        const iconHost = state.chip.querySelector('.garad-u-chip-icon');
        if (iconHost) iconHost.innerHTML = svg(icon);
        state.chip.hidden = false;
    }

    function clearMode() {
        if (state.currentMode?.type && typeof window.garadClearSelectedTool === 'function') {
            try { window.garadClearSelectedTool(); } catch (_) {}
        } else {
            const input = $('#garad_selected_tool');
            if (input) input.value = '';
        }
        state.currentMode = null;
        if (state.chip) state.chip.hidden = true;
    }

    function selectCreateTool(tool) {
        const meta = TOOL_LABELS[tool];
        if (!meta) return false;
        let done = false;
        if (typeof window.garadSetSelectedTool === 'function') {
            try {
                window.garadSetSelectedTool(tool);
                done = true;
            } catch (_) {}
        }
        if (!done) {
            const native = $(`[data-garad-tool="${tool}"]`);
            if (native) {
                native.click();
                done = true;
            }
        }
        if (!done) {
            const input = $('#garad_selected_tool');
            if (input) {
                input.value = tool;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                done = true;
            }
        }
        if (done) {
            setMode(meta[0], tool, tool);
            focusPrompt();
        }
        return done;
    }

    function openMemory() {
        const modal = $('#ai-memory-modal');
        if (!modal) return false;
        const trigger = $('.lqd-chat-share-modal-trigger', modal) || $('[role="button"]', modal) || $('button', modal);
        if (trigger) {
            trigger.click();
            return true;
        }
        return false;
    }

    function action(item) {
        closeMenu();
        switch (item.action) {
            case 'upload':
                return clickElement('#selectImageInput');
            case 'tool':
                return selectCreateTool(item.tool);
            case 'web': {
                const ok = clickElement('#realtime');
                if (ok) setMode('Web Search', 'web', null);
                return ok;
            }
            case 'research': {
                const ok = clickElement('#deep_research_button');
                if (ok) setMode('Deep Research', 'research', null);
                return ok;
            }
            case 'connectors':
                return clickElement('.lqd-generator-connectors-trigger');
            case 'canvas': {
                const ok = clickElement('#create_canvas_button');
                if (ok) setMode('Canvas', 'canvas', null);
                return ok;
            }
            case 'skills':
                return clickElement('.lqd-generator-skills-trigger');
            case 'prompts':
                return clickElement('.lqd-chat-templates-trigger');
            case 'brand':
                return clickElement('.lqd-chat-brand-voice-trigger');
            case 'memory':
                return openMemory();
            case 'temp':
                return clickElement('#temp_chat_button');
            case 'voice':
                return clickElement('.lqd-realtime-chat-button');
            case 'agents':
                return setPrompt('Show my Garad AI agents and help me choose the right one to run for this task: ', 'replace');
            default:
                return false;
        }
    }

    function itemAvailable(item) {
        if (item.action === 'upload') return available('#selectImageInput');
        if (item.action === 'tool') return available('#garad_selected_tool') || typeof window.garadSetSelectedTool === 'function' || available(`[data-garad-tool="${item.tool}"]`);
        if (item.action === 'web') return available('#realtime');
        if (item.action === 'research') return available('#deep_research_button');
        if (item.action === 'connectors') return available('.lqd-generator-connectors-trigger');
        if (item.action === 'canvas') return available('#create_canvas_button');
        if (item.action === 'skills') return available('.lqd-generator-skills-trigger');
        if (item.action === 'prompts') return available('.lqd-chat-templates-trigger');
        if (item.action === 'brand') return available('.lqd-chat-brand-voice-trigger');
        if (item.action === 'memory') return available('#ai-memory-modal');
        if (item.action === 'temp') return available('#temp_chat_button');
        if (item.action === 'voice') return available('.lqd-realtime-chat-button');
        if (item.action === 'agents') return true;
        return true;
    }

    const groups = [
        {
            title: 'Create',
            items: [
                { label: 'Image', hint: 'Generate or edit visuals', icon: 'image', action: 'tool', tool: 'image', keys: 'image poster logo sawir' },
                { label: 'Video', hint: 'Generate AI video', icon: 'video', action: 'tool', tool: 'video', keys: 'video clip muuqaal' },
                { label: 'PDF', hint: 'Branded downloadable PDF', icon: 'pdf', action: 'tool', tool: 'pdf', keys: 'pdf document report' },
                { label: 'Word', hint: 'Editable DOCX document', icon: 'word', action: 'tool', tool: 'word', keys: 'word docx document' },
                { label: 'Excel', hint: 'Spreadsheet / XLSX', icon: 'excel', action: 'tool', tool: 'excel', keys: 'excel xlsx spreadsheet data' },
                { label: 'Slides', hint: 'Presentation / PPTX', icon: 'slides', action: 'tool', tool: 'slides', keys: 'slides presentation pptx deck' },
            ],
        },
        {
            title: 'Research & files',
            items: [
                { label: 'Upload files', hint: 'PDF, Office, image, audio, video', icon: 'upload', action: 'upload', keys: 'upload attach file document' },
                { label: 'Web Search', hint: 'Search the live web', icon: 'web', action: 'web', keys: 'web search internet' },
                { label: 'Deep Research', hint: 'Multi-step cited research', icon: 'research', action: 'research', keys: 'deep research sources citations' },
            ],
        },
        {
            title: 'Work',
            items: [
                { label: 'Agents', hint: 'Run your Garad AI agents', icon: 'agents', action: 'agents', keys: 'agent workflow automation' },
                { label: 'Canvas', hint: 'Draft and refine long work', icon: 'canvas', action: 'canvas', keys: 'canvas document edit' },
                { label: 'Skills', hint: 'Use reusable instructions', icon: 'skills', action: 'skills', keys: 'skills prompt instruction' },
                { label: 'Prompt Library', hint: 'Saved prompts and templates', icon: 'prompts', action: 'prompts', keys: 'prompt library templates' },
            ],
        },
        {
            title: 'Personalize & connect',
            items: [
                { label: 'Brand Voice', hint: 'Apply your company identity', icon: 'brand', action: 'brand', keys: 'brand voice logo colors company' },
                { label: 'Memory', hint: 'Personal instructions and context', icon: 'memory', action: 'memory', keys: 'memory instructions context' },
                { label: 'Connectors', hint: 'Gmail, Drive, Calendar, Outlook, Notion', icon: 'connectors', action: 'connectors', keys: 'connect gmail drive calendar outlook notion' },
                { label: 'Temporary Chat', hint: 'Start a temporary conversation', icon: 'temp', action: 'temp', keys: 'temporary private temp chat' },
                { label: 'Voice', hint: 'Start real-time voice', icon: 'voice', action: 'voice', keys: 'voice realtime microphone talk' },
            ],
        },
    ];

    function buildMenu() {
        if ($('#garad-u-menu')) {
            state.menu = $('#garad-u-menu');
            state.search = $('#garad-u-search');
            return;
        }

        const menu = document.createElement('div');
        menu.id = 'garad-u-menu';
        menu.className = 'garad-u-menu';
        menu.hidden = true;
        menu.setAttribute('role', 'dialog');
        menu.setAttribute('aria-label', 'Garad AI tools');

        const header = document.createElement('div');
        header.className = 'garad-u-menu-head';
        header.innerHTML = `<span class="garad-u-search-icon">${svg('search')}</span><input id="garad-u-search" type="search" autocomplete="off" placeholder="Search tools" aria-label="Search Garad AI tools"><kbd>⌘K</kbd>`;
        menu.appendChild(header);

        const body = document.createElement('div');
        body.className = 'garad-u-menu-body';
        groups.forEach((group) => {
            const section = document.createElement('section');
            section.className = 'garad-u-group';
            const title = document.createElement('div');
            title.className = 'garad-u-group-title';
            title.textContent = group.title;
            section.appendChild(title);
            const grid = document.createElement('div');
            grid.className = 'garad-u-grid';

            group.items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'garad-u-item';
                button.dataset.search = `${item.label} ${item.hint} ${item.keys || ''}`.toLowerCase();
                button.dataset.available = itemAvailable(item) ? '1' : '0';
                button.innerHTML = `<span class="garad-u-item-icon">${svg(item.icon)}</span><span class="garad-u-item-copy"><strong>${item.label}</strong><small>${item.hint}</small></span>`;
                if (!itemAvailable(item)) {
                    button.disabled = true;
                    button.title = 'This feature is not enabled for this account or installation.';
                }
                button.addEventListener('click', () => action(item));
                grid.appendChild(button);
            });
            section.appendChild(grid);
            body.appendChild(section);
        });
        menu.appendChild(body);
        document.body.appendChild(menu);

        state.menu = menu;
        state.search = $('#garad-u-search');
        state.search?.addEventListener('input', filterMenu);
        state.search?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                const first = $('.garad-u-item:not([hidden]):not(:disabled)', menu);
                if (first) {
                    event.preventDefault();
                    first.click();
                }
            }
        });
    }

    function refreshAvailability() {
        if (!state.menu) return;
        const allItems = groups.flatMap((group) => group.items);
        $$('.garad-u-item', state.menu).forEach((button, index) => {
            const item = allItems[index];
            const ok = item ? itemAvailable(item) : false;
            button.disabled = !ok;
            button.dataset.available = ok ? '1' : '0';
            button.title = ok ? '' : 'This feature is not enabled for this account or installation.';
        });
    }

    function filterMenu() {
        if (!state.menu) return;
        const q = String(state.search?.value || '').trim().toLowerCase();
        $$('.garad-u-group', state.menu).forEach((section) => {
            let visible = 0;
            $$('.garad-u-item', section).forEach((button) => {
                const match = !q || button.dataset.search.includes(q);
                button.hidden = !match;
                if (match) visible += 1;
            });
            section.hidden = visible === 0;
        });
    }

    function positionMenu() {
        if (!state.menu || !state.trigger || state.menu.hidden) return;
        const r = state.trigger.getBoundingClientRect();
        const menuWidth = Math.min(520, window.innerWidth - 24);
        const gap = 10;
        state.menu.style.width = `${menuWidth}px`;

        const desiredLeft = Math.max(12, Math.min(r.left, window.innerWidth - menuWidth - 12));
        state.menu.style.left = `${desiredLeft}px`;

        const measured = state.menu.getBoundingClientRect();
        const height = measured.height || Math.min(610, window.innerHeight - 40);
        const spaceAbove = r.top - 16;
        if (spaceAbove > Math.min(height, 520)) {
            state.menu.style.top = 'auto';
            state.menu.style.bottom = `${Math.max(12, window.innerHeight - r.top + gap)}px`;
        } else {
            state.menu.style.bottom = 'auto';
            state.menu.style.top = `${Math.min(window.innerHeight - height - 12, r.bottom + gap)}px`;
        }
    }

    function openMenu({ focusSearch = false } = {}) {
        buildMenu();
        refreshAvailability();
        if (!state.menu) return;
        state.menu.hidden = false;
        document.body.classList.add('garad-u-menu-open');
        state.trigger?.setAttribute('aria-expanded', 'true');
        filterMenu();
        requestAnimationFrame(() => {
            positionMenu();
            if (focusSearch) {
                state.search?.focus();
                state.search?.select();
            }
        });
    }

    function closeMenu() {
        if (!state.menu) return;
        state.menu.hidden = true;
        document.body.classList.remove('garad-u-menu-open');
        state.trigger?.setAttribute('aria-expanded', 'false');
        if (state.search) state.search.value = '';
        filterMenu();
    }

    function toggleMenu() {
        if (!state.menu || state.menu.hidden) openMenu(); else closeMenu();
    }

    function buildComposerUI(form) {
        const composer = $('.garad-chat-composer', form) || $('.lqd-chat-form-inputs-container', form);
        const prompt = $('#prompt', form);
        if (!composer || !prompt) return false;
        if ($('#garad-u-plus', form)) {
            state.trigger = $('#garad-u-plus', form);
            state.chip = $('#garad-u-chip', form);
            state.chipLabel = $('.garad-u-chip-label', state.chip || form);
            return true;
        }

        markLegacyControls(form);

        const promptRow = prompt.closest('.relative') || prompt.parentElement;
        if (!promptRow) return false;

        const plus = document.createElement('button');
        plus.id = 'garad-u-plus';
        plus.className = 'garad-u-plus';
        plus.type = 'button';
        plus.setAttribute('aria-haspopup', 'dialog');
        plus.setAttribute('aria-expanded', 'false');
        plus.setAttribute('aria-label', 'Tools and attachments');
        plus.title = 'Tools and attachments';
        plus.innerHTML = svg('plus');
        plus.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleMenu();
        });
        promptRow.appendChild(plus);

        const chip = document.createElement('div');
        chip.id = 'garad-u-chip';
        chip.className = 'garad-u-chip';
        chip.hidden = true;
        chip.innerHTML = `<span class="garad-u-chip-icon">${svg('plus')}</span><span class="garad-u-chip-label"></span><button class="garad-u-chip-clear" type="button" aria-label="Clear mode">${svg('close')}</button>`;
        composer.insertBefore(chip, promptRow);
        $('.garad-u-chip-clear', chip)?.addEventListener('click', clearMode);

        state.trigger = plus;
        state.chip = chip;
        state.chipLabel = $('.garad-u-chip-label', chip);
        state.chipClear = $('.garad-u-chip-clear', chip);
        state.initializedForm = form;

        // Keep old hidden/native controls functional but remove their visual clutter.
        requestAnimationFrame(() => markLegacyControls(form));
        return true;
    }

    function installGlobalEvents() {
        if (window.__garadUltimateEventsInstalled) return;
        window.__garadUltimateEventsInstalled = true;

        document.addEventListener('click', (event) => {
            if (!state.menu || state.menu.hidden) return;
            if (state.menu.contains(event.target) || state.trigger?.contains(event.target)) return;
            closeMenu();
        }, true);

        document.addEventListener('keydown', (event) => {
            const cmdK = (event.metaKey || event.ctrlKey) && String(event.key).toLowerCase() === 'k';
            if (cmdK) {
                const form = $('#chat_form');
                if (!form) return;
                event.preventDefault();
                openMenu({ focusSearch: true });
                return;
            }
            if (event.key === 'Escape' && state.menu && !state.menu.hidden) {
                event.preventDefault();
                closeMenu();
            }
        });

        window.addEventListener('resize', positionMenu, { passive: true });
        window.addEventListener('scroll', positionMenu, { passive: true });
    }

    function syncNativeTool() {
        const nativeInput = $('#garad_selected_tool');
        const tool = String(nativeInput?.value || '').trim().toLowerCase();
        if (tool && TOOL_LABELS[tool] && (!state.currentMode || state.currentMode.type !== tool)) {
            setMode(TOOL_LABELS[tool][0], tool, tool);
        }
        if (!tool && state.currentMode?.type) {
            state.currentMode = null;
            if (state.chip) state.chip.hidden = true;
        }
    }

    function ensure() {
        const form = $('#chat_form');
        if (!form) return;
        if (buildComposerUI(form)) {
            document.documentElement.classList.add(ROOT_CLASS);
            document.body?.classList.add(ROOT_CLASS);
            buildMenu();
            refreshAvailability();
            syncNativeTool();
        }
    }

    function init() {
        installGlobalEvents();
        ensure();

        const observer = new MutationObserver(() => {
            clearTimeout(window.__garadUltimateMutationTimer);
            window.__garadUltimateMutationTimer = setTimeout(ensure, 80);
        });
        observer.observe(document.body, { childList: true, subtree: true });

        setInterval(() => {
            ensure();
            syncNativeTool();
        }, 900);

        window.garadUltimate = Object.freeze({
            version: VERSION,
            openTools: () => openMenu({ focusSearch: true }),
            closeTools: closeMenu,
            selectTool: selectCreateTool,
            clearMode,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
