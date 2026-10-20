<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ setting('site_name', 'Garad AI') }} | {{ __('Chat') }}</title>
    <link
        rel="icon"
        href="{{ custom_theme_url($setting->favicon_path ?? 'assets/favicon.ico', true) }}"
    >

    @foreach ($styleUrls as $styleUrl)
        <link rel="stylesheet" href="{{ $styleUrl }}">
    @endforeach

    @if ($isAgentModePage)
        <style>
            .app-shell {
                background-color: #050b17 !important;
                background-image: none !important;
            }

            .app-shell > main > .topbar.chat-topbar {
                display: none !important;
            }

            .conversation.agent-mode {
                position: relative;
                z-index: 0;
                isolation: isolate;
                --text: #f5f8ff;
                --muted: rgba(226, 235, 250, .78);
                --faint: rgba(207, 220, 242, .58);
                --line: rgba(208, 224, 250, .2);
                --line-soft: rgba(208, 224, 250, .12);
                --surface: rgba(7, 15, 31, .58);
                --surface-2: rgba(255, 255, 255, .1);
                --surface-3: rgba(255, 255, 255, .14);
                --brand-soft: rgba(165, 130, 255, .18);
                background: transparent !important;
            }

            .conversation.agent-mode::before {
                position: fixed;
                z-index: -1;
                right: 6px;
                left: 272px;
                top: 0;
                bottom: 0;
                background-image:
                    linear-gradient(180deg, #050b17 0%, rgba(5, 11, 23, .38) 18%, #050b17 52%, #050b17 100%),
                    url('{{ url('images/garad-agent-earth-sunrise.jpg') }}');
                background-repeat: no-repeat;
                background-position: 0 0, center top;
                background-size: 100% 100%, cover;
                content: '';
                filter: blur(.5px);
                opacity: .82;
                pointer-events: none;
            }

            .app-shell:has(.sidebar.collapsed) .conversation.agent-mode::before {
                left: 72px;
            }

            .app-shell .agent-empty-state {
                color: #f5f8ff;
                position: relative;
                top: clamp(-104px, -10vh, -48px);
            }

            .app-shell .agent-workspace-mark {
                position: relative;
                overflow: hidden;
                background: transparent !important;
                box-shadow: none !important;
            }

            .app-shell .agent-workspace-mark svg {
                display: none;
            }

            .app-shell .agent-workspace-mark::after {
                position: absolute;
                inset: 0;
                background-image: url('{{ $brand['logoMarkUrl'] }}');
                background-repeat: no-repeat;
                background-position: -29px center;
                background-size: 110px 48px;
                content: '';
                filter: brightness(0) invert(1);
            }

            .app-shell .agent-empty-eyebrow {
                display: none;
            }

            .app-shell .agent-empty-state h1 {
                color: #f5f8ff;
            }

            .app-shell .agent-empty-state p {
                color: rgba(226, 235, 250, .78);
            }

            .conversation.agent-mode .agents-workspace-head,
            .conversation.agent-mode .agent-starter,
            .conversation.agent-mode .agent-task-brief,
            .conversation.agent-mode .agent-workflow-card,
            .conversation.agent-mode .agent-output-card,
            .conversation.agent-mode .agent-example {
                border-color: var(--line) !important;
                background: rgba(7, 15, 31, .34) !important;
                box-shadow: 0 18px 50px rgba(0, 0, 0, .2);
                backdrop-filter: blur(16px) saturate(125%);
                -webkit-backdrop-filter: blur(16px) saturate(125%);
            }

            .app-shell .composer-zone {
                --text: #f5f8ff;
                --muted: rgba(226, 235, 250, .78);
                --faint: rgba(207, 220, 242, .58);
                --line: rgba(208, 224, 250, .2);
                --surface: rgba(7, 15, 31, .76);
                --surface-2: rgba(255, 255, 255, .1);
                --surface-3: rgba(255, 255, 255, .14);
                color: var(--text);
                background: transparent;
                isolation: isolate;
            }

            .app-shell .composer-zone::before {
                position: absolute;
                z-index: 0;
                inset: 0;
                background: linear-gradient(180deg, transparent 0%, rgba(3, 8, 20, .5) 30%, rgba(3, 8, 20, .94) 100%);
                content: '';
                pointer-events: none;
                backdrop-filter: blur(18px);
                -webkit-backdrop-filter: blur(18px);
                mask-image: linear-gradient(180deg, transparent 0%, #000 14%);
                -webkit-mask-image: linear-gradient(180deg, transparent 0%, #000 14%);
                box-shadow: 0 -22px 42px rgba(0, 0, 0, .22);
            }

            .app-shell .composer-zone > .composer-wrap {
                position: relative;
                z-index: 1;
            }

            .app-shell .composer-zone.is-empty-chat {
                background: transparent !important;
            }

            .app-shell .composer-zone.is-empty-chat::before {
                display: none;
            }

            .app-shell .composer {
                border-color: var(--line) !important;
                background: rgba(7, 15, 31, .82) !important;
                box-shadow: 0 18px 48px rgba(0, 0, 0, .42);
                backdrop-filter: blur(24px) saturate(130%);
                -webkit-backdrop-filter: blur(24px) saturate(130%);
            }

            .app-shell .model-btn {
                color: var(--muted);
            }

            .app-shell .model-menu {
                top: calc(100% + 8px);
                bottom: auto;
                max-height: min(42vh, calc(100dvh - 32px));
                border-color: rgba(208, 224, 250, .2);
                background: rgba(7, 15, 31, .86);
                color: #f5f8ff;
                backdrop-filter: blur(18px) saturate(125%);
                -webkit-backdrop-filter: blur(18px) saturate(125%);
            }

            .app-shell .model-menu.agent-menu-above {
                top: auto;
                bottom: calc(100% + 8px);
            }

            .app-shell .composer-note {
                color: var(--muted);
            }

            @media (max-width: 700px) {
                .app-shell .agent-empty-state {
                    top: clamp(-128px, -13vh, -72px);
                }

                .conversation.agent-mode::before {
                    top: 10vh;
                    left: 0;
                }
            }

            @media (min-width: 701px) and (max-width: 900px) {
                .conversation.agent-mode::before {
                    left: 0;
                }
            }

            @media (min-width: 701px) and (max-height: 820px) {
                .app-shell .agent-empty-state {
                    top: clamp(-184px, -20vh, -112px);
                }
            }

        </style>
    @endif

    <script>
        window.__GARAD_API_BASE_URL = @json($apiBaseUrl);
        window.__GARAD_CHAT_SLUG = @json($slug);
        window.__GARAD_BRAND = @json($brand);
        window.__GARAD_BOOTSTRAP = @json($bootstrap);
    </script>
</head>

<body>
    <div id="root"></div>

    @if ($isAgentModePage)
        <script>
            (() => {
                const placeAgentModelMenu = () => {
                    const button = document.querySelector('.model-btn[aria-expanded="true"]');
                    const menu = document.querySelector('.model-menu');

                    if (!button || !menu) {
                        return;
                    }

                    const buttonRect = button.getBoundingClientRect();
                    const viewportHeight = window.visualViewport?.height || window.innerHeight;
                    const spaceAbove = Math.max(0, buttonRect.top - 8);
                    const spaceBelow = Math.max(0, viewportHeight - buttonRect.bottom - 8);
                    const desiredHeight = Math.min(menu.scrollHeight, viewportHeight * 0.42);
                    const placeAbove = spaceBelow < desiredHeight + 8 && spaceAbove > spaceBelow;
                    const availableSpace = placeAbove ? spaceAbove : spaceBelow;

                    menu.classList.toggle('agent-menu-above', placeAbove);
                    menu.style.maxHeight = `${Math.max(120, Math.min(desiredHeight, availableSpace - 8))}px`;
                };

                document.addEventListener('click', (event) => {
                    if (!(event.target instanceof Element) || !event.target.closest('.model-btn')) {
                        return;
                    }

                    window.requestAnimationFrame(() => window.requestAnimationFrame(placeAgentModelMenu));
                });

                window.addEventListener('resize', placeAgentModelMenu);
                window.visualViewport?.addEventListener('resize', placeAgentModelMenu);
            })();
        </script>
    @endif

    @if ($viteDevServer)
        <script type="module" src="{{ $viteDevServer }}/@vite/client"></script>
        <script type="module" src="{{ $viteDevServer }}/src/main.tsx"></script>
    @elseif ($entryScriptUrl)
        <script type="module" src="{{ $entryScriptUrl }}"></script>
    @elseif ($assetsMissing)
        <main style="font-family: sans-serif; margin: 2rem; max-width: 40rem;">
            <h1 style="margin-bottom: 1rem;">{{ __('Chat frontend assets are not available.') }}</h1>
            <p style="line-height: 1.6; color: #444;">
                {{ __('Run npm run frontend:build from the Garad AI project root, or configure GARAD_CHAT_FRONTEND_DEV_URL for a running dev server.') }}
            </p>
        </main>
    @endif
</body>

</html>
