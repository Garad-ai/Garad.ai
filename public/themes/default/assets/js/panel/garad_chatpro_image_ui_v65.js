(() => {
  'use strict';

  if (window.__GARAD_CHATPRO_IMAGE_UI_V65__) return;
  window.__GARAD_CHATPRO_IMAGE_UI_V65__ = true;

  const STATE = {
    pending: [],
    observer: null,
    lastStartAt: 0,
    lastPrompt: '',
  };

  const svg = (name) => {
    const common = 'viewBox="0 0 24 24" fill="none" aria-hidden="true"';

    const icons = {
      spinner: `<svg ${common}>
        <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="2" opacity=".2"/>
        <path d="M20.5 12A8.5 8.5 0 0 0 12 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>`,

      check: `<svg ${common}>
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
        <path d="m7.8 12.2 2.8 2.7 5.8-6" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`,

      image: `<svg ${common}>
        <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/>
        <circle cx="8.2" cy="9" r="1.5" stroke="currentColor" stroke-width="1.5"/>
        <path d="m5 17 4.4-4.2 3.1 2.8 2.5-2.4L19 17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`,

      palette: `<svg ${common}>
        <path d="M12 3a9 9 0 0 0 0 18h1.2a1.8 1.8 0 0 0 1.4-2.9l-.5-.6a1.6 1.6 0 0 1 1.2-2.6H17a4 4 0 0 0 4-4A8 8 0 0 0 12 3Z" stroke="currentColor" stroke-width="1.7"/>
        <circle cx="7.5" cy="10" r="1" fill="currentColor"/>
        <circle cx="10" cy="6.8" r="1" fill="currentColor"/>
        <circle cx="14" cy="6.5" r="1" fill="currentColor"/>
      </svg>`,

      sparkles: `<svg ${common}>
        <path d="M12 3l1.6 4.2L18 9l-4.4 1.7L12 15l-1.6-4.3L6 9l4.4-1.8L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
        <path d="m18.2 14 .7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
      </svg>`,

      download: `<svg ${common}>
        <path d="M12 3v11m0 0 4-4m-4 4-4-4M5 18v2h14v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`,

      open: `<svg ${common}>
        <path d="M14 4h6v6M20 4l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>`,

      alert: `<svg ${common}>
        <path d="M12 3.5 21 20H3L12 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M12 9v4.6M12 17h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
      </svg>`,
    };

    return icons[name] || icons.image;
  };

  const ensureStyles = () => {
    if (document.getElementById('garad-image-ui-v65-style')) return;

    const style = document.createElement('style');
    style.id = 'garad-image-ui-v65-style';

    style.textContent = `
      .garad-image-v65-card {
        width: min(100%, 560px);
        max-width: 560px;
        margin: 12px 0 18px;
        padding: 13px;
        border: 1px solid rgba(103,76,255,.18);
        border-radius: 16px;
        background: linear-gradient(
          180deg,
          rgba(255,255,255,.98),
          rgba(248,247,255,.98)
        );
        box-shadow: 0 8px 26px rgba(27,22,66,.055);
        overflow: hidden;
      }

      html.dark .garad-image-v65-card,
      .dark .garad-image-v65-card {
        background: linear-gradient(
          180deg,
          rgba(23,24,30,.98),
          rgba(28,29,39,.98)
        );
        border-color: rgba(132,112,255,.22);
      }

      .garad-image-v65-head {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .garad-image-v65-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        color: #684cff;
        background: rgba(104,76,255,.11);
      }

      .garad-image-v65-icon svg {
        width: 17px;
        height: 17px;
      }

      .garad-image-v65-icon.is-loading svg {
        animation: garad-image-v65-spin 1s linear infinite;
      }

      .garad-image-v65-icon.is-ready {
        color: #16a34a;
        background: rgba(22,163,74,.11);
      }

      .garad-image-v65-icon.is-error {
        color: #dc2626;
        background: rgba(220,38,38,.1);
      }

      .garad-image-v65-title {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.25;
      }

      .garad-image-v65-subtitle {
        margin-top: 2px;
        font-size: 12px;
        line-height: 1.4;
        opacity: .7;
      }

      .garad-image-v65-preview {
        position: relative;
        height: 158px;
        margin-top: 11px;
        overflow: hidden;
        border-radius: 13px;
        background:
          radial-gradient(
            circle at 20% 20%,
            rgba(118,90,255,.22),
            transparent 36%
          ),
          radial-gradient(
            circle at 80% 24%,
            rgba(0,190,255,.16),
            transparent 34%
          ),
          linear-gradient(
            135deg,
            #171927,
            #282e49
          );
      }

      .garad-image-v65-preview:before {
        content: "";
        position: absolute;
        inset: 0;
        transform: translateX(-100%);
        background: linear-gradient(
          105deg,
          transparent,
          rgba(255,255,255,.09),
          transparent
        );
        animation: garad-image-v65-shimmer 2s linear infinite;
      }

      .garad-image-v65-preview-grid {
        position: absolute;
        inset: 14px;
        display: grid;
        grid-template-columns: 1.2fr .8fr;
        gap: 10px;
      }

      .garad-image-v65-block {
        border-radius: 10px;
        background: rgba(255,255,255,.09);
        overflow: hidden;
        position: relative;
      }

      .garad-image-v65-stack {
        display: grid;
        gap: 10px;
      }

      .garad-image-v65-progress-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 11px;
        margin-bottom: 6px;
        font-size: 11.5px;
        font-weight: 600;
      }

      .garad-image-v65-progress {
        height: 6px;
        border-radius: 999px;
        overflow: hidden;
        background: rgba(127,127,127,.14);
      }

      .garad-image-v65-progress-fill {
        width: 12%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(
          90deg,
          #684cff,
          #00baf5
        );
        transition: width .45s ease;
      }

      .garad-image-v65-stages {
        display: grid;
        gap: 6px;
        margin-top: 11px;
      }

      .garad-image-v65-stage {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 22px;
        font-size: 11.5px;
        opacity: .52;
      }

      .garad-image-v65-stage.active {
        opacity: 1;
        font-weight: 600;
      }

      .garad-image-v65-stage.done {
        opacity: .85;
      }

      .garad-image-v65-stage-icon {
        width: 19px;
        height: 19px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(127,127,127,.1);
      }

      .garad-image-v65-stage-icon svg {
        width: 11px;
        height: 11px;
      }

      .garad-image-v65-stage.active
      .garad-image-v65-stage-icon {
        color: #684cff;
        background: rgba(104,76,255,.12);
      }

      .garad-image-v65-stage.done
      .garad-image-v65-stage-icon {
        color: #16a34a;
        background: rgba(22,163,74,.12);
      }

      .garad-image-v65-result {
        margin-top: 11px;
      }

      .garad-image-v65-result img {
        display: block;
        width: 100%;
        max-height: 430px;
        object-fit: contain;
        border-radius: 13px;
        background: rgba(127,127,127,.06);
      }

      .garad-image-v65-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 9px;
      }

      .garad-image-v65-action {
        min-height: 32px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 9px;
        border: 1px solid rgba(127,127,127,.2);
        border-radius: 9px;
        text-decoration: none;
        font-size: 11.5px;
      }

      .garad-image-v65-action svg {
        width: 14px;
        height: 14px;
      }

      .garad-image-v65-card.is-ready
      .garad-image-v65-preview,
      .garad-image-v65-card.is-error
      .garad-image-v65-preview {
        display: none;
      }

      @keyframes garad-image-v65-spin {
        to {
          transform: rotate(360deg);
        }
      }

      @keyframes garad-image-v65-shimmer {
        to {
          transform: translateX(100%);
        }
      }

      @media (prefers-reduced-motion: reduce) {
        .garad-image-v65-icon.is-loading svg,
        .garad-image-v65-preview:before {
          animation: none !important;
        }
      }

      @media (max-width: 640px) {
        .garad-image-v65-card {
          width: calc(100% - 8px);
          max-width: none;
          padding: 10px;
        }

        .garad-image-v65-preview {
          height: 135px;
        }

        .garad-image-v65-result img {
          max-height: 320px;
        }
      }
    `;

    document.head.appendChild(style);
  };

  const hasBrandVoice = () => {
    const el = document.querySelector(
      '[name="chat_brand_voice"],#chat_brand_voice'
    );

    return !!(
      el &&
      String(el.value || '').trim() &&
      String(el.value) !== '0'
    );
  };

  const selectedTool = () => {
    const hidden = document.getElementById(
      'garad_selected_tool'
    );

    return String(
      hidden?.value ||
      window.garadActiveRequestTool ||
      ''
    ).toLowerCase();
  };

  const currentPrompt = () =>
    String(
      document.getElementById('prompt')?.value ||
      ''
    ).trim();

  const isImageIntent = (
    prompt = currentPrompt()
  ) => {
    if (selectedTool() === 'image') {
      return true;
    }

    if (/^@image\b/i.test(prompt)) {
      return true;
    }

    return /\b(create|generate|make|design|samee|sameey|sawir)\b.{0,70}\b(image|picture|poster|visual|sawir|sawirka)\b/iu.test(
      prompt
    );
  };

  const aiCount = () =>
    document.querySelectorAll(
      '.lqd-chat-ai-bubble,[data-message-role="assistant"],.lqd-chat-message-ai'
    ).length;

  const latestUserAnchor = () => {
    const selectors = [
      '.lqd-chat-user-bubble',
      '[data-message-role="user"]',
      '.lqd-chat-message-user',
      '.chat-user',
      '.lqd-chat-human-bubble',
    ];

    for (const selector of selectors) {
      const nodes = [
        ...document.querySelectorAll(
          selector
        )
      ];

      if (nodes.length) {
        return nodes.at(-1);
      }
    }

    return null;
  };

  const fallbackInsertTarget = () => {
    const formWrap =
      document.querySelector(
        '.lqd-chat-form-wrap'
      );

    if (
      formWrap?.parentElement
    ) {
      return {
        parent:
          formWrap.parentElement,

        before:
          formWrap,
      };
    }

    const form =
      document.getElementById(
        'chat_form'
      );

    if (
      form?.parentElement
    ) {
      return {
        parent:
          form.parentElement,

        before:
          form,
      };
    }

    return null;
  };

  const createLoadingCard = (
    prompt
  ) => {
    const card =
      document.createElement(
        'div'
      );

    card.className =
      'garad-image-v65-card';

    card.dataset.garadImagePending =
      `${Date.now()}-${Math.random()
        .toString(16)
        .slice(2)}`;

    const branded =
      hasBrandVoice();

    card.innerHTML = `
      <div class="garad-image-v65-head">
        <span class="garad-image-v65-icon is-loading">
          ${svg('spinner')}
        </span>

        <div>
          <div class="garad-image-v65-title">
            Creating your image
          </div>

          <div class="garad-image-v65-subtitle">
            Garad AI is preparing the visual you requested.
          </div>
        </div>
      </div>

      <div class="garad-image-v65-preview">
        <div class="garad-image-v65-preview-grid">
          <div class="garad-image-v65-block"></div>

          <div class="garad-image-v65-stack">
            <div class="garad-image-v65-block"></div>
            <div class="garad-image-v65-block"></div>
          </div>
        </div>
      </div>

      <div class="garad-image-v65-progress-head">
        <span class="garad-image-v65-progress-label">
          Understanding your request…
        </span>

        <span class="garad-image-v65-percent">
          12%
        </span>
      </div>

      <div class="garad-image-v65-progress">
        <div class="garad-image-v65-progress-fill"></div>
      </div>

      <div class="garad-image-v65-stages">

        <div class="garad-image-v65-stage active">
          <span class="garad-image-v65-stage-icon">
            ${svg('sparkles')}
          </span>
          <span>Understanding your request</span>
        </div>

        <div class="garad-image-v65-stage">
          <span class="garad-image-v65-stage-icon">
            ${svg('palette')}
          </span>
          <span>
            ${
              branded
                ? 'Applying brand identity'
                : 'Planning the visual direction'
            }
          </span>
        </div>

        <div class="garad-image-v65-stage">
          <span class="garad-image-v65-stage-icon">
            ${svg('image')}
          </span>
          <span>Generating the image</span>
        </div>

        <div class="garad-image-v65-stage">
          <span class="garad-image-v65-stage-icon">
            ${svg('sparkles')}
          </span>
          <span>Finalizing the image</span>
        </div>

      </div>

      <div class="garad-image-v65-result"></div>
    `;

    const pending = {
      card,
      prompt,
      baselineAiCount:
        aiCount(),
      attempt: 0,
      timer: null,
      completed: false,
    };

    STATE.pending.push(
      pending
    );

    let tries = 0;

    const place = () => {
      tries += 1;

      if (
        card.isConnected
      ) {
        return;
      }

      const anchor =
        latestUserAnchor();

      if (anchor) {
        anchor.insertAdjacentElement(
          'afterend',
          card
        );

        return;
      }

      const fallback =
        fallbackInsertTarget();

      if (fallback) {
        fallback.parent.insertBefore(
          card,
          fallback.before
        );

        return;
      }

      if (tries < 20) {
        setTimeout(
          place,
          100
        );
      }
    };

    setTimeout(
      place,
      50
    );

    const tick = () => {
      if (
        pending.completed
      ) {
        return;
      }

      pending.attempt += 1;

      updateProgress(
        pending
      );

      pending.timer =
        setTimeout(
          tick,
          3500
        );
    };

    pending.timer =
      setTimeout(
        tick,
        1800
      );

    return pending;
  };

  const updateProgress = (
    pending
  ) => {
    const steps = [
      {
        max: 1,
        percent: 18,
        stage: 0,
        text:
          'Understanding your request…',
      },

      {
        max: 2,
        percent: 36,
        stage: 1,
        text:
          hasBrandVoice()
            ? 'Applying your brand identity…'
            : 'Planning the visual direction…',
      },

      {
        max: 5,
        percent: 63,
        stage: 2,
        text:
          'Generating your image…',
      },

      {
        max: 8,
        percent: 82,
        stage: 3,
        text:
          'Finalizing the image…',
      },

      {
        max: Infinity,
        percent: 92,
        stage: 3,
        text:
          'Your image is almost ready…',
      },
    ];

    const current =
      steps.find(
        (s) =>
          pending.attempt <= s.max
      )
      || steps.at(-1);

    const card =
      pending.card;

    card
      .querySelector(
        '.garad-image-v65-progress-fill'
      )
      ?.style.setProperty(
        'width',
        `${current.percent}%`
      );

    const p =
      card.querySelector(
        '.garad-image-v65-percent'
      );

    const l =
      card.querySelector(
        '.garad-image-v65-progress-label'
      );

    if (p) {
      p.textContent =
        `${current.percent}%`;
    }

    if (l) {
      l.textContent =
        current.text;
    }

    const stages = [
      ...card.querySelectorAll(
        '.garad-image-v65-stage'
      ),
    ];

    const names = [
      'sparkles',
      'palette',
      'image',
      'sparkles',
    ];

    stages.forEach(
      (
        stage,
        index
      ) => {
        stage.classList.remove(
          'active',
          'done'
        );

        const icon =
          stage.querySelector(
            '.garad-image-v65-stage-icon'
          );

        if (
          index <
          current.stage
        ) {
          stage.classList.add(
            'done'
          );

          if (icon) {
            icon.innerHTML =
              svg('check');
          }

        } else if (
          index ===
          current.stage
        ) {
          stage.classList.add(
            'active'
          );

          if (icon) {
            icon.innerHTML =
              svg(
                names[index]
              );
          }

        } else if (icon) {
          icon.innerHTML =
            svg(
              names[index]
            );
        }
      }
    );
  };

  const activePending = () =>
    STATE.pending.find(
      (item) =>
        !item.completed
    )
    || null;

  const startPendingIfNeeded = (
    source
  ) => {
    const prompt =
      currentPrompt();

    if (
      !prompt ||
      !isImageIntent(
        prompt
      )
    ) {
      return;
    }

    const now =
      Date.now();

    if (
      now -
        STATE.lastStartAt <
        1200
      &&
      prompt ===
        STATE.lastPrompt
    ) {
      return;
    }

    STATE.lastStartAt =
      now;

    STATE.lastPrompt =
      prompt;

    createLoadingCard(
      prompt
    );
  };

  const decodeBase64Url = (
    value
  ) => {
    try {
      let s =
        String(
          value ||
          ''
        )
          .replace(
            /-/g,
            '+'
          )
          .replace(
            /_/g,
            '/'
          );

      while (
        s.length % 4
      ) {
        s += '=';
      }

      return decodeURIComponent(
        escape(
          atob(s)
        )
      );

    } catch (_) {
      try {
        let s =
          String(
            value ||
            ''
          )
            .replace(
              /-/g,
              '+'
            )
            .replace(
              /_/g,
              '/'
            );

        while (
          s.length % 4
        ) {
          s += '=';
        }

        return atob(
          s
        );

      } catch (_) {
        return null;
      }
    }
  };

  const safeImageUrl = (
    raw
  ) => {
    if (!raw) {
      return null;
    }

    try {
      const url =
        new URL(
          raw,
          location.origin
        );

      if (
        ![
          'http:',
          'https:',
        ].includes(
          url.protocol
        )
      ) {
        return null;
      }

      return url.href;

    } catch (_) {
      return null;
    }
  };

  const completePending = (
    pending,
    url,
    sourceElement = null
  ) => {
    if (
      !pending ||
      pending.completed
    ) {
      return;
    }

    pending.completed =
      true;

    clearTimeout(
      pending.timer
    );

    const card =
      pending.card;

    card.classList.add(
      'is-ready'
    );

    const icon =
      card.querySelector(
        '.garad-image-v65-icon'
      );

    if (icon) {
      icon.classList.remove(
        'is-loading'
      );

      icon.classList.add(
        'is-ready'
      );

      icon.innerHTML =
        svg('check');
    }

    const title =
      card.querySelector(
        '.garad-image-v65-title'
      );

    const subtitle =
      card.querySelector(
        '.garad-image-v65-subtitle'
      );

    const label =
      card.querySelector(
        '.garad-image-v65-progress-label'
      );

    const percent =
      card.querySelector(
        '.garad-image-v65-percent'
      );

    const fill =
      card.querySelector(
        '.garad-image-v65-progress-fill'
      );

    if (title) {
      title.textContent =
        'Image ready';
    }

    if (subtitle) {
      subtitle.textContent =
        'Your image has been created successfully.';
    }

    if (label) {
      label.textContent =
        'Completed';
    }

    if (percent) {
      percent.textContent =
        '100%';
    }

    if (fill) {
      fill.style.width =
        '100%';
    }

    card
      .querySelectorAll(
        '.garad-image-v65-stage'
      )
      .forEach(
        (stage) => {
          stage.classList.remove(
            'active'
          );

          stage.classList.add(
            'done'
          );

          const i =
            stage.querySelector(
              '.garad-image-v65-stage-icon'
            );

          if (i) {
            i.innerHTML =
              svg('check');
          }
        }
      );

    const result =
      card.querySelector(
        '.garad-image-v65-result'
      );

    if (!result) {
      return;
    }

    result.innerHTML = '';

    const image =
      document.createElement(
        'img'
      );

    image.alt =
      'Generated image';

    image.loading =
      'eager';

    image.decoding =
      'async';

    const actions =
      document.createElement(
        'div'
      );

    actions.className =
      'garad-image-v65-actions';

    const open =
      document.createElement(
        'a'
      );

    open.className =
      'garad-image-v65-action';

    open.href =
      url;

    open.target =
      '_blank';

    open.rel =
      'noopener noreferrer';

    open.innerHTML =
      `${svg('open')}<span>Open</span>`;

    const download =
      document.createElement(
        'a'
      );

    download.className =
      'garad-image-v65-action';

    download.href =
      url;

    download.target =
      '_blank';

    download.rel =
      'noopener noreferrer';

    download.innerHTML =
      `${svg('download')}<span>Download</span>`;

    try {
      const parsed =
        new URL(url);

      if (
        parsed.origin ===
        location.origin
      ) {
        download.removeAttribute(
          'target'
        );

        download.setAttribute(
          'download',
          ''
        );
      }
    } catch (_) {}

    let retried = false;

    image.addEventListener(
      'load',
      () => {
        if (
          sourceElement
        ) {
          sourceElement.style.display =
            'none';
        }
      },
      {
        once: true,
      }
    );

    image.addEventListener(
      'error',
      () => {
        if (!retried) {
          retried = true;

          const retry =
            new URL(
              url,
              location.origin
            );

          retry.searchParams.set(
            '_garad',
            String(
              Date.now()
            )
          );

          image.src =
            retry.href;

          return;
        }

        failPending(
          pending,
          'The generated file could not be displayed.'
        );

        if (
          sourceElement
        ) {
          sourceElement.style.display =
            '';
        }
      }
    );

    result.appendChild(
      image
    );

    actions.appendChild(
      open
    );

    actions.appendChild(
      download
    );

    result.appendChild(
      actions
    );

    image.src =
      url;
  };

  const failPending = (
    pending,
    message
  ) => {
    if (
      !pending ||
      pending.completed
    ) {
      return;
    }

    pending.completed =
      true;

    clearTimeout(
      pending.timer
    );

    const card =
      pending.card;

    card.classList.add(
      'is-error'
    );

    const icon =
      card.querySelector(
        '.garad-image-v65-icon'
      );

    if (icon) {
      icon.classList.remove(
        'is-loading'
      );

      icon.classList.add(
        'is-error'
      );

      icon.innerHTML =
        svg('alert');
    }

    const title =
      card.querySelector(
        '.garad-image-v65-title'
      );

    if (title) {
      title.textContent =
        'Image could not be completed';
    }

    const subtitle =
      card.querySelector(
        '.garad-image-v65-subtitle'
      );

    if (subtitle) {
      subtitle.textContent =
        message ||
        'Please try again.';
    }

    const label =
      card.querySelector(
        '.garad-image-v65-progress-label'
      );

    if (label) {
      label.textContent =
        'Generation stopped';
    }

    const percent =
      card.querySelector(
        '.garad-image-v65-percent'
      );

    if (percent) {
      percent.textContent =
        '';
    }
  };

  const nearestMessageContainer = (
    node
  ) => {
    let el =
      node?.parentElement ||
      null;

    if (!el) {
      return null;
    }

    const selectors = [
      '.lqd-chat-ai-bubble',
      '[data-message-role="assistant"]',
      '.lqd-chat-message-ai',
      '.lqd-chat-message',
      '.prose',
    ];

    for (
      const selector
      of selectors
    ) {
      const found =
        el.closest(
          selector
        );

      if (found) {
        return found;
      }
    }

    for (
      let i = 0;
      i < 5 &&
      el?.parentElement;
      i += 1
    ) {
      el =
        el.parentElement;
    }

    return el;
  };

  const consumeResultMarkers = () => {
    const re =
      /\[\[GARAD_IMAGE_RESULT:([A-Za-z0-9_-]+)\]\]/i;

    const walker =
      document.createTreeWalker(
        document.body,
        NodeFilter.SHOW_TEXT
      );

    const found = [];

    while (
      walker.nextNode()
    ) {
      const node =
        walker.currentNode;

      const match =
        String(
          node.nodeValue ||
          ''
        ).match(
          re
        );

      if (match) {
        found.push({
          node,
          encoded:
            match[1],
        });
      }
    }

    found.forEach(
      ({
        node,
        encoded,
      }) => {
        const decoded =
          decodeBase64Url(
            encoded
          );

        const url =
          safeImageUrl(
            decoded
          );

        const container =
          nearestMessageContainer(
            node
          );

        node.nodeValue =
          String(
            node.nodeValue ||
            ''
          ).replace(
            re,
            ''
          );

        if (!url) {
          return;
        }

        let pending =
          activePending();

        if (!pending) {
          pending =
            createLoadingCard(
              ''
            );
        }

        completePending(
          pending,
          url,
          container
        );
      }
    );
  };

  const consumeLegacyResult = () => {
    const pending =
      activePending();

    if (!pending) {
      return;
    }

    const selectors = [
      '.lqd-chat-ai-bubble',
      '[data-message-role="assistant"]',
      '.lqd-chat-message-ai',
    ];

    const bubbles = [
      ...document.querySelectorAll(
        selectors.join(',')
      ),
    ];

    const candidates =
      bubbles.slice(
        Math.max(
          0,
          pending.baselineAiCount
        )
      );

    for (
      const bubble
      of candidates
    ) {
      const text =
        String(
          bubble.textContent ||
          ''
        ).trim();

      if (
        /image generation failed|could not be generated|no credits|daily image generation limit/i.test(
          text
        )
      ) {
        failPending(
          pending,
          'The image request could not be completed. Please try again.'
        );

        return;
      }

      const img =
        bubble.querySelector(
          'img[src]'
        );

      let url =
        img
          ? safeImageUrl(
              img.getAttribute(
                'src'
              )
            )
          : null;

      if (!url) {
        for (
          const link
          of [
            ...bubble.querySelectorAll(
              'a[href]'
            ),
          ]
        ) {
          const href =
            safeImageUrl(
              link.getAttribute(
                'href'
              )
            );

          if (
            href &&
            (
              /\.(png|jpe?g|webp|gif)(?:$|[?#])/i.test(
                href
              )
              ||
              /\/uploads\//i.test(
                href
              )
            )
          ) {
            url =
              href;

            break;
          }
        }
      }

      if (
        url &&
        (
          /image created successfully/i.test(
            text
          )
          ||
          img
        )
      ) {
        completePending(
          pending,
          url,
          bubble
        );

        return;
      }
    }
  };

  const hookSendActions = () => {
    const send =
      document.getElementById(
        'send_message_button'
      );

    if (
      send &&
      send.dataset.garadImageV65Hooked !==
        '1'
    ) {
      send.dataset.garadImageV65Hooked =
        '1';

      send.addEventListener(
        'click',
        () =>
          startPendingIfNeeded(
            'click'
          ),
        true
      );
    }

    const prompt =
      document.getElementById(
        'prompt'
      );

    if (
      prompt &&
      prompt.dataset.garadImageV65Hooked !==
        '1'
    ) {
      prompt.dataset.garadImageV65Hooked =
        '1';

      prompt.addEventListener(
        'keydown',
        (event) => {
          if (
            event.key ===
              'Enter'
            &&
            !event.shiftKey
            &&
            !event.isComposing
          ) {
            startPendingIfNeeded(
              'enter'
            );
          }
        },
        true
      );
    }
  };

  let queued = false;

  const scan = () => {
    hookSendActions();
    consumeResultMarkers();
    consumeLegacyResult();
  };

  const queueScan = () => {
    if (queued) {
      return;
    }

    queued = true;

    requestAnimationFrame(
      () => {
        queued = false;
        scan();
      }
    );
  };

  const boot = () => {
    ensureStyles();
    scan();

    STATE.observer =
      new MutationObserver(
        queueScan
      );

    STATE.observer.observe(
      document.body,
      {
        childList: true,
        subtree: true,
        characterData: true,
      }
    );
  };

  if (
    document.readyState ===
    'loading'
  ) {
    document.addEventListener(
      'DOMContentLoaded',
      boot,
      {
        once: true,
      }
    );
  } else {
    boot();
  }
})();