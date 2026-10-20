(() => {
    'use strict';

    const TOOL_META = {
        image:  { label: 'Image',  placeholder: 'Describe the image you want to create…' },
        video:  { label: 'Video',  placeholder: 'Describe the video you want to generate…' },
        pdf:    { label: 'PDF',    placeholder: 'Describe the PDF you want Garad AI to create…' },
        word:   { label: 'Word',   placeholder: 'Describe the Word document you want to create…' },
        excel:  { label: 'Excel',  placeholder: 'Describe the spreadsheet or data table you want…' },
        slides: { label: 'Slides', placeholder: 'Describe the presentation you want to create…' },
    };

    let activeTool = '';
    let observer;

    function qs(selector, root = document) {
        return root.querySelector(selector);
    }

    function qsa(selector, root = document) {
        return Array.from(root.querySelectorAll(selector));
    }

    function ensureChip(form) {
        let chip = qs('#garad-safe-tool-chip', form);
        if (chip) return chip;

        const container = qs('.lqd-chat-form-inputs-container', form);
        if (!container) return null;

        chip = document.createElement('div');
        chip.id = 'garad-safe-tool-chip';
        chip.className = 'garad-safe-tool-chip';
        chip.hidden = true;
        chip.innerHTML = '<span class="garad-safe-tool-chip-dot"></span><span class="garad-safe-tool-chip-label"></span><button type="button" aria-label="Clear selected creation tool">×</button>';
        container.prepend(chip);
        qs('button', chip)?.addEventListener('click', () => setTool('', form));
        return chip;
    }

    function setTool(tool, form = qs('#chat_form')) {
        if (!form) return;
        const hidden = qs('#garad_selected_tool', form);
        const prompt = qs('#prompt', form);
        const chip = ensureChip(form);

        activeTool = TOOL_META[tool] ? tool : '';
        window.garadSelectedTool = activeTool;

        if (hidden) hidden.value = activeTool;
        qsa('[data-garad-create-tool]', form).forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.garadCreateTool === activeTool);
            btn.setAttribute('aria-pressed', btn.dataset.garadCreateTool === activeTool ? 'true' : 'false');
        });

        if (chip) {
            chip.hidden = !activeTool;
            const label = qs('.garad-safe-tool-chip-label', chip);
            if (label) label.textContent = activeTool ? `Create ${TOOL_META[activeTool].label}` : '';
        }

        if (prompt) {
            prompt.placeholder = activeTool ? TOOL_META[activeTool].placeholder : (prompt.dataset.garadOriginalPlaceholder || 'Type a message');
            if (!prompt.dataset.garadOriginalPlaceholder) {
                prompt.dataset.garadOriginalPlaceholder = 'Type a message';
            }
            prompt.focus({ preventScroll: true });
        }
    }

    function bind(form) {
        if (!form || form.dataset.garadSafeV4Bound === '1') return;
        form.dataset.garadSafeV4Bound = '1';
        document.documentElement.classList.add('garad-chatpro-safe-v4');

        const prompt = qs('#prompt', form);
        if (prompt && !prompt.dataset.garadOriginalPlaceholder) {
            prompt.dataset.garadOriginalPlaceholder = prompt.getAttribute('placeholder') || 'Type a message';
        }

        ensureChip(form);
        qsa('[data-garad-create-tool]', form).forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                setTool(button.dataset.garadCreateTool || '', form);
            });
        });

        const hidden = qs('#garad_selected_tool', form);
        if (hidden?.value) setTool(hidden.value, form);

        // Keep the selected tool long enough for the native FormData builder to read it,
        // then return the composer to normal chat mode for the next message.
        qs('#send_message_button', form)?.addEventListener('click', () => {
            if (!activeTool) return;
            window.garadActiveRequestTool = activeTool;
            window.setTimeout(() => setTool('', form), 1800);
        }, true);
    }

    function boot() {
        bind(qs('#chat_form'));
        if (!observer) {
            observer = new MutationObserver(() => bind(qs('#chat_form')));
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
