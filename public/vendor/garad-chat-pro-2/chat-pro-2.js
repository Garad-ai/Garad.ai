(() => {
    'use strict';

    const ready = (fn) => document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', fn, { once: true })
        : fn();

    ready(() => {
        const page = document.querySelector('.lqd-chat-v2');
        const root = document.querySelector('.lqd-chat-v2-container');
        if (!page || !root) return;

        const prompt = document.getElementById('prompt');
        const composer = document.getElementById('chat_form');
        const composerContainer = composer?.querySelector('.lqd-chat-form-inputs-container');
        const editMessageInput = document.getElementById('edit_message_id');
        let lastPrompt = '';

        const syncTheme = () => {
            const dark = document.body.classList.contains('theme-dark') ||
                document.documentElement.getAttribute('data-bs-theme') === 'dark' ||
                document.documentElement.getAttribute('data-cp-theme') === 'dark' ||
                localStorage.getItem('lqdDarkMode') === 'true';
            document.documentElement.classList.toggle('dark', dark);
        };
        syncTheme();
        new MutationObserver(syncTheme).observe(document.body, { attributes: true, attributeFilter: ['class'] });

        const setPromptValue = (value) => {
            if (!prompt) return;
            prompt.value = value || '';
            prompt.dispatchEvent(new Event('input', { bubbles: true }));
            prompt.dispatchEvent(new Event('change', { bubbles: true }));
            prompt.focus();
            try { prompt.setSelectionRange(prompt.value.length, prompt.value.length); } catch (_) {}
        };

        // ---------- Artifact Studio ----------
        const artifact = document.createElement('aside');
        artifact.id = 'garad-artifact-workspace';
        artifact.setAttribute('aria-hidden', 'true');
        artifact.innerHTML = `
            <div class="garad-artifact-head">
                <div class="garad-artifact-title">Garad Studio</div>
                <div class="garad-artifact-actions">
                    <a class="garad-artifact-download" href="#" target="_blank" rel="noopener" title="Download" aria-label="Download"><svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 18v2h14v-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                    <button class="garad-artifact-close" type="button" title="Close" aria-label="Close"><svg viewBox="0 0 24 24"><path d="m7 7 10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
                </div>
            </div>
            <iframe title="Garad Studio preview" loading="lazy"></iframe>`;
        document.body.appendChild(artifact);
        const artifactFrame = artifact.querySelector('iframe');
        const artifactTitle = artifact.querySelector('.garad-artifact-title');
        const artifactDownload = artifact.querySelector('.garad-artifact-download');
        const closeArtifact = () => {
            artifact.classList.remove('is-open');
            artifact.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('garad-artifact-open');
            window.setTimeout(() => { if (!artifact.classList.contains('is-open')) artifactFrame.src = 'about:blank'; }, 220);
        };
        artifact.querySelector('.garad-artifact-close')?.addEventListener('click', closeArtifact);

        document.addEventListener('click', (event) => {
            const anchor = event.target.closest?.('.garad-artifact-result-card a[href*="/artifacts/"], .garad-tool-result-card a[href*="/artifacts/"]');
            if (!anchor || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            const url = new URL(anchor.href, location.origin);
            const preview = new URL(url.toString());
            preview.searchParams.set('preview', '1');
            artifactTitle.textContent = anchor.closest('.garad-artifact-result-card, .garad-tool-result-card')?.querySelector('span')?.textContent?.trim() || 'Garad Studio';
            artifactDownload.href = url.toString();
            artifactFrame.src = preview.toString();
            artifact.classList.add('is-open');
            artifact.setAttribute('aria-hidden', 'false');
            document.body.classList.add('garad-artifact-open');
        });

        // ---------- Chat Pro workspace ----------
        const work = document.createElement('section');
        work.id = 'garad-workspace-panel';
        work.setAttribute('aria-hidden', 'true');
        work.innerHTML = `
            <div class="garad-workspace-head">
                <div class="garad-workspace-title">Garad Workspace</div>
                <button class="garad-workspace-close" type="button" aria-label="Back to chat" title="Back to chat"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg></button>
            </div>
            <iframe title="Garad workspace" loading="lazy"></iframe>`;
        root.appendChild(work);
        const workFrame = work.querySelector('iframe');
        const workTitle = work.querySelector('.garad-workspace-title');
        const navItems = Array.from(document.querySelectorAll('.garad-primary-nav-item'));

        const updateWorkspaceInset = () => {
            if (window.innerWidth < 992) {
                work.style.insetInlineStart = '0px';
            } else {
                work.style.insetInlineStart = root.classList.contains('garad-sidebar-open') ? '260px' : '52px';
            }
        };

        const setNavActive = (key) => navItems.forEach((a) => a.classList.toggle('is-active', key === 'chat' ? a.dataset.garadNav === 'chat' : a.dataset.garadWorkspace === key));
        const closeWorkspace = () => {
            work.classList.remove('is-open');
            work.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('garad-workspace-open');
            setNavActive('chat');
            window.setTimeout(() => { if (!work.classList.contains('is-open')) workFrame.src = 'about:blank'; }, 180);
            prompt?.focus();
        };
        const openWorkspace = (anchor) => {
            const url = new URL(anchor.href, location.origin);
            url.searchParams.set('embed', '1');
            workTitle.textContent = anchor.dataset.garadTitle || anchor.textContent.trim() || 'Garad Workspace';
            workFrame.src = url.toString();
            work.classList.add('is-open');
            work.setAttribute('aria-hidden', 'false');
            document.body.classList.add('garad-workspace-open');
            setNavActive(anchor.dataset.garadWorkspace || '');
            updateWorkspaceInset();
        };
        document.querySelectorAll('[data-garad-workspace]').forEach((anchor) => anchor.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            openWorkspace(anchor);
        }));
        work.querySelector('.garad-workspace-close')?.addEventListener('click', closeWorkspace);
        window.addEventListener('resize', updateWorkspaceInset);
        new MutationObserver(updateWorkspaceInset).observe(root, { attributes: true, attributeFilter: ['class'] });
        updateWorkspaceInset();

        window.addEventListener('message', (event) => {
            if (event.origin !== location.origin || !event.data) return;
            if (event.data.type === 'garad-workspace-close') return closeWorkspace();
            if (event.data.type === 'garad-chat-prompt') {
                closeWorkspace();
                window.setTimeout(() => setPromptValue(String(event.data.prompt || '')), 80);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (artifact.classList.contains('is-open')) closeArtifact();
            else if (work.classList.contains('is-open')) closeWorkspace();
        });

        // ---------- Composer / model control ----------
        const moveRealModelPickerIntoComposer = (toolbar) => {
            if (!toolbar) return null;
            let wrapper = toolbar.querySelector('.garad-inline-model-control');
            if (wrapper) return wrapper;

            const modal = document.querySelector('.select-ai-model-modal');
            if (!modal) return null;

            wrapper = modal.closest('[x-data="modelList"]') || modal.parentElement;
            if (!wrapper) return null;

            wrapper.classList.add('garad-inline-model-control');
            toolbar.prepend(wrapper);
            return wrapper;
        };

        const ensureEditBadge = (toolbar) => {
            let badge = toolbar.querySelector('.garad-edit-state');
            if (!badge) {
                badge = document.createElement('button');
                badge.type = 'button';
                badge.className = 'garad-edit-state';
                badge.hidden = true;
                badge.innerHTML = '<span>Editing message</span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m7 7 10 10M17 7 7 17"/></svg>';
                badge.addEventListener('click', () => clearEditState());
                toolbar.appendChild(badge);
            }
            return badge;
        };

        const installComposerToolbar = () => {
            if (!composerContainer) return;
            let toolbar = composerContainer.querySelector('.garad-composer-toolbar');
            if (!toolbar) {
                toolbar = document.createElement('div');
                toolbar.className = 'garad-composer-toolbar';
                composerContainer.appendChild(toolbar);
            }

            ensureEditBadge(toolbar);

            // Model selector stays in the dedicated Chat Pro top control bar.
        };
        const clearEditState = () => {
            document.body.classList.remove('garad-editing-mode');
            document.querySelectorAll('.garad-editing-target').forEach((el) => el.classList.remove('garad-editing-target'));
            if (editMessageInput) editMessageInput.value = '';
            const badge = composerContainer?.querySelector('.garad-edit-state');
            if (badge) badge.hidden = true;
        };

        const beginEdit = (bubble) => {
            const content = bubble?.querySelector('.chat-content');
            const messageId = bubble?.getAttribute('data-message-id');
            if (!content || !messageId) {
                setPromptValue(content?.innerText?.trim() || '');
                return;
            }
            document.querySelectorAll('.garad-editing-target').forEach((el) => el.classList.remove('garad-editing-target'));
            bubble.classList.add('garad-editing-target');
            if (editMessageInput) editMessageInput.value = messageId;
            document.body.classList.add('garad-editing-mode');
            installComposerToolbar();
            const badge = composerContainer?.querySelector('.garad-edit-state');
            if (badge) badge.hidden = false;
            setPromptValue(content.innerText.trim());
        };

        if (composer && prompt) {
            composer.addEventListener('submit', () => { lastPrompt = prompt.value.trim() || lastPrompt; }, true);
            composer.addEventListener('keydown', (event) => {
                if ((event.metaKey || event.ctrlKey) && event.shiftKey && event.key.toLowerCase() === 'r') {
                    event.preventDefault();
                    if (!prompt.value.trim()) setPromptValue('Remind me ');
                }
            });
        }

        // ---------- Edit buttons ----------
        const addUserEditButtons = () => {
            document.querySelectorAll('.lqd-chat-user-bubble').forEach((bubble) => {
                const content = bubble.querySelector('.chat-content');
                if (!content || bubble.querySelector('.garad-user-edit-btn')) return;
                let actions = bubble.querySelector('.lqd-chat-actions-wrap');
                if (!actions) {
                    actions = document.createElement('div');
                    actions.className = 'lqd-chat-actions-wrap';
                    bubble.querySelector('.chat-content-container')?.appendChild(actions);
                }
                const edit = document.createElement('button');
                edit.className = 'garad-user-edit-btn';
                edit.type = 'button';
                edit.title = 'Edit prompt';
                edit.setAttribute('aria-label', 'Edit prompt');
                edit.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg><span class="garad-sr-only">Edit</span>';
                edit.addEventListener('click', () => beginEdit(bubble));
                actions.prepend(edit);
            });
        };

        // ---------- Thinking / typing ----------
        const stagesFor = (text) => {
            const t = (text || '').toLowerCase();
            if (/remind|schedule|xasuusi|monitor|every day|daily|weekly/.test(t)) return ['Understanding the schedule…', 'Creating the task…', 'Confirming the schedule…'];
            if (/pdf|word|docx|document|report|proposal|excel|spreadsheet|xlsx|powerpoint|slides|presentation/.test(t)) return ['Planning the file…', 'Preparing the content…', 'Creating the file…', 'Checking the result…'];
            if (/image|sawir|photo|design|logo/.test(t)) return ['Understanding the visual…', 'Preparing the generation…', 'Creating the image…'];
            if (/video|clip/.test(t)) return ['Planning the video…', 'Starting generation…', 'Checking progress…'];
            if (/research|search|baar|web|latest|sources/.test(t)) return ['Planning the research…', 'Checking sources…', 'Comparing findings…', 'Preparing the answer…'];
            if (/agent/.test(t)) return ['Selecting the agent…', 'Running the task…', 'Checking the result…'];
            return ['Understanding your request…', 'Working through the details…', 'Preparing the answer…'];
        };

        const bindThinking = () => {
            document.querySelectorAll('#lqd-typing').forEach((typing) => {
                if (typing.dataset.garadThinkingBound === '1') return;
                typing.dataset.garadThinkingBound = '1';
                const label = typing.querySelector('#thinking-text');
                if (!label) return;
                const stages = stagesFor(lastPrompt || prompt?.value || '');
                let i = 0;
                label.textContent = stages[0];
                const timer = window.setInterval(() => {
                    if (!document.body.contains(typing)) {
                        window.clearInterval(timer);
                        return;
                    }
                    const bubble = typing.closest('.lqd-chat-ai-bubble');
                    const content = bubble?.querySelector('.chat-content')?.textContent?.trim() || '';
                    if (content.length > 0) {
                        typing.remove();
                        window.clearInterval(timer);
                        return;
                    }
                    i = Math.min(i + 1, stages.length - 1);
                    label.textContent = stages[i];
                }, 1800);
            });
        };

        // ---------- Scroll to bottom ----------
        const scrollBtn = document.createElement('button');
        scrollBtn.type = 'button';
        scrollBtn.className = 'garad-scroll-bottom';
        scrollBtn.setAttribute('aria-label', 'Scroll to latest message');
        scrollBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>';
        root.appendChild(scrollBtn);

        const getScrollArea = () => document.querySelector('.conversation-area') || document.querySelector('.chats-wrap') || document.scrollingElement;
        const scrollToBottom = (smooth = true) => {
            const area = getScrollArea();
            if (!area) return;
            area.scrollTo({ top: area.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        };
        const syncScrollBtn = () => {
            const area = getScrollArea();
            if (!area) return;
            const distance = area.scrollHeight - area.clientHeight - area.scrollTop;
            scrollBtn.classList.toggle('is-visible', distance > 220);
        };
        scrollBtn.addEventListener('click', () => scrollToBottom(true));
        window.addEventListener('resize', syncScrollBtn);
        document.addEventListener('chat:response-complete', () => {
            clearEditState();
            setTimeout(() => scrollToBottom(true), 80);
        });

        // ---------- Normalize ----------
        const normalize = () => {
            addUserEditButtons();
            installComposerToolbar();
            document.querySelectorAll('.garad-tool-result-card, .garad-artifact-result-card').forEach((card) => {
                card.dataset.garadPro2 = '1';
                const link = card.querySelector('a');
                if (link && !link.getAttribute('aria-label')) link.setAttribute('aria-label', link.textContent.trim() || 'Open result');
            });
            syncScrollBtn();
        };

        const scrollArea = getScrollArea();
        scrollArea?.addEventListener('scroll', syncScrollBtn, { passive: true });

        try {
            const url = new URL(window.location.href);
            const initialPrompt = url.searchParams.get('prompt');
            if (initialPrompt) {
                setPromptValue(initialPrompt);
                url.searchParams.delete('prompt');
                window.history.replaceState({}, '', url.toString());
            }
        } catch (_) {}

        normalize();
        new MutationObserver(normalize).observe(document.body, { childList: true, subtree: true });
    });
})();
