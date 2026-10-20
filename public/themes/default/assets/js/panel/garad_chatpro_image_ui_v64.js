(() => {
  'use strict';

  if (window.__GARAD_CHATPRO_IMAGE_UI_V64__) return;
  window.__GARAD_CHATPRO_IMAGE_UI_V64__ = true;

  const STATE = {
    pending: [],
    observer: null,
  };

  const svg = (name) => {
    const common = 'viewBox="0 0 24 24" fill="none" aria-hidden="true"';
    const icons = {
      spinner: `<svg ${common}><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="2" opacity=".2"/><path d="M20.5 12A8.5 8.5 0 0 0 12 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>`,
      check: `<svg ${common}><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="m7.8 12.2 2.8 2.7 5.8-6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
      image: `<svg ${common}><rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><circle cx="8.2" cy="9" r="1.5" stroke="currentColor" stroke-width="1.5"/><path d="m5 17 4.4-4.2 3.1 2.8 2.5-2.4L19 17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
      palette: `<svg ${common}><path d="M12 3a9 9 0 0 0 0 18h1.2a1.8 1.8 0 0 0 1.4-2.9l-.5-.6a1.6 1.6 0 0 1 1.2-2.6H17a4 4 0 0 0 4-4A8 8 0 0 0 12 3Z" stroke="currentColor" stroke-width="1.7"/><circle cx="7.5" cy="10" r="1" fill="currentColor"/><circle cx="10" cy="6.8" r="1" fill="currentColor"/><circle cx="14" cy="6.5" r="1" fill="currentColor"/></svg>`,
      sparkles: `<svg ${common}><path d="M12 3l1.6 4.2L18 9l-4.4 1.7L12 15l-1.6-4.3L6 9l4.4-1.8L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m18.2 14 .7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>`,
      download: `<svg ${common}><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 18v2h14v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
      open: `<svg ${common}><path d="M14 4h6v6M20 4l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>`,
      alert: `<svg ${common}><path d="M12 3.5 21 20H3L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4.6M12 17h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>`,
    };
    return icons[name] || icons.image;
  };

  const ensureStyles = () => {
    if (document.getElementById('garad-image-ui-v64-style')) return;

    const style = document.createElement('style');
    style.id = 'garad-image-ui-v64-style';
    style.textContent = `
      .garad-image-v64-card {
        width: min(100%, 560px);
        max-width: 560px;
        margin: 12px 0 18px;
        padding: 13px;
        border: 1px solid rgba(103, 76, 255, .18);
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,247,255,.98));
        box-shadow: 0 8px 26px rgba(27, 22, 66, .055);
        overflow: hidden;
      }

      html.dark .garad-image-v64-card,
      body.dark .garad-image-v64-card,
      .dark .garad-image-v64-card {
        background: linear-gradient(180deg, rgba(23,24,30,.98), rgba(28,29,39,.98));
        border-color: rgba(132,112,255,.22);
      }

      .garad-image-v64-head { display:flex; align-items:center; gap:10px; }
      .garad-image-v64-icon {
        width:34px; height:34px; flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center;
        border-radius:999px; color:#684cff; background:rgba(104,76,255,.11);
      }
      .garad-image-v64-icon svg { width:17px; height:17px; }
      .garad-image-v64-icon.is-loading svg { animation:garad-image-v64-spin 1s linear infinite; }
      .garad-image-v64-icon.is-ready { color:#16a34a; background:rgba(22,163,74,.11); }
      .garad-image-v64-icon.is-error { color:#dc2626; background:rgba(220,38,38,.1); }
      .garad-image-v64-title { font-size:14px; font-weight:700; line-height:1.25; }
      .garad-image-v64-subtitle { margin-top:2px; font-size:12px; line-height:1.4; opacity:.7; }

      .garad-image-v64-preview {
        position:relative; height:158px; margin-top:11px; overflow:hidden; border-radius:13px;
        background:
          radial-gradient(circle at 20% 20%, rgba(118,90,255,.22), transparent 36%),
          radial-gradient(circle at 80% 24%, rgba(0,190,255,.16), transparent 34%),
          linear-gradient(135deg,#171927,#282e49);
      }
      .garad-image-v64-preview::before {
        content:""; position:absolute; inset:0; transform:translateX(-100%);
        background:linear-gradient(105deg,transparent,rgba(255,255,255,.09),transparent);
        animation:garad-image-v64-shimmer 2s linear infinite;
      }
      .garad-image-v64-preview-grid {
        position:absolute; inset:14px; display:grid; grid-template-columns:1.2fr .8fr; gap:10px;
      }
      .garad-image-v64-block { border-radius:10px; background:rgba(255,255,255,.09); overflow:hidden; position:relative; }
      .garad-image-v64-block::after {
        content:""; position:absolute; inset:0; transform:translateX(-100%);
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.09),transparent);
        animation:garad-image-v64-shimmer 1.8s linear infinite;
      }
      .garad-image-v64-stack { display:grid; gap:10px; }

      .garad-image-v64-progress-head {
        display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:11px; margin-bottom:6px;
        font-size:11.5px; font-weight:600;
      }
      .garad-image-v64-progress { height:6px; border-radius:999px; overflow:hidden; background:rgba(127,127,127,.14); }
      .garad-image-v64-progress-fill {
        width:12%; height:100%; border-radius:inherit; background:linear-gradient(90deg,#684cff,#00baf5); transition:width .45s ease;
      }

      .garad-image-v64-stages { display:grid; gap:6px; margin-top:11px; }
      .garad-image-v64-stage { display:flex; align-items:center; gap:8px; min-height:22px; font-size:11.5px; opacity:.52; }
      .garad-image-v64-stage.active { opacity:1; font-weight:600; }
      .garad-image-v64-stage.done { opacity:.85; }
      .garad-image-v64-stage-icon {
        width:19px; height:19px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center;
        background:rgba(127,127,127,.1);
      }
      .garad-image-v64-stage-icon svg { width:11px; height:11px; }
      .garad-image-v64-stage.active .garad-image-v64-stage-icon { color:#684cff; background:rgba(104,76,255,.12); }
      .garad-image-v64-stage.done .garad-image-v64-stage-icon { color:#16a34a; background:rgba(22,163,74,.12); }

      .garad-image-v64-result { margin-top:11px; }
      .garad-image-v64-result img {
        display:block; width:100%; max-height:430px; object-fit:contain; border-radius:13px; background:rgba(127,127,127,.06);
      }
      .garad-image-v64-actions { display:flex; flex-wrap:wrap; gap:7px; margin-top:9px; }
      .garad-image-v64-action {
        min-height:32px; display:inline-flex; align-items:center; gap:6px; padding:6px 9px;
        border:1px solid rgba(127,127,127,.2); border-radius:9px; text-decoration:none; font-size:11.5px;
      }
      .garad-image-v64-action svg { width:14px; height:14px; }
      .garad-image-v64-card.is-ready .garad-image-v64-preview { display:none; }
      .garad-image-v64-card.is-error .garad-image-v64-preview { display:none; }

      @keyframes garad-image-v64-spin { to { transform:rotate(360deg); } }
      @keyframes garad-image-v64-shimmer { to { transform:translateX(100%); } }

      @media (prefers-reduced-motion: reduce) {
        .garad-image-v64-icon.is-loading svg,
        .garad-image-v64-preview::before,
        .garad-image-v64-block::after { animation:none !important; }
      }

      @media (max-width:640px) {
        .garad-image-v64-card { width:calc(100% - 8px); max-width:none; padding:10px; }
        .garad-image-v64-preview { height:135px; }
        .garad-image-v64-result img { max-height:320px; }
      }
    `;
    document.head.appendChild(style);
  };

  const hasBrandVoice = () => {
    const el = document.querySelector('[name="chat_brand_voice"], #chat_brand_voice');
    return !!(el && String(el.value || '').trim() && String(el.value) !== '0');
  };

  const selectedTool = () => {
    const hidden = document.getElementById('garad_selected_tool');
    const value = (hidden?.value || window.garadActiveRequestTool || '').toString().toLowerCase();
    return value;
  };

  const isImageIntent = () => {
    if (selectedTool() === 'image') return true;

    const prompt = (document.getElementById('prompt')?.value || '').trim();
    if (/^@image\b/i.test(prompt)) return true;

    return /\b(create|generate|make|design|samee|sawir)\b.{0,40}\b(image|picture|poster|visual|sawir|sawirka)\b/iu.test(prompt);
  };

  const latestUserBubble = () => {
    const bubbles = [...document.querySelectorAll('.lqd-chat-user-bubble')];
    return bubbles.at(-1) || null;
  };

  const createLoadingCard = (baselineAiCount) => {
    const card = document.createElement('div');
    card.className = 'garad-image-v64-card';
    card.dataset.garadImagePending = `${Date.now()}-${Math.random().toString(16).slice(2)}`;

    const branded = hasBrandVoice();
    card.innerHTML = `
      <div class="garad-image-v64-head">
        <span class="garad-image-v64-icon is-loading">${svg('spinner')}</span>
        <div>
          <div class="garad-image-v64-title">Creating your image</div>
          <div class="garad-image-v64-subtitle">Garad AI is preparing the visual you requested.</div>
        </div>
      </div>

      <div class="garad-image-v64-preview">
        <div class="garad-image-v64-preview-grid">
          <div class="garad-image-v64-block"></div>
          <div class="garad-image-v64-stack">
            <div class="garad-image-v64-block"></div>
            <div class="garad-image-v64-block"></div>
          </div>
        </div>
      </div>

      <div class="garad-image-v64-progress-head">
        <span class="garad-image-v64-progress-label">Understanding your request…</span>
        <span class="garad-image-v64-percent">12%</span>
      </div>
      <div class="garad-image-v64-progress"><div class="garad-image-v64-progress-fill"></div></div>

      <div class="garad-image-v64-stages">
        <div class="garad-image-v64-stage active" data-stage="0"><span class="garad-image-v64-stage-icon">${svg('sparkles')}</span><span>Understanding your request</span></div>
        <div class="garad-image-v64-stage" data-stage="1"><span class="garad-image-v64-stage-icon">${svg('palette')}</span><span>${branded ? 'Applying brand identity' : 'Planning the visual direction'}</span></div>
        <div class="garad-image-v64-stage" data-stage="2"><span class="garad-image-v64-stage-icon">${svg('image')}</span><span>Generating the image</span></div>
        <div class="garad-image-v64-stage" data-stage="3"><span class="garad-image-v64-stage-icon">${svg('sparkles')}</span><span>Finalizing the image</span></div>
      </div>

      <div class="garad-image-v64-result"></div>
    `;

    const pending = {
      card,
      baselineAiCount,
      attempt: 0,
      timer: null,
      completed: false,
    };
    STATE.pending.push(pending);

    let tries = 0;
    const place = () => {
      tries += 1;
      const userBubble = latestUserBubble();
      if (userBubble && card.isConnected === false) {
        userBubble.insertAdjacentElement('afterend', card);
        return;
      }
      if (!card.isConnected && tries < 20) setTimeout(place, 100);
    };
    setTimeout(place, 60);

    const tick = () => {
      if (pending.completed || !card.isConnected) return;
      pending.attempt += 1;
      updateProgress(pending);
      pending.timer = setTimeout(tick, 3500);
    };
    pending.timer = setTimeout(tick, 2200);

    return pending;
  };

  const updateProgress = (pending) => {
    const { card, attempt } = pending;
    const steps = [
      { max: 1, percent: 18, stage: 0, text: 'Understanding your request…' },
      { max: 2, percent: 36, stage: 1, text: hasBrandVoice() ? 'Applying your brand identity…' : 'Planning the visual direction…' },
      { max: 5, percent: 63, stage: 2, text: 'Generating your image…' },
      { max: 8, percent: 82, stage: 3, text: 'Finalizing the image…' },
      { max: Infinity, percent: 92, stage: 3, text: 'Your image is almost ready…' },
    ];
    const current = steps.find((s) => attempt <= s.max) || steps.at(-1);

    card.querySelector('.garad-image-v64-progress-fill')?.style.setProperty('width', `${current.percent}%`);
    const p = card.querySelector('.garad-image-v64-percent');
    const l = card.querySelector('.garad-image-v64-progress-label');
    if (p) p.textContent = `${current.percent}%`;
    if (l) l.textContent = current.text;

    const stages = [...card.querySelectorAll('.garad-image-v64-stage')];
    const stageIcons = ['sparkles', 'palette', 'image', 'sparkles'];
    stages.forEach((stage, index) => {
      stage.classList.remove('active', 'done');
      const icon = stage.querySelector('.garad-image-v64-stage-icon');
      if (index < current.stage) {
        stage.classList.add('done');
        if (icon) icon.innerHTML = svg('check');
      } else if (index === current.stage) {
        stage.classList.add('active');
        if (icon) icon.innerHTML = svg(stageIcons[index]);
      } else if (icon) {
        icon.innerHTML = svg(stageIcons[index]);
      }
    });
  };

  const safeImageUrl = (raw) => {
    if (!raw) return null;
    try {
      const url = new URL(raw, location.origin);
      if (!['http:', 'https:'].includes(url.protocol)) return null;
      return url.href;
    } catch (_) {
      return null;
    }
  };

  const extractImageUrlFromBubble = (bubble) => {
    const img = bubble.querySelector('img[src]');
    if (img) return safeImageUrl(img.getAttribute('src'));

    const links = [...bubble.querySelectorAll('a[href]')];
    for (const link of links) {
      const href = safeImageUrl(link.getAttribute('href'));
      if (href && /\.(png|jpe?g|webp|gif)(?:$|[?#])/i.test(href)) return href;
      if (href && /\/uploads\//i.test(href)) return href;
    }
    return null;
  };

  const completePending = (pending, url, bubble) => {
    if (!pending || pending.completed) return;
    pending.completed = true;
    clearTimeout(pending.timer);

    const card = pending.card;
    card.classList.add('is-ready');

    const icon = card.querySelector('.garad-image-v64-icon');
    if (icon) {
      icon.classList.remove('is-loading');
      icon.classList.add('is-ready');
      icon.innerHTML = svg('check');
    }
    const title = card.querySelector('.garad-image-v64-title');
    const subtitle = card.querySelector('.garad-image-v64-subtitle');
    const label = card.querySelector('.garad-image-v64-progress-label');
    const percent = card.querySelector('.garad-image-v64-percent');
    const fill = card.querySelector('.garad-image-v64-progress-fill');
    if (title) title.textContent = 'Image ready';
    if (subtitle) subtitle.textContent = 'Your image has been created successfully.';
    if (label) label.textContent = 'Completed';
    if (percent) percent.textContent = '100%';
    if (fill) fill.style.width = '100%';

    card.querySelectorAll('.garad-image-v64-stage').forEach((stage) => {
      stage.classList.remove('active');
      stage.classList.add('done');
      const i = stage.querySelector('.garad-image-v64-stage-icon');
      if (i) i.innerHTML = svg('check');
    });

    const result = card.querySelector('.garad-image-v64-result');
    if (!result) return;
    result.innerHTML = '';

    const image = document.createElement('img');
    image.alt = 'Generated image';
    image.loading = 'eager';
    image.src = url;

    const actions = document.createElement('div');
    actions.className = 'garad-image-v64-actions';

    const open = document.createElement('a');
    open.className = 'garad-image-v64-action';
    open.href = url;
    open.target = '_blank';
    open.rel = 'noopener noreferrer';
    open.innerHTML = `${svg('open')}<span>Open</span>`;

    const download = document.createElement('a');
    download.className = 'garad-image-v64-action';
    download.href = url;
    download.target = '_blank';
    download.rel = 'noopener noreferrer';
    download.innerHTML = `${svg('download')}<span>Download</span>`;

    try {
      const parsed = new URL(url);
      if (parsed.origin === location.origin) {
        download.removeAttribute('target');
        download.setAttribute('download', '');
      }
    } catch (_) {}

    image.addEventListener('load', () => {
      if (bubble) bubble.style.display = 'none';
    }, { once: true });

    image.addEventListener('error', () => {
      failPending(pending, 'The image file could not be displayed.');
      if (bubble) bubble.style.display = '';
    }, { once: true });

    result.appendChild(image);
    actions.appendChild(open);
    actions.appendChild(download);
    result.appendChild(actions);
  };

  const failPending = (pending, message = 'Image generation could not be completed.') => {
    if (!pending || pending.completed) return;
    pending.completed = true;
    clearTimeout(pending.timer);
    const card = pending.card;
    card.classList.add('is-error');

    const icon = card.querySelector('.garad-image-v64-icon');
    if (icon) {
      icon.classList.remove('is-loading');
      icon.classList.add('is-error');
      icon.innerHTML = svg('alert');
    }
    const title = card.querySelector('.garad-image-v64-title');
    const subtitle = card.querySelector('.garad-image-v64-subtitle');
    const label = card.querySelector('.garad-image-v64-progress-label');
    const percent = card.querySelector('.garad-image-v64-percent');
    if (title) title.textContent = 'Image could not be completed';
    if (subtitle) subtitle.textContent = message;
    if (label) label.textContent = 'Generation stopped';
    if (percent) percent.textContent = '';
  };

  const activePending = () => STATE.pending.find((item) => !item.completed) || null;

  const scanResults = () => {
    const pending = activePending();
    if (!pending) return;

    const bubbles = [...document.querySelectorAll('.lqd-chat-ai-bubble')];
    const candidates = bubbles.slice(Math.max(0, pending.baselineAiCount));

    for (const bubble of candidates) {
      if (bubble.dataset.garadImageV64Processed === '1') continue;
      const text = (bubble.textContent || '').trim();
      const url = extractImageUrlFromBubble(bubble);

      if (url && (/image created successfully/i.test(text) || bubble.querySelector('img[src]'))) {
        bubble.dataset.garadImageV64Processed = '1';
        completePending(pending, url, bubble);
        return;
      }

      if (/image generation failed|could not be generated|no credits|daily image generation limit/i.test(text)) {
        bubble.dataset.garadImageV64Processed = '1';
        failPending(pending, 'The image request could not be completed. Please try again.');
        return;
      }
    }
  };

  const hookForm = () => {
    const form = document.getElementById('chat_form');
    if (!form || form.dataset.garadImageV64Hooked === '1') return;
    form.dataset.garadImageV64Hooked = '1';

    form.addEventListener('submit', () => {
      if (!isImageIntent()) return;
      const baseline = document.querySelectorAll('.lqd-chat-ai-bubble').length;
      createLoadingCard(baseline);
    }, true);
  };

  let scanQueued = false;
  const queueScan = () => {
    if (scanQueued) return;
    scanQueued = true;
    requestAnimationFrame(() => {
      scanQueued = false;
      hookForm();
      scanResults();
    });
  };

  const boot = () => {
    ensureStyles();
    hookForm();

    STATE.observer = new MutationObserver(queueScan);
    STATE.observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();