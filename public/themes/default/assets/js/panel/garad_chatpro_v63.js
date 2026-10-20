(() => {
  'use strict';

  if (window.__GARAD_CHATPRO_V63__) return;
  window.__GARAD_CHATPRO_V63__ = true;

  const tools = [
    { key: 'video',  tag: '@video',  label: 'Video',  icon: 'video' },
    { key: 'pdf',    tag: '@pdf',    label: 'PDF',    icon: 'pdf' },
    { key: 'excel',  tag: '@excel',  label: 'Excel',  icon: 'excel' },
    { key: 'word',   tag: '@word',   label: 'Word',   icon: 'word' },
    { key: 'image',  tag: '@image',  label: 'Image',  icon: 'image' },
    { key: 'slides', tag: '@slides', label: 'Slides', icon: 'slides' },
  ];

  const svg = (name, cls = '') => {
    const common = `class="${cls}" viewBox="0 0 24 24" fill="none" aria-hidden="true"`;
    const icons = {
      video: `<svg ${common}><rect x="3" y="5" width="13" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M16 10l4-2.5v9L16 14" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>`,
      pdf: `<svg ${common}><path d="M6 2.8h8l4 4V21H6a2 2 0 0 1-2-2V4.8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v5h4M8 13h2.2a1.6 1.6 0 1 1 0 3.2H8V13Zm5 0v3.2m0-3.2h1.6m-1.6 1.6h1.3" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/></svg>`,
      excel: `<svg ${common}><rect x="4" y="3" width="16" height="18" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 8l4 8m0-8-4 8M15 8h2M15 12h2M15 16h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`,
      word: `<svg ${common}><rect x="4" y="3" width="16" height="18" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="m7.5 8 1.7 8 2.1-5 2.1 5 1.8-8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
      image: `<svg ${common}><rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><circle cx="8.2" cy="9" r="1.6" stroke="currentColor" stroke-width="1.5"/><path d="m5 17 4.3-4.1 3.1 2.7 2.5-2.4L19 17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
      slides: `<svg ${common}><rect x="3" y="4" width="18" height="13" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 21h8M12 17v4M7 8h5M7 11h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>`,
      spinner: `<svg ${common}><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="2.2" opacity=".22"/><path d="M20.5 12A8.5 8.5 0 0 0 12 3.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>`,
      check: `<svg ${common}><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="m7.8 12.1 2.8 2.8 5.8-6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
      alert: `<svg ${common}><path d="M12 3.5 21 20H3L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4.6M12 17h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>`,
      external: `<svg ${common}><path d="M14 4h6v6M20 4l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>`,
      download: `<svg ${common}><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 18v2h14v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    };

    return icons[name] || icons.video;
  };

  const ensureStyles = () => {
    if (document.getElementById('garad-v63-runtime-styles')) return;

    const style = document.createElement('style');
    style.id = 'garad-v63-runtime-styles';
    style.textContent = `
      .garad-v63-tool-icon,
      .garad-v63-status-icon,
      .garad-v63-action-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
      }

      .garad-v63-tool-icon svg,
      .garad-v63-status-icon svg,
      .garad-v63-action-icon svg {
        width: 100%;
        height: 100%;
      }

      .garad-v63-at-item .garad-v63-tool-icon,
      [data-garad-v63-create-wrap] .garad-v63-tool-icon {
        transition: transform .2s ease, opacity .2s ease;
      }

      .garad-v63-at-item:hover .garad-v63-tool-icon,
      [data-garad-v63-create-wrap] button:hover .garad-v63-tool-icon {
        transform: translateY(-1px) scale(1.06);
      }

      .garad-v63-job-card {
        margin: 10px 0 14px;
        padding: 13px;
        border: 1px solid rgba(94, 23, 255, .18);
        border-radius: 16px;
        background: color-mix(in srgb, var(--tblr-body-bg, #fff) 94%, #5e17ff 6%);
        box-shadow: 0 8px 28px rgba(20, 20, 40, .06);
        max-width: 640px;
        overflow: hidden;
        color: inherit;
      }

      /*
       * Video results should feel like part of the conversation, not a
       * separate white/dark card. The outer container is transparent and
       * inherits the active Garad light/dark theme. Only the media itself
       * keeps a black canvas, which is correct for a video player.
       */
      .garad-v63-job-card--video {
        width: min(560px, 100%);
        max-width: 560px;
        margin-top: 8px;
        margin-bottom: 16px;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent !important;
        box-shadow: none;
        overflow: visible;
        color: inherit;
      }

      .garad-v63-job-card--video .garad-v63-job-head,
      .garad-v63-job-card--video .garad-v63-job-title,
      .garad-v63-job-card--video .garad-v63-job-status {
        color: inherit;
      }

      .garad-v63-job-card--video .garad-v63-job-head {
        padding: 0;
      }

      .garad-v63-job-card--video .garad-v63-job-status {
        margin-top: 5px;
        opacity: .68;
      }

      .garad-v63-job-card--video .garad-v63-job-media {
        margin-top: 10px;
        background: transparent;
      }

      .garad-v63-job-card--video .garad-v63-job-actions {
        margin-top: 9px;
      }

      .garad-v63-job-card--video .garad-v63-job-action {
        color: inherit;
        background: transparent;
        border-color: rgba(127, 127, 127, .22);
      }

      .garad-v63-job-card--video .garad-v63-job-action:hover {
        background: rgba(127, 127, 127, .08);
      }

      .garad-v63-job-head {
        display: flex;
        align-items: center;
        gap: 9px;
        font-weight: 650;
        line-height: 1.25;
      }

      .garad-v63-job-status {
        margin-top: 7px;
        font-size: 13px;
        opacity: .72;
      }

      .garad-v63-job-media {
        margin-top: 11px;
      }

      .garad-v63-job-media video {
        display: block;
        width: 100%;
        max-height: 430px;
        border-radius: 12px;
        background: #000;
      }

      .garad-v63-job-card--video .garad-v63-job-media video {
        width: 100%;
        max-height: 360px;
        border-radius: 14px;
        background: #000;
        box-shadow: none;
      }

      .garad-v63-job-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
      }

      .garad-v63-job-action {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 34px;
        padding: 7px 10px;
        border: 1px solid rgba(127,127,127,.2);
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        line-height: 1;
        transition: transform .18s ease, background-color .18s ease, border-color .18s ease;
      }

      .garad-v63-job-action:hover {
        transform: translateY(-1px);
      }

      .garad-v63-spin svg {
        animation: garad-v63-spin 1s linear infinite;
      }

      .garad-v63-ready {
        color: #16a34a;
      }

      .garad-v63-ready svg {
        animation: garad-v63-pop .28s ease-out both;
      }

      .garad-v63-error {
        color: #dc2626;
      }

      @keyframes garad-v63-spin {
        to { transform: rotate(360deg); }
      }

      @keyframes garad-v63-pop {
        0% { transform: scale(.72); opacity: .2; }
        70% { transform: scale(1.08); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
      }

      @media (max-width: 640px) {
        .garad-v63-job-card {
          border-radius: 14px;
          padding: 11px;
        }

        .garad-v63-job-card--video {
          width: 100%;
          max-width: 100%;
          margin-inline-start: 0 !important;
          padding: 0;
          border-radius: 0;
        }

        .garad-v63-job-media video,
        .garad-v63-job-card--video .garad-v63-job-media video {
          max-height: 330px;
        }
      }
    `;

    document.head.appendChild(style);
  };

  const chatBase = () => {
    const p = location.pathname;
    const marker = '/dashboard/user/openai/chat/pro';
    const i = p.indexOf(marker);
    return i >= 0 ? p.slice(0, i) + marker : marker;
  };

  const promptEl = () => document.getElementById('prompt');

  const selectedEl = () => {
    let el = document.getElementById('garad_selected_tool');

    if (!el) {
      const form = document.getElementById('chat_form');
      if (!form) return null;

      el = document.createElement('input');
      el.type = 'hidden';
      el.id = 'garad_selected_tool';
      el.name = 'garad_selected_tool';
      form.appendChild(el);
    }

    return el;
  };

  const setPromptValue = (value, cursor = null) => {
    const el = promptEl();
    if (!el) return;

    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));

    try {
      if (el._x_model) el._x_model.set(value);
    } catch (_) {}

    el.focus();

    const pos = cursor ?? value.length;

    try {
      el.setSelectionRange(pos, pos);
    } catch (_) {}
  };

  const chooseTool = (tool) => {
    const el = promptEl();
    if (!el) return;

    const hidden = selectedEl();

    if (hidden) {
      hidden.value = tool.key;
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    window.garadActiveRequestTool = tool.key;

    // Native create buttons select the capability only.
    // Do not inject @video/@pdf/etc. into the user's visible prompt.
    el.focus();
  };

  const installAtPicker = () => {
    const el = promptEl();

    if (!el || el.dataset.garadV63At === '1') return;

    el.dataset.garadV63At = '1';

    const host = el.closest('.relative') || el.parentElement;
    if (!host) return;

    const menu = document.createElement('div');
    menu.id = 'garad-v63-at-menu';
    menu.innerHTML = '<div class="garad-v63-at-title">Create with Garad AI</div><div class="garad-v63-at-list"></div>';

    host.appendChild(menu);

    const list = menu.querySelector('.garad-v63-at-list');
    let visible = [];
    let active = 0;

    const context = () => {
      const value = el.value || '';
      const cursor = el.selectionStart ?? value.length;
      const before = value.slice(0, cursor);
      const at = before.lastIndexOf('@');

      if (at < 0) return null;

      const token = before.slice(at);

      if (/\s/.test(token)) return null;

      return { value, cursor, at, token };
    };

    const close = () => {
      menu.classList.remove('is-open');
      list.innerHTML = '';
      visible = [];
      active = 0;
    };

    const insert = tool => {
      const c = context();

      if (!c) {
        chooseTool(tool);
        close();
        return;
      }

      const after = c.value.slice(el.selectionEnd ?? c.cursor);
      const before = c.value.slice(0, c.at);
      const next = before + after;
      const hidden = selectedEl();

      if (hidden) {
        hidden.value = tool.key;
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
      }

      window.garadActiveRequestTool = tool.key;

      setPromptValue(next, before.length);
      close();
    };

    const render = items => {
      visible = items;
      list.innerHTML = '';

      items.forEach((tool, index) => {
        const button = document.createElement('button');

        button.type = 'button';
        button.className = 'garad-v63-at-item' + (index === active ? ' is-active' : '');
        button.innerHTML = `
          <span class="garad-v63-tool-icon">${svg(tool.icon)}</span>
          <span>${tool.label}</span>
          <code>${tool.tag}</code>
        `;

        button.addEventListener('mousedown', event => {
          event.preventDefault();
          insert(tool);
        });

        list.appendChild(button);
      });

      menu.classList.toggle('is-open', items.length > 0);
    };

    const update = () => {
      const c = context();

      if (!c) {
        close();
        return;
      }

      const q = c.token.toLowerCase();
      active = 0;

      render(
        tools.filter(tool =>
          tool.tag.startsWith(q) ||
          tool.label.toLowerCase().startsWith(q.slice(1))
        )
      );
    };

    el.addEventListener('input', update);
    el.addEventListener('click', update);
    el.addEventListener('blur', () => setTimeout(close, 120));

    el.addEventListener('keydown', event => {
      if (!menu.classList.contains('is-open') || !visible.length) return;

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        active = (active + 1) % visible.length;
        render(visible);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active = (active - 1 + visible.length) % visible.length;
        render(visible);
      } else if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        insert(visible[active]);
      } else if (event.key === 'Escape') {
        close();
      }
    });
  };

  const ensureDynamicCreateButtons = () => {
    const upload = document.querySelector('#chat_form .lqd-chat-attach');
    if (!upload) return;

    const parent = upload.parentElement;

    if (!parent || parent.querySelector('[data-garad-v63-create-wrap]')) return;

    const wrap = document.createElement('div');

    wrap.setAttribute('data-garad-v63-create-wrap', '1');
    wrap.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid rgba(127,127,127,.12);display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;';

    tools.forEach(tool => {
      const button = document.createElement('button');

      button.type = 'button';
      button.setAttribute('data-garad-create-tool', tool.key);
      button.style.cssText = 'display:flex;align-items:center;gap:8px;width:100%;padding:8px 9px;border-radius:9px;font-size:12px;text-align:left;';
      button.innerHTML = `
        <span class="garad-v63-tool-icon">${svg(tool.icon)}</span>
        <span>${tool.label}</span>
      `;

      wrap.appendChild(button);
    });

    parent.appendChild(wrap);
  };

  const hookNativeCreateButtons = () => {
    document.querySelectorAll('[data-garad-create-tool]').forEach(button => {
      if (button.dataset.garadV63Hook === '1') return;

      button.dataset.garadV63Hook = '1';

      button.addEventListener('click', () => {
        const key = (button.getAttribute('data-garad-create-tool') || '').toLowerCase();

        const tool = tools.find(item =>
          item.key === key ||
          (key === 'presentation' && item.key === 'slides')
        );

        if (tool) {
          setTimeout(() => chooseTool(tool), 0);
        }
      });
    });
  };

  const syncSelectedToolFromPrompt = () => {
    const el = promptEl();
    if (!el) return;

    const match = (el.value || '').trim().match(/^@(video|pdf|excel|word|image|slides)\b/i);

    if (!match) return;

    const hidden = selectedEl();
    if (!hidden) return;

    hidden.value = match[1].toLowerCase();
    window.garadActiveRequestTool = hidden.value;
  };

  const autosize = () => {
    const el = promptEl();

    if (!el || el.dataset.garadV63Auto === '1') return;

    el.dataset.garadV63Auto = '1';

    const run = () => {
      el.style.height = 'auto';
      el.style.height = Math.min(Math.max(el.scrollHeight, 58), 210) + 'px';
      el.style.overflowY = el.scrollHeight > 210 ? 'auto' : 'hidden';
    };

    el.addEventListener('input', run, { passive: true });
    run();
  };

  const markdownLink = text => {
    const match = (text || '').match(/\[([^\]]+)\]\(([^)]+)\)/);
    return match ? { label: match[1], url: match[2] } : null;
  };

  const safeResultUrl = raw => {
    try {
      const url = new URL(raw, location.origin);

      if (!['http:', 'https:'].includes(url.protocol)) {
        return null;
      }

      return url.href;
    } catch (_) {
      return null;
    }
  };

  const cleanTechnicalVideoText = text => (text || '')
    .replace(/\[\[GARAD_VIDEO_JOB:\d+\]\]/gi, '')
    .replace(/Provider:\s*[^\r\n]+/gi, '')
    .replace(/Video ID:\s*`?\d+`?/gi, '')
    .replace(/Provider job:\s*`?[^`\r\n]+`?/gi, '')
    .replace(/The video is processing\.[^\r\n]*/gi, '')
    .replace(/Ask me to check the video status[^\r\n]*/gi, '')
    .replace(/\n{3,}/g, '\n\n');

  const sanitizeVideoBubble = bubble => {
    if (!bubble) return;

    const walker = document.createTreeWalker(
      bubble,
      NodeFilter.SHOW_TEXT
    );

    const nodes = [];

    while (walker.nextNode()) {
      nodes.push(walker.currentNode);
    }

    nodes.forEach(node => {
      const original = node.nodeValue || '';
      const cleaned = cleanTechnicalVideoText(original);

      if (cleaned !== original) {
        node.nodeValue = cleaned;
      }
    });

    bubble.querySelectorAll('p').forEach(p => {
      if (!p.textContent.trim()) {
        p.remove();
      }
    });
  };


  const cleanTechnicalPresentationText = text => (text || '')
    .replace(/\[\[GARAD_PRESENTATION_JOB:[^\]]+\]\]/gi, '')
    .replace(/\[\[GARAD_PRESENTATION_META:[^\]]+\]\]/gi, '')
    .replace(/Generation ID:\s*`?[^`\r\n]+`?/gi, '')
    .replace(/I['’]?m creating the presentation you requested\.?/gi, '')
    .replace(/Presentation generation started[^\r\n]*/gi, '')
    .replace(/Ask me to check the presentation status[^\r\n]*/gi, '')
    .replace(/\n{3,}/g, '\n\n');

  const sanitizePresentationBubble = bubble => {
    if (!bubble) return;

    const walker = document.createTreeWalker(bubble, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);

    nodes.forEach(node => {
      const original = node.nodeValue || '';
      const cleaned = cleanTechnicalPresentationText(original);
      if (cleaned !== original) node.nodeValue = cleaned;
    });

    bubble.querySelectorAll('p').forEach(p => {
      if (!p.textContent.trim()) p.remove();
    });
  };

  const disableLegacyVideoPolling = id => {
    const legacyKey = `__garad_v62_poll_video_${id}`;
    const legacyHandle = window[legacyKey];

    if (typeof legacyHandle === 'number') {
      clearInterval(legacyHandle);
    }

    window[legacyKey] = '__disabled_by_v63__';

    document
      .querySelectorAll(`.garad-v62-video-${CSS.escape(String(id))}`)
      .forEach(element => element.remove());
  };

  const removeDuplicateJobCards = (type, id) => {
    const selector = `.garad-v63-${type}-${CSS.escape(String(id))}`;
    const cards = [...document.querySelectorAll(selector)];

    cards.slice(1).forEach(card => card.remove());

    return cards[0] || null;
  };

  const statusIcon = (kind = 'loading') => {
    const span = document.createElement('span');

    span.className = 'garad-v63-status-icon';

    if (kind === 'loading') {
      span.classList.add('garad-v63-spin');
      span.innerHTML = svg('spinner');
    } else if (kind === 'ready') {
      span.classList.add('garad-v63-ready');
      span.innerHTML = svg('check');
    } else {
      span.classList.add('garad-v63-error');
      span.innerHTML = svg('alert');
    }

    return span;
  };

  const alignVideoCardToBubble = (card, bubble) => {
    if (!card || !bubble || card.dataset.garadJobType !== 'video') return;

    const parent = card.parentElement;
    if (!parent) return;

    const apply = () => {
      if (!card.isConnected || !bubble.isConnected) return;

      // On phones the card naturally spans the conversation width.
      if (window.matchMedia('(max-width: 640px)').matches) {
        card.style.marginInlineStart = '0px';
        return;
      }

      const parentRect = parent.getBoundingClientRect();
      const bubbleRect = bubble.getBoundingClientRect();
      const offset = Math.max(0, Math.round(bubbleRect.left - parentRect.left));

      // Match the left edge of the assistant text without forcing the
      // bubble's width onto the video player.
      card.style.marginInlineStart = `${offset}px`;
    };

    requestAnimationFrame(apply);

    if (!card.__garadV63ResizeBound) {
      card.__garadV63ResizeBound = true;
      const onResize = () => requestAnimationFrame(apply);
      window.addEventListener('resize', onResize, { passive: true });
    }
  };

  const jobCard = (bubble, type, id) => {
    const existing = removeDuplicateJobCards(type, id);

    if (existing) return existing;

    const card = document.createElement('div');
    const cls = `garad-v63-${type}-${CSS.escape(String(id))}`;

    card.className = `garad-v63-job-card ${cls} ${type === 'video' ? 'garad-v63-job-card--video' : 'garad-v63-job-card--presentation'}`;
    card.dataset.garadJobType = type;

    const head = document.createElement('div');
    head.className = 'garad-v63-job-head';
    head.appendChild(statusIcon('loading'));

    const title = document.createElement('span');
    title.className = 'garad-v63-job-title';
    title.textContent = type === 'video' ? 'Generating video…' : 'Generating slides…';

    head.appendChild(title);

    const status = document.createElement('div');
    status.className = 'garad-v63-job-status';
    status.textContent = type === 'video'
      ? 'Your video is being created…'
      : 'Processing…';

    const media = document.createElement('div');
    media.className = 'garad-v63-job-media';

    card.appendChild(head);
    card.appendChild(status);
    card.appendChild(media);

    bubble.insertAdjacentElement('afterend', card);

    if (type === 'video') {
      alignVideoCardToBubble(card, bubble);
    }

    return card;
  };

  const replaceStatusIcon = (card, kind) => {
    const current = card.querySelector('.garad-v63-status-icon');
    const next = statusIcon(kind);

    if (current) {
      current.replaceWith(next);
    } else {
      card.querySelector('.garad-v63-job-head')?.prepend(next);
    }
  };

  const addResultActions = (media, url, type) => {
    const actions = document.createElement('div');
    actions.className = 'garad-v63-job-actions';

    const open = document.createElement('a');
    open.className = 'garad-v63-job-action';
    open.href = url;
    open.target = '_blank';
    open.rel = 'noopener noreferrer';
    open.innerHTML = `
      <span class="garad-v63-action-icon">${svg('external')}</span>
      <span>Open</span>
    `;

    const download = document.createElement('a');
    download.className = 'garad-v63-job-action';
    download.href = url;
    download.target = '_blank';
    download.rel = 'noopener noreferrer';
    download.innerHTML = `
      <span class="garad-v63-action-icon">${svg('download')}</span>
      <span>Download</span>
    `;

    try {
      const parsed = new URL(url);

      if (parsed.origin === location.origin) {
        download.removeAttribute('target');
        download.setAttribute('download', '');
      }
    } catch (_) {}

    actions.appendChild(open);
    actions.appendChild(download);

    media.appendChild(actions);
  };

  const renderJob = (card, type, message) => {
    const status = card.querySelector('.garad-v63-job-status');
    const link = markdownLink(message || '');

    if (!link) {
      if (status) {
        if (type === 'video') {
          const progress = (message || '').match(/Progress:\s*(\d{1,3})%/i);

          status.textContent = progress
            ? `Generating video… ${progress[1]}%`
            : 'Your video is being created…';
        } else {
          status.textContent = (message || 'Processing…')
            .replace(/[`*]/g, '')
            .trim();
        }
      }

      return false;
    }

    const url = safeResultUrl(link.url);

    if (!url) {
      return false;
    }

    card.classList.add('is-done');
    replaceStatusIcon(card, 'ready');

    const title = card.querySelector('.garad-v63-job-title');

    if (title) {
      title.textContent = type === 'video'
        ? 'Video ready'
        : 'Slides ready';
    }

    if (status) {
      status.textContent = type === 'video'
        ? 'Your video is ready.'
        : 'Your presentation is ready.';
    }

    const media = card.querySelector('.garad-v63-job-media');
    media.innerHTML = '';

    if (type === 'video') {
      const video = document.createElement('video');

      video.controls = true;
      video.preload = 'metadata';
      video.src = url;

      media.appendChild(video);
    }

    addResultActions(media, url, type);

    return true;
  };

  const setJobFailure = (card, type, message = '') => {
    card.classList.add('is-done');
    replaceStatusIcon(card, 'error');

    const title = card.querySelector('.garad-v63-job-title');
    const status = card.querySelector('.garad-v63-job-status');

    if (title) {
      title.textContent = type === 'video'
        ? 'Video could not be completed'
        : 'Generation failed';
    }

    if (status) {
      status.textContent = /credit/i.test(message)
        ? 'Not enough credits to complete this request.'
        : type === 'video'
          ? 'Video generation could not be completed. Please try again.'
          : 'Generation could not be completed. Please try again.';
    }
  };

  const poll = (bubble, type, id) => {
    const key = `__garad_v63_poll_${type}_${id}`;

    if (window[key]?.active) return;

    if (type === 'video') {
      disableLegacyVideoPolling(id);
    }

    const card = jobCard(bubble, type, id);
    const encoded = encodeURIComponent(id);

    const url = type === 'video'
      ? `${chatBase()}/garad-video-status/${encoded}`
      : `${chatBase()}/garad-presentation-status/${encoded}`;

    const state = {
      active: true,
      timer: null,
      inFlight: false,
      attempts: 0,
    };

    window[key] = state;

    const stop = () => {
      state.active = false;

      if (state.timer) {
        clearTimeout(state.timer);
      }

      state.timer = null;
      window[key] = null;
    };

    const schedule = () => {
      if (!state.active) return;

      state.timer = setTimeout(tick, 5000);
    };

    const tick = async () => {
      if (!state.active || state.inFlight) return;

      state.inFlight = true;
      state.attempts += 1;

      try {
        const response = await fetch(url, {
          method: 'GET',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
        });

        let data = null;

        try {
          data = await response.json();
        } catch (_) {}

        if (!response.ok) {
          const message = data?.message || '';

          if (
            response.status === 401 ||
            response.status === 403 ||
            response.status === 404
          ) {
            setJobFailure(card, type, message);
            stop();
            return;
          }

          throw new Error('Temporary status request failure.');
        }

        const message = data?.message || 'Processing…';

        if (renderJob(card, type, message)) {
          stop();
          return;
        }

        if (
          !data?.ok &&
          /failed|error|not found|credits|cannot be checked/i.test(message)
        ) {
          setJobFailure(card, type, message);
          stop();
          return;
        }
      } catch (_) {
        // Transient network/provider-status failures stay private and are retried.
      } finally {
        state.inFlight = false;
      }

      if (!state.active) return;

      if (state.attempts >= 240) {
        replaceStatusIcon(card, 'error');

        const title = card.querySelector('.garad-v63-job-title');
        const status = card.querySelector('.garad-v63-job-status');

        if (title) {
          title.textContent = 'Still processing';
        }

        if (status) {
          status.textContent = 'This request is taking longer than expected. You can return to this conversation later.';
        }

        stop();
        return;
      }

      schedule();
    };

    tick();
  };

  const scanJobs = () => {
    const bubbles = [...document.querySelectorAll('.lqd-chat-ai-bubble')];

    let latestVideo = null;
    let latestPresentation = null;

    bubbles.forEach(bubble => {
      const text = bubble.textContent || '';

      const marker = text.match(/\[\[GARAD_VIDEO_JOB:(\d+)\]\]/i);
      const legacy = text.match(/Video ID:\s*`?(\d+)`?/i);
      const videoMatch = marker || legacy;

      if (
        videoMatch &&
        (
          marker ||
          /video generation started|creating the video/i.test(text)
        )
      ) {
        disableLegacyVideoPolling(videoMatch[1]);
        sanitizeVideoBubble(bubble);

        latestVideo = {
          bubble,
          id: videoMatch[1],
        };
      }

      const presentationMarker = text.match(/\[\[GARAD_PRESENTATION_JOB:([^\]]+)\]\]/i);
      const presentationLegacy = text.match(/Generation ID:\s*`?([^\s`]+)`?/i);
      const presentationMatch = presentationMarker || presentationLegacy;

      if (
        presentationMatch &&
        (
          presentationMarker ||
          /presentation generation started|creating the presentation/i.test(text)
        )
      ) {
        sanitizePresentationBubble(bubble);

        latestPresentation = {
          bubble,
          id: presentationMatch[1],
        };
      }
    });

    // Auto-poll only the newest active video so a long conversation
    // never starts many provider-status loops at once.
    if (latestVideo) {
      poll(latestVideo.bubble, 'video', latestVideo.id);
    }

    if (latestPresentation) {
      poll(
        latestPresentation.bubble,
        'presentation',
        latestPresentation.id
      );
    }
  };

  let scanQueued = false;

  const queueScan = () => {
    if (scanQueued) return;

    scanQueued = true;

    requestAnimationFrame(() => {
      scanQueued = false;
      scanJobs();
    });
  };

  const boot = () => {
    ensureStyles();
    ensureDynamicCreateButtons();
    installAtPicker();
    hookNativeCreateButtons();
    autosize();
    scanJobs();

    const el = promptEl();

    if (el && el.dataset.garadV63Sync !== '1') {
      el.dataset.garadV63Sync = '1';
      el.addEventListener('input', syncSelectedToolFromPrompt);
    }

    const observer = new MutationObserver(() => {
      ensureDynamicCreateButtons();
      installAtPicker();
      hookNativeCreateButtons();
      autosize();
      queueScan();
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();