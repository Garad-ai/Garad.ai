/* GARAD Chat Pro Image UI V66 - production repair 2026-08-20 */
(() => {
  'use strict';

  if (window.__GARAD_CHATPRO_IMAGE_UI_V66__) return;
  window.__GARAD_CHATPRO_IMAGE_UI_V66__ = true;

  const STATE = {
    pending: [],
    observer: null,
    themeObserver: null,
    lastStartAt: 0,
    lastPrompt: '',
    queued: false,
  };

  const svg = (name) => {
    const common =
      'viewBox="0 0 24 24" fill="none" aria-hidden="true"';

    const icons = {
      spinner: `
        <svg ${common}>
          <circle
            cx="12"
            cy="12"
            r="8.5"
            stroke="currentColor"
            stroke-width="2"
            opacity=".2"
          />
          <path
            d="M20.5 12A8.5 8.5 0 0 0 12 3.5"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
          />
        </svg>
      `,

      check: `
        <svg ${common}>
          <circle
            cx="12"
            cy="12"
            r="9"
            stroke="currentColor"
            stroke-width="1.8"
          />
          <path
            d="m7.8 12.2 2.8 2.7 5.8-6"
            stroke="currentColor"
            stroke-width="1.9"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      `,

      image: `
        <svg ${common}>
          <rect
            x="3"
            y="4"
            width="18"
            height="16"
            rx="3"
            stroke="currentColor"
            stroke-width="1.8"
          />
          <circle
            cx="8.2"
            cy="9"
            r="1.5"
            stroke="currentColor"
            stroke-width="1.5"
          />
          <path
            d="m5 17 4.4-4.2 3.1 2.8 2.5-2.4L19 17"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      `,

      palette: `
        <svg ${common}>
          <path
            d="M12 3a9 9 0 0 0 0 18h1.2a1.8 1.8 0 0 0 1.4-2.9l-.5-.6a1.6 1.6 0 0 1 1.2-2.6H17a4 4 0 0 0 4-4A8 8 0 0 0 12 3Z"
            stroke="currentColor"
            stroke-width="1.7"
          />
          <circle
            cx="7.5"
            cy="10"
            r="1"
            fill="currentColor"
          />
          <circle
            cx="10"
            cy="6.8"
            r="1"
            fill="currentColor"
          />
          <circle
            cx="14"
            cy="6.5"
            r="1"
            fill="currentColor"
          />
        </svg>
      `,

      sparkles: `
        <svg ${common}>
          <path
            d="M12 3l1.6 4.2L18 9l-4.4 1.7L12 15l-1.6-4.3L6 9l4.4-1.8L12 3Z"
            stroke="currentColor"
            stroke-width="1.7"
            stroke-linejoin="round"
          />
        </svg>
      `,

      download: `
        <svg ${common}>
          <path
            d="M12 3v11m0 0 4-4m-4 4-4-4M5 18v2h14v-2"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      `,

      open: `
        <svg ${common}>
          <path
            d="M14 4h6v6M20 4l-9 9"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
          />
          <path
            d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
          />
        </svg>
      `,

      alert: `
        <svg ${common}>
          <path
            d="M12 3.5 21 20H3L12 3.5Z"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linejoin="round"
          />
          <path
            d="M12 9v4.6M12 17h.01"
            stroke="currentColor"
            stroke-width="1.9"
            stroke-linecap="round"
          />
        </svg>
      `,
    };

    return icons[name] || icons.image;
  };

  const parseRgb = (value) => {
    const match =
      String(value || '')
        .match(
          /rgba?\((\d+)[,\s]+(\d+)[,\s]+(\d+)/i
        );

    return match
      ? [
          Number(match[1]),
          Number(match[2]),
          Number(match[3]),
        ]
      : null;
  };

  const luminance = (rgb) => {
    if (!rgb) return 255;

    return (
      (0.2126 * rgb[0])
      +
      (0.7152 * rgb[1])
      +
      (0.0722 * rgb[2])
    );
  };

  const detectDarkMode = () => {
    const root =
      document.documentElement;

    const body =
      document.body;

    const token = [
      root?.className || '',
      body?.className || '',

      root?.getAttribute(
        'data-theme'
      ) || '',

      body?.getAttribute(
        'data-theme'
      ) || '',

      root?.getAttribute(
        'data-mode'
      ) || '',

      body?.getAttribute(
        'data-mode'
      ) || '',

      root?.getAttribute(
        'data-bs-theme'
      ) || '',

      body?.getAttribute(
        'data-bs-theme'
      ) || '',
    ]
      .join(' ')
      .toLowerCase();

    if (
      /\b(dark|night|theme-dark|dark-mode)\b/
        .test(token)
    ) {
      return true;
    }

    if (
      /\b(light|theme-light|light-mode)\b/
        .test(token)
    ) {
      return false;
    }

    const candidates = [
      body,
      document.querySelector('main'),
      document.querySelector('.lqd-page'),
      document.querySelector(
        '.lqd-dashboard-wrap'
      ),
      root,
    ].filter(Boolean);

    for (
      const element
      of candidates
    ) {
      const background =
        getComputedStyle(
          element
        ).backgroundColor;

      const rgb =
        parseRgb(
          background
        );

      if (
        rgb
        &&
        luminance(rgb) < 110
      ) {
        return true;
      }
    }

    return !!window
      .matchMedia?.(
        '(prefers-color-scheme: dark)'
      )
      .matches;
  };

  const syncTheme = () => {
    document
      .documentElement
      .dataset
      .garadUiTheme =
        detectDarkMode()
          ? 'dark'
          : 'light';
  };

  const installThemeWatcher = () => {
    syncTheme();

    if (
      STATE.themeObserver
    ) {
      return;
    }

    STATE.themeObserver =
      new MutationObserver(
        () => {
          requestAnimationFrame(
            syncTheme
          );
        }
      );

    STATE.themeObserver.observe(
      document.documentElement,
      {
        attributes: true,

        attributeFilter: [
          'class',
          'style',
          'data-theme',
          'data-mode',
          'data-bs-theme',
        ],
      }
    );

    if (document.body) {
      STATE.themeObserver.observe(
        document.body,
        {
          attributes: true,

          attributeFilter: [
            'class',
            'style',
            'data-theme',
            'data-mode',
            'data-bs-theme',
          ],
        }
      );
    }

    try {
      const media =
        window.matchMedia(
          '(prefers-color-scheme: dark)'
        );

      media.addEventListener?.(
        'change',
        syncTheme
      );
    } catch (_) {}
  };

  const ensureStyles = () => {
    if (
      document.getElementById(
        'garad-image-ui-v66-style'
      )
    ) {
      return;
    }

    const style =
      document.createElement(
        'style'
      );

    style.id =
      'garad-image-ui-v66-style';

    style.textContent = `
      .garad-image-v66-card {
        --g-card-bg: rgba(255,255,255,.98);
        --g-card-bg-2: rgba(248,247,255,.98);
        --g-card-border: rgba(103,76,255,.20);
        --g-text: #17171c;
        --g-muted: rgba(23,23,28,.62);
        --g-soft: rgba(127,127,127,.10);

        width: min(calc(100% - 24px), 500px);
        max-width: 500px;
        margin: 14px auto 18px;
        padding: 13px;

        color: var(--g-text);

        border:
          1px solid
          var(--g-card-border);

        border-radius: 16px;

        background:
          linear-gradient(
            180deg,
            var(--g-card-bg),
            var(--g-card-bg-2)
          );

        box-shadow:
          0 10px 30px
          rgba(24,20,55,.07);

        overflow: hidden;
        box-sizing: border-box;
      }

      html[data-garad-ui-theme="dark"]
      .garad-image-v66-card {
        --g-card-bg:
          rgba(24,24,29,.98);

        --g-card-bg-2:
          rgba(18,18,22,.98);

        --g-card-border:
          rgba(129,105,255,.34);

        --g-text:
          rgba(255,255,255,.94);

        --g-muted:
          rgba(255,255,255,.58);

        --g-soft:
          rgba(255,255,255,.075);

        box-shadow:
          0 10px 34px
          rgba(0,0,0,.28);
      }

      .garad-image-v66-head {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .garad-image-v66-icon {
        width: 34px;
        height: 34px;

        display:
          inline-flex;

        align-items:
          center;

        justify-content:
          center;

        flex:
          0 0 auto;

        border-radius:
          999px;

        color:
          #684cff;

        background:
          rgba(
            104,
            76,
            255,
            .12
          );
      }

      .garad-image-v66-icon svg {
        width: 17px;
        height: 17px;
      }

      .garad-image-v66-icon.is-loading svg {
        animation:
          garad-image-v66-spin
          1s
          linear
          infinite;
      }

      .garad-image-v66-icon.is-ready {
        color:
          #16a34a;

        background:
          rgba(
            22,
            163,
            74,
            .12
          );
      }

      .garad-image-v66-icon.is-error {
        color:
          #dc2626;

        background:
          rgba(
            220,
            38,
            38,
            .12
          );
      }

      .garad-image-v66-title {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.25;
        color: var(--g-text);
      }

      .garad-image-v66-subtitle {
        margin-top: 2px;
        font-size: 12px;
        line-height: 1.4;
        color: var(--g-muted);
      }

      .garad-image-v66-preview {
        position: relative;

        height: 150px;

        margin-top:
          11px;

        overflow:
          hidden;

        border-radius:
          13px;

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
            #11141f,
            #25314c
          );
      }

      .garad-image-v66-preview:before {
        content: "";

        position:
          absolute;

        inset: 0;

        transform:
          translateX(-100%);

        background:
          linear-gradient(
            105deg,
            transparent,
            rgba(255,255,255,.10),
            transparent
          );

        animation:
          garad-image-v66-shimmer
          2s
          linear
          infinite;
      }

      .garad-image-v66-preview-grid {
        position:
          absolute;

        inset:
          13px;

        display:
          grid;

        grid-template-columns:
          1.2fr .8fr;

        gap:
          9px;
      }

      .garad-image-v66-block {
        border-radius:
          10px;

        background:
          rgba(
            255,
            255,
            255,
            .09
          );
      }

      .garad-image-v66-stack {
        display:
          grid;

        gap:
          9px;
      }

      .garad-image-v66-progress-head {
        display:
          flex;

        align-items:
          center;

        justify-content:
          space-between;

        gap:
          12px;

        margin-top:
          11px;

        margin-bottom:
          6px;

        font-size:
          11.5px;

        font-weight:
          600;

        color:
          var(--g-text);
      }

      .garad-image-v66-progress {
        height:
          6px;

        overflow:
          hidden;

        border-radius:
          999px;

        background:
          var(--g-soft);
      }

      .garad-image-v66-progress-fill {
        width:
          12%;

        height:
          100%;

        border-radius:
          inherit;

        background:
          linear-gradient(
            90deg,
            #684cff,
            #00baf5
          );

        transition:
          width
          .45s
          ease;
      }

      .garad-image-v66-stages {
        display:
          grid;

        gap:
          6px;

        margin-top:
          11px;
      }

      .garad-image-v66-stage {
        display:
          flex;

        align-items:
          center;

        gap:
          8px;

        min-height:
          22px;

        font-size:
          11.5px;

        color:
          var(--g-muted);

        opacity:
          .70;
      }

      .garad-image-v66-stage.active {
        color:
          var(--g-text);

        opacity:
          1;

        font-weight:
          650;
      }

      .garad-image-v66-stage.done {
        opacity:
          .88;
      }

      .garad-image-v66-stage-icon {
        width:
          19px;

        height:
          19px;

        display:
          inline-flex;

        align-items:
          center;

        justify-content:
          center;

        border-radius:
          999px;

        background:
          var(--g-soft);
      }

      .garad-image-v66-stage-icon svg {
        width:
          11px;

        height:
          11px;
      }

      .garad-image-v66-stage.active
      .garad-image-v66-stage-icon {
        color:
          #684cff;

        background:
          rgba(
            104,
            76,
            255,
            .13
          );
      }

      .garad-image-v66-stage.done
      .garad-image-v66-stage-icon {
        color:
          #16a34a;

        background:
          rgba(
            22,
            163,
            74,
            .13
          );
      }

      .garad-image-v66-result {
        margin-top:
          11px;
      }

      .garad-image-v66-result img {
        display:
          block;

        width:
          100%;

        max-height:
          390px;

        object-fit:
          contain;

        border-radius:
          13px;

        background:
          var(--g-soft);
      }

      .garad-image-v66-actions {
        display:
          flex;

        flex-wrap:
          wrap;

        gap:
          7px;

        margin-top:
          9px;
      }

      .garad-image-v66-action {
        min-height:
          32px;

        display:
          inline-flex;

        align-items:
          center;

        gap:
          6px;

        padding:
          6px 10px;

        border:
          1px solid
          var(--g-card-border);

        border-radius:
          9px;

        color:
          var(--g-text);

        background:
          var(--g-soft);

        text-decoration:
          none !important;

        font-size:
          11.5px;
      }

      .garad-image-v66-action:hover {
        color:
          #684cff;

        border-color:
          rgba(
            104,
            76,
            255,
            .55
          );
      }

      .garad-image-v66-action svg {
        width:
          14px;

        height:
          14px;
      }

      .garad-image-v66-error-note {
        margin-top:
          10px;

        padding:
          9px 10px;

        border-radius:
          10px;

        color:
          #dc2626;

        background:
          rgba(
            220,
            38,
            38,
            .08
          );

        font-size:
          11.5px;

        line-height:
          1.45;
      }

      .garad-image-v66-card.is-ready
      .garad-image-v66-preview,

      .garad-image-v66-card.is-error
      .garad-image-v66-preview {
        display:
          none;
      }

      @keyframes
      garad-image-v66-spin {
        to {
          transform:
            rotate(360deg);
        }
      }

      @keyframes
      garad-image-v66-shimmer {
        to {
          transform:
            translateX(100%);
        }
      }

      @media
      (prefers-reduced-motion: reduce) {
        .garad-image-v66-icon.is-loading svg,
        .garad-image-v66-preview:before {
          animation:
            none !important;
        }
      }

      @media
      (max-width: 640px) {
        .garad-image-v66-card {
          width:
            calc(100% - 8px);

          max-width:
            none;

          padding:
            10px;
        }

        .garad-image-v66-preview {
          height:
            132px;
        }

        .garad-image-v66-result img {
          max-height:
            310px;
        }
      }
    `;

    document.head.appendChild(
      style
    );
  };

  const hasBrandVoice = () => {
    const element =
      document.querySelector(
        '[name="chat_brand_voice"],#chat_brand_voice'
      );

    return !!(
      element
      &&
      String(
        element.value || ''
      ).trim()
      &&
      String(
        element.value
      ) !== '0'
    );
  };

  const selectedTool = () => {
    return String(
      document
        .getElementById(
          'garad_selected_tool'
        )
        ?.value
      ||
      window.garadActiveRequestTool
      ||
      ''
    ).toLowerCase();
  };

  const currentPrompt = () => {
    return String(
      document
        .getElementById(
          'prompt'
        )
        ?.value
      ||
      ''
    ).trim();
  };

  const isImageIntent = (
    prompt =
      currentPrompt()
  ) => {
    if (
      selectedTool() ===
      'image'
    ) {
      return true;
    }

    if (
      /^@image\b/i
        .test(prompt)
    ) {
      return true;
    }

    return /\b(create|generate|make|design|samee|sameey|sawir)\b.{0,80}\b(image|picture|poster|visual|sawir|sawirka)\b/iu
      .test(prompt);
  };

  const latestUserAnchor = () => {
    const selectors = [
      '.lqd-chat-user-bubble',

      '[data-message-role="user"]',

      '.lqd-chat-message-user',

      '.chat-user',

      '.lqd-chat-human-bubble',
    ];

    for (
      const selector
      of selectors
    ) {
      const nodes = [
        ...document
          .querySelectorAll(
            selector
          ),
      ];

      if (
        nodes.length
      ) {
        return nodes.at(-1);
      }
    }

    return null;
  };

  const fallbackInsertTarget = () => {
    const wrapper =
      document.querySelector(
        '.lqd-chat-form-wrap'
      );

    if (
      wrapper?.parentElement
    ) {
      return {
        parent:
          wrapper.parentElement,

        before:
          wrapper,
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

  const aiCount = () => {
    return document
      .querySelectorAll(
        '.lqd-chat-ai-bubble,[data-message-role="assistant"],.lqd-chat-message-ai'
      )
      .length;
  };

  /*
   * Normalize and identify image-like URLs. The generated output may arrive
   * as an <img>, an <a>, raw Markdown, or a marker decoded to an R2 URL.
   */
  const imageLikeUrl = raw => {
    const url = safeImageUrl(raw);

    if (!url) {
      return null;
    }

    return (
      /\.(png|jpe?g|webp|gif)(?:$|[?#])/i.test(url)
      ||
      /https?:\/\/image\.garad\.ai\//i.test(url)
      ||
      /\/uploads\//i.test(url)
    )
      ? url
      : null;
  };

  /*
   * Remember all image URLs that existed before this request. That prevents
   * an uploaded logo, attachment preview, or an older generated image from
   * being mistaken for the result of the current request.
   */
  const snapshotImageUrls = () => {
    const urls = new Set();

    document
      .querySelectorAll('img[src],a[href]')
      .forEach(element => {
        const raw = element.tagName === 'IMG'
          ? element.getAttribute('src')
          : element.getAttribute('href');

        const url = imageLikeUrl(raw);

        if (url) {
          urls.add(url);
        }
      });

    return urls;
  };

  const setStage = (
    pending,
    attempt
  ) => {
    const branded =
      hasBrandVoice();

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
          branded
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
        item =>
          attempt <= item.max
      )
      ||
      steps.at(-1);

    const card =
      pending.card;

    card
      .querySelector(
        '.garad-image-v66-progress-fill'
      )
      ?.style
      .setProperty(
        'width',
        `${current.percent}%`
      );

    const percent =
      card.querySelector(
        '.garad-image-v66-percent'
      );

    const label =
      card.querySelector(
        '.garad-image-v66-progress-label'
      );

    if (percent) {
      percent.textContent =
        `${current.percent}%`;
    }

    if (label) {
      label.textContent =
        current.text;
    }

    const names = [
      'sparkles',
      'palette',
      'image',
      'sparkles',
    ];

    [
      ...card
        .querySelectorAll(
          '.garad-image-v66-stage'
        ),
    ].forEach(
      (
        stage,
        index
      ) => {
        stage
          .classList
          .remove(
            'active',
            'done'
          );

        const icon =
          stage.querySelector(
            '.garad-image-v66-stage-icon'
          );

        if (
          index <
          current.stage
        ) {
          stage
            .classList
            .add(
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
          stage
            .classList
            .add(
              'active'
            );

          if (icon) {
            icon.innerHTML =
              svg(
                names[index]
              );
          }
        } else if (
          icon
        ) {
          icon.innerHTML =
            svg(
              names[index]
            );
        }
      }
    );
  };

  const createLoadingCard = (
    prompt
  ) => {
    const card =
      document.createElement(
        'div'
      );

    card.className =
      'garad-image-v66-card';

    card.innerHTML = `
      <div class="garad-image-v66-head">

        <span
          class="garad-image-v66-icon is-loading"
        >
          ${svg('spinner')}
        </span>

        <div>

          <div
            class="garad-image-v66-title"
          >
            Creating your image
          </div>

          <div
            class="garad-image-v66-subtitle"
          >
            Garad AI is preparing the visual you requested.
          </div>

        </div>
      </div>

      <div
        class="garad-image-v66-preview"
      >
        <div
          class="garad-image-v66-preview-grid"
        >

          <div
            class="garad-image-v66-block"
          ></div>

          <div
            class="garad-image-v66-stack"
          >

            <div
              class="garad-image-v66-block"
            ></div>

            <div
              class="garad-image-v66-block"
            ></div>

          </div>
        </div>
      </div>

      <div
        class="garad-image-v66-progress-head"
      >

        <span
          class="garad-image-v66-progress-label"
        >
          Understanding your request…
        </span>

        <span
          class="garad-image-v66-percent"
        >
          12%
        </span>

      </div>

      <div
        class="garad-image-v66-progress"
      >

        <div
          class="garad-image-v66-progress-fill"
        ></div>

      </div>

      <div
        class="garad-image-v66-stages"
      >

        <div
          class="garad-image-v66-stage active"
        >

          <span
            class="garad-image-v66-stage-icon"
          >
            ${svg('sparkles')}
          </span>

          <span>
            Understanding your request
          </span>

        </div>

        <div
          class="garad-image-v66-stage"
        >

          <span
            class="garad-image-v66-stage-icon"
          >
            ${svg('palette')}
          </span>

          <span>
            ${
              hasBrandVoice()
                ? 'Applying brand identity'
                : 'Planning the visual direction'
            }
          </span>

        </div>

        <div
          class="garad-image-v66-stage"
        >

          <span
            class="garad-image-v66-stage-icon"
          >
            ${svg('image')}
          </span>

          <span>
            Generating the image
          </span>

        </div>

        <div
          class="garad-image-v66-stage"
        >

          <span
            class="garad-image-v66-stage-icon"
          >
            ${svg('sparkles')}
          </span>

          <span>
            Finalizing the image
          </span>

        </div>

      </div>

      <div
        class="garad-image-v66-result"
      ></div>
    `;

    const pending = {
      card,
      prompt,

      baselineAiCount:
        aiCount(),

      attempt: 0,

      timer: null,

      scanTimer: null,

      deadlineTimer: null,

      baselineUrls:
        snapshotImageUrls(),

      startedAt:
        Date.now(),

      completed:
        false,
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
        anchor
          .insertAdjacentElement(
            'afterend',
            card
          );

        return;
      }

      const fallback =
        fallbackInsertTarget();

      if (fallback) {
        fallback
          .parent
          .insertBefore(
            card,
            fallback.before
          );

        return;
      }

      if (
        tries < 20
      ) {
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

      setStage(
        pending,
        pending.attempt
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

    /*
     * Do not rely only on MutationObserver. Some streamed Chat Pro responses
     * are painted through a renderer whose final image does not match the old
     * bubble selectors. Scan lightly while the request is active so a result
     * generated in seconds is picked up immediately.
     */
    pending.scanTimer =
      setInterval(
        () => {
          if (
            pending.completed
          ) {
            clearInterval(
              pending.scanTimer
            );
            return;
          }

          queueScan();
        },
        700
      );

    /*
     * Never leave a stale card spinning forever at 92%. Three minutes is a
     * safety ceiling only; normal completions are detected much earlier.
     */
    pending.deadlineTimer =
      setTimeout(
        () => {
          if (
            pending.completed
          ) {
            return;
          }

          failPending(
            pending,
            'The image request took too long to return a usable result. Please retry once; no page refresh is required.'
          );
        },
        180000
      );

    return pending;
  };

  const activePending = () => {
    return STATE.pending.find(
      item =>
        !item.completed
    ) || null;
  };

  const startPendingIfNeeded = () => {
    const prompt =
      currentPrompt();

    if (
      !prompt
      ||
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
        STATE.lastStartAt
        <
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

  const safeImageUrl = (
    raw
  ) => {
    if (!raw) {
      return null;
    }

    try {
      const url =
        new URL(
          String(raw).trim(),
          location.origin
        );

      return [
        'http:',
        'https:',
      ].includes(
        url.protocol
      )
        ? url.href
        : null;

    } catch (_) {
      return null;
    }
  };

  const decodeBase64Url = (
    value
  ) => {
    try {
      let input =
        String(
          value || ''
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
        input.length % 4
      ) {
        input += '=';
      }

      const bytes =
        Uint8Array.from(
          atob(input),
          item =>
            item.charCodeAt(0)
        );

      return new TextDecoder()
        .decode(
          bytes
        );

    } catch (_) {
      return null;
    }
  };

  const markReady = (
    pending,
    url,
    sourceElement,
    image
  ) => {
    pending.completed =
      true;

    clearTimeout(
      pending.timer
    );

    clearInterval(
      pending.scanTimer
    );

    clearTimeout(
      pending.deadlineTimer
    );

    const card =
      pending.card;

    card
      .classList
      .add(
        'is-ready'
      );

    const icon =
      card.querySelector(
        '.garad-image-v66-icon'
      );

    if (icon) {
      icon
        .classList
        .remove(
          'is-loading'
        );

      icon
        .classList
        .add(
          'is-ready'
        );

      icon.innerHTML =
        svg('check');
    }

    card
      .querySelector(
        '.garad-image-v66-title'
      )
      .textContent =
        'Image ready';

    card
      .querySelector(
        '.garad-image-v66-subtitle'
      )
      .textContent =
        'Your image has been created successfully.';

    card
      .querySelector(
        '.garad-image-v66-progress-label'
      )
      .textContent =
        'Completed';

    card
      .querySelector(
        '.garad-image-v66-percent'
      )
      .textContent =
        '100%';

    card
      .querySelector(
        '.garad-image-v66-progress-fill'
      )
      .style.width =
        '100%';

    card
      .querySelectorAll(
        '.garad-image-v66-stage'
      )
      .forEach(
        stage => {
          stage
            .classList
            .remove(
              'active'
            );

          stage
            .classList
            .add(
              'done'
            );

          const item =
            stage
              .querySelector(
                '.garad-image-v66-stage-icon'
              );

          if (item) {
            item.innerHTML =
              svg('check');
          }
        }
      );

    const result =
      card.querySelector(
        '.garad-image-v66-result'
      );

    result.innerHTML =
      '';
/*
 * Generated image behaves like ChatGPT media:
 * clicking the image opens the original R2 asset.
 */
image.style.cursor = 'zoom-in';

image.setAttribute(
  'role',
  'button'
);

image.setAttribute(
  'tabindex',
  '0'
);

image.setAttribute(
  'title',
  'Open image'
);

const openGeneratedImage = () => {
  const opened = window.open(
    url,
    '_blank',
    'noopener,noreferrer'
  );

  if (opened) {
    try {
      opened.opener = null;
    } catch (_) {}
  }
};

image.addEventListener(
  'click',
  openGeneratedImage
);

image.addEventListener(
  'keydown',
  (event) => {
    if (
      event.key === 'Enter'
      || event.key === ' '
    ) {
      event.preventDefault();
      openGeneratedImage();
    }
  }
);

    result.appendChild(
      image
    );

    const actions =
      document.createElement(
        'div'
      );

    actions.className =
      'garad-image-v66-actions';

    const open =
      document.createElement(
        'a'
      );

    open.className =
      'garad-image-v66-action';

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
      'garad-image-v66-action';

    download.href =
      url;

    download.rel =
      'noopener noreferrer';

    download.innerHTML =
      `${svg('download')}<span>Download</span>`;

    try {
      if (
        new URL(url)
          .origin ===
        location.origin
      ) {
        download
          .setAttribute(
            'download',
            ''
          );
      } else {
        download.target =
          '_blank';
      }
    } catch (_) {}

    actions.append(
      open,
      download
    );

    result.appendChild(
      actions
    );

    /*
     * Hide the original raw assistant
     * image response only after the
     * replacement image has loaded.
     */
    if (
      sourceElement
      &&
      sourceElement !==
        card
      &&
      !sourceElement.contains(card)
    ) {
      const hideTarget =
        sourceElement.matches?.(
          '.lqd-chat-ai-bubble,[data-message-role="assistant"],.lqd-chat-message-ai'
        )
          ? sourceElement
          : sourceElement.closest?.(
              '.lqd-chat-ai-bubble,[data-message-role="assistant"],.lqd-chat-message-ai'
            );

      if (
        hideTarget
        &&
        !hideTarget.contains(card)
      ) {
        hideTarget.style.display =
          'none';
      }
    }
  };

  const failPending = (
    pending,
    message,
    sourceElement = null
  ) => {
    if (
      !pending
      ||
      pending.completed
    ) {
      return;
    }

    pending.completed =
      true;

    clearTimeout(
      pending.timer
    );

    clearInterval(
      pending.scanTimer
    );

    clearTimeout(
      pending.deadlineTimer
    );

    const card =
      pending.card;

    card
      .classList
      .add(
        'is-error'
      );

    const icon =
      card.querySelector(
        '.garad-image-v66-icon'
      );

    if (icon) {
      icon
        .classList
        .remove(
          'is-loading'
        );

      icon
        .classList
        .add(
          'is-error'
        );

      icon.innerHTML =
        svg('alert');
    }

    card
      .querySelector(
        '.garad-image-v66-title'
      )
      .textContent =
        'Image file is unavailable';

    card
      .querySelector(
        '.garad-image-v66-subtitle'
      )
      .textContent =
        'The generation finished, but the returned image URL could not be loaded.';

    card
      .querySelector(
        '.garad-image-v66-progress-label'
      )
      .textContent =
        'Output unavailable';

    card
      .querySelector(
        '.garad-image-v66-percent'
      )
      .textContent =
        '';

    const result =
      card.querySelector(
        '.garad-image-v66-result'
      );

    result.innerHTML = `
      <div
        class="garad-image-v66-error-note"
      >
        ${
          message
          ||
          'Garad AI received an invalid or inaccessible image source.'
        }
      </div>
    `;

    if (sourceElement) {
      sourceElement.style.display =
        '';
    }
  };

  const verifyAndComplete = (
    pending,
    rawUrl,
    sourceElement = null
  ) => {
    if (
      !pending
      ||
      pending.completed
    ) {
      return;
    }

    const url =
      safeImageUrl(
        rawUrl
      );

    if (!url) {
      failPending(
        pending,
        'The returned image URL is invalid.',
        sourceElement
      );

      return;
    }

    /*
     * Do NOT report 100% merely because
     * the server returned a URL.
     *
     * First confirm that the browser
     * can actually load the image.
     */
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

    let settled =
      false;

    const timeout =
      setTimeout(
        () => {
          if (settled) return;

          settled =
            true;

          failPending(
            pending,
            'The image server did not return a usable image in time.',
            sourceElement
          );
        },
        20000
      );

    image.addEventListener(
      'load',
      () => {
        if (settled) {
          return;
        }

        settled =
          true;

        clearTimeout(
          timeout
        );

        markReady(
          pending,
          url,
          sourceElement,
          image
        );
      },
      {
        once: true,
      }
    );

    image.addEventListener(
      'error',
      () => {
        if (settled) {
          return;
        }

        settled =
          true;

        clearTimeout(
          timeout
        );

        failPending(
          pending,
          'The returned image URL responded with an error such as 404 or a private object.',
          sourceElement
        );
      },
      {
        once: true,
      }
    );

    image.src =
      url;
  };

  const nearestMessageContainer = (
    node
  ) => {
    let element =
      node?.parentElement
      ||
      null;

    if (!element) {
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
        element.closest(
          selector
        );

      if (found) {
        return found;
      }
    }

    return element;
  };

  const consumeResultMarkers = () => {
    const regex =
      /\[\[GARAD_IMAGE_RESULT:([A-Za-z0-9_-]+)\]\]/i;

    const walker =
      document.createTreeWalker(
        document.body,
        NodeFilter.SHOW_TEXT
      );

    const found =
      [];

    while (
      walker.nextNode()
    ) {
      const node =
        walker.currentNode;

      const match =
        String(
          node.nodeValue || ''
        ).match(
          regex
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

        const container =
          nearestMessageContainer(
            node
          );

        node.nodeValue =
          String(
            node.nodeValue || ''
          ).replace(
            regex,
            ''
          );

        let pending =
          activePending();

        if (!pending) {
          pending =
            createLoadingCard(
              ''
            );
        }

        verifyAndComplete(
          pending,
          decoded,
          container
        );
      }
    );
  };

  const extractUrlFromBubble = (
    bubble
  ) => {
    const image =
      bubble.querySelector(
        'img[src]'
      );

    if (image) {
      const source =
        safeImageUrl(
          image.getAttribute(
            'src'
          )
        );

      if (source) {
        return source;
      }
    }

    for (
      const link
      of [
        ...bubble
          .querySelectorAll(
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

      if (!href) {
        continue;
      }

      if (
        /\.(png|jpe?g|webp|gif)(?:$|[?#])/i
          .test(href)
        ||
        /\/uploads\//i
          .test(href)
      ) {
        return href;
      }
    }

    return null;
  };

  /*
   * Broad result detector for the current Chat Pro renderer.
   *
   * A completed image can arrive outside the old assistant bubble selectors.
   * We therefore accept a NEW image-like URL anywhere in the live chat DOM,
   * while excluding everything that existed before the request and excluding
   * our own progress card.
   */
  const consumeAnyNewImageResult = () => {
    const pending =
      activePending();

    if (!pending) {
      return;
    }

    const baseline =
      pending.baselineUrls
      ||
      new Set();

    const elements = [
      ...document.querySelectorAll(
        'img[src],a[href]'
      ),
    ];

    for (
      const element
      of elements
    ) {
      if (
        element.closest(
          '.garad-image-v66-card'
        )
      ) {
        continue;
      }

      const raw =
        element.tagName === 'IMG'
          ? element.getAttribute('src')
          : element.getAttribute('href');

      const url =
        imageLikeUrl(raw);

      if (
        !url
        ||
        baseline.has(url)
      ) {
        continue;
      }

      verifyAndComplete(
        pending,
        url,
        nearestMessageContainer(
          element
        )
      );

      return;
    }

    /*
     * Raw Markdown/SSE fallback: find a newly returned R2/image URL even if
     * the Markdown renderer has not yet converted it into an <img> element.
     */
    const walker =
      document.createTreeWalker(
        document.body,
        NodeFilter.SHOW_TEXT
      );

    const urlRegex =
      /https?:\/\/image\.garad\.ai\/[^\s<>"')\]]+|https?:\/\/[^\s<>"')\]]+\.(?:png|jpe?g|webp|gif)(?:[?#][^\s<>"')\]]*)?/i;

    while (
      walker.nextNode()
    ) {
      const node =
        walker.currentNode;

      const parent =
        node.parentElement;

      if (
        !parent
        ||
        parent.closest(
          '.garad-image-v66-card,script,style,noscript'
        )
      ) {
        continue;
      }

      const match =
        String(
          node.nodeValue || ''
        ).match(
          urlRegex
        );

      if (!match) {
        continue;
      }

      const url =
        imageLikeUrl(
          match[0]
        );

      if (
        !url
        ||
        baseline.has(url)
      ) {
        continue;
      }

      verifyAndComplete(
        pending,
        url,
        nearestMessageContainer(
          node
        )
      );

      return;
    }
  };

  const consumeLegacyResult = () => {
    const pending =
      activePending();

    if (!pending) {
      return;
    }

    const bubbles = [
      ...document
        .querySelectorAll(
          '.lqd-chat-ai-bubble,[data-message-role="assistant"],.lqd-chat-message-ai,.lqd-chat-message,.prose'
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
          bubble.textContent || ''
        ).trim();

      if (
        /image generation failed|could not be generated|no credits|daily image generation limit/i
          .test(text)
      ) {
        failPending(
          pending,
          'The image request could not be completed. Please try again.',
          bubble
        );

        return;
      }

      const url =
        extractUrlFromBubble(
          bubble
        );

      if (
        url
        &&
        (
          /image created successfully/i
            .test(text)
          ||
          bubble.querySelector(
            'img[src]'
          )
        )
      ) {
        verifyAndComplete(
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
      send
      &&
      send.dataset
        .garadImageV66Hooked
        !== '1'
    ) {
      send.dataset
        .garadImageV66Hooked =
        '1';

      send.addEventListener(
        'click',
        startPendingIfNeeded,
        true
      );
    }

    const prompt =
      document.getElementById(
        'prompt'
      );

    if (
      prompt
      &&
      prompt.dataset
        .garadImageV66Hooked
        !== '1'
    ) {
      prompt.dataset
        .garadImageV66Hooked =
        '1';

      prompt.addEventListener(
        'keydown',
        event => {
          if (
            event.key ===
              'Enter'
            &&
            !event.shiftKey
            &&
            !event.isComposing
          ) {
            startPendingIfNeeded();
          }
        },
        true
      );
    }
  };

  const scan = () => {
    syncTheme();

    hookSendActions();

    consumeResultMarkers();

    consumeAnyNewImageResult();

    consumeLegacyResult();
  };

  const queueScan = () => {
    if (STATE.queued) {
      return;
    }

    STATE.queued =
      true;

    requestAnimationFrame(
      () => {
        STATE.queued =
          false;

        scan();
      }
    );
  };

  const boot = () => {
    installThemeWatcher();

    ensureStyles();

    scan();

    STATE.observer =
      new MutationObserver(
        queueScan
      );

    STATE.observer.observe(
      document.body,
      {
        childList:
          true,

        subtree:
          true,

        characterData:
          true,
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