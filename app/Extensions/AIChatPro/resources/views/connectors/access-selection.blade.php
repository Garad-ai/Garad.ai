@php
    /** @var \App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition $definition */
    /** @var \App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector $connector */
    $popup = $popup ?? false;
    $access = $access ?? [];
    $selected = $selected ?? [];
    $optionsType = (string) ($access['type'] ?? 'items');
    $optionsLabel = (string) ($access['label'] ?? __('Items'));
    $optionsList = (array) ($access['options'] ?? []);
    $previousSelection = (array) data_get($selected, $optionsType, []);
    $recentOnly = (bool) data_get($selected, 'recent_only', false);
    $iconName = $definition->icon();
    $isBladeIcon = str_contains($iconName, '::') && view()->exists($iconName);
    $updateUrl = route('dashboard.user.ai-chat-pro.connectors.access.update', $connector);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Choose what to share') }} — {{ $definition->label() }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f7f7f8;
            --card: #ffffff;
            --border: rgba(0, 0, 0, 0.08);
            --foreground: #1f2024;
            --muted: rgba(31, 32, 36, 0.6);
            --primary: #635bff;
            --primary-foreground: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f0f12;
                --card: #1a1a1f;
                --border: rgba(255, 255, 255, 0.08);
                --foreground: #f4f4f5;
                --muted: rgba(244, 244, 245, 0.6);
            }
        }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; padding: 24px; background: var(--bg); color: var(--foreground); min-height: 100vh; }
        .container { max-width: 560px; margin: 0 auto; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
        .icon { width: 48px; height: 48px; border-radius: 10px; background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .icon svg { width: 28px; height: 28px; object-fit: contain; }
        h1 { font-size: 18px; margin: 0; font-weight: 600; }
        .desc { font-size: 13px; color: var(--muted); margin: 4px 0 0; }
        .section-title { font-size: 14px; font-weight: 600; margin: 20px 0 8px; }
        .options { display: flex; flex-direction: column; gap: 8px; max-height: 320px; overflow-y: auto; padding: 4px 0; }
        .option { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border); cursor: pointer; transition: background .15s ease; }
        .option:hover { background: rgba(0,0,0,0.03); }
        .option input[type="checkbox"] { accent-color: var(--primary); margin: 0; }
        .option .label { font-size: 14px; }
        .option .meta { font-size: 12px; color: var(--muted); margin-inline-start: auto; }
        .toggle-row { display: flex; align-items: center; gap: 10px; padding: 12px 0 0; }
        .empty { font-size: 13px; color: var(--muted); padding: 12px 14px; background: rgba(0,0,0,0.03); border-radius: 8px; }
        .actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; }
        button { font: inherit; cursor: pointer; border-radius: 9999px; padding: 9px 18px; font-size: 13px; font-weight: 500; border: 1px solid var(--border); background: transparent; color: var(--foreground); }
        .btn-primary { background: var(--primary); color: var(--primary-foreground); border-color: var(--primary); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary:hover { background: rgba(0,0,0,0.04); }
        .controls { display: flex; gap: 12px; font-size: 12px; margin: 6px 0 10px; }
        .controls a { color: var(--primary); cursor: pointer; }
        html[dir="rtl"] .actions { justify-content: flex-start; }
        html[dir="rtl"] .option .meta { margin-inline-start: 0; margin-inline-end: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="icon">
                    @if ($isBladeIcon)
                        @include($iconName)
                    @else
                        @svg($iconName)
                    @endif
                </div>
                <div>
                    <h1>{{ __('Choose what to share') }}</h1>
                    <p class="desc">{{ __('Pick exactly which :label the AI may access for :provider.', ['label' => mb_strtolower($optionsLabel), 'provider' => $definition->label()]) }}</p>
                </div>
            </div>

            <form id="access-form">
                <p class="section-title">{{ $optionsLabel }}</p>

                @if (count($optionsList) === 0)
                    <div class="empty">
                        {{ __('No options were returned from the provider. Saving an empty selection means the AI will see everything this connector allows.') }}
                    </div>
                @else
                    <div class="controls">
                        <a id="select-all">{{ __('Select all') }}</a>
                        <a id="select-none">{{ __('Select none') }}</a>
                    </div>
                    <div class="options">
                        @foreach ($optionsList as $option)
                            @php
                                $optionId = (string) data_get($option, 'id', '');
                                $optionName = (string) data_get($option, 'name', $optionId);
                                $optionMeta = (string) data_get($option, 'meta', '');
                                $isChecked = in_array($optionId, $previousSelection, true);
                            @endphp
                            <label class="option">
                                <input
                                    type="checkbox"
                                    name="selection[]"
                                    value="{{ $optionId }}"
                                    @checked($isChecked)
                                />
                                <span class="label">{{ $optionName }}</span>
                                @if ($optionMeta !== '')
                                    <span class="meta">{{ $optionMeta }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="toggle-row">
                    <input type="checkbox" id="recent_only" name="recent_only" value="1" @checked($recentOnly) />
                    <label for="recent_only" style="font-size: 14px;">{{ __('Only the last 30 days') }}</label>
                </div>

                <div class="actions">
                    <button type="button" class="btn-secondary" onclick="window.close();">{{ __('Skip') }}</button>
                    <button type="submit" class="btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tell the opener we're connected right away so the parent modal can refresh
        // and show "Connected" while the user is still picking labels in this popup.
        // Microsoft (and sometimes Google) set Cross-Origin-Opener-Policy on their
        // login pages, which severs `window.opener` once the popup returns to our
        // origin. BroadcastChannel is same-origin IPC and is not affected by COOP,
        // so we use it as the primary signal and keep postMessage as a fallback.
        (function () {
            const payload = {
                type: 'ai_chat_pro_connector_connected',
                nonce: (Date.now().toString(36) + Math.random().toString(36).slice(2, 8)),
                success: true,
                message: @json(__(':provider connected successfully.', ['provider' => $definition->label()])),
                connector: { key: @json($definition->key()) },
            };
            try {
                if (typeof BroadcastChannel === 'function') {
                    const ch = new BroadcastChannel('ai_chat_pro_connectors');
                    ch.postMessage(payload);
                    ch.close();
                }
            } catch (e) {}
            if (window.opener) {
                try { window.opener.postMessage(payload, window.location.origin); } catch (e) {}
            }
        })();

        (function () {
            const form = document.getElementById('access-form');
            const selectAll = document.getElementById('select-all');
            const selectNone = document.getElementById('select-none');

            if (selectAll) {
                selectAll.addEventListener('click', function () {
                    form.querySelectorAll('input[name="selection[]"]').forEach((cb) => cb.checked = true);
                });
            }
            if (selectNone) {
                selectNone.addEventListener('click', function () {
                    form.querySelectorAll('input[name="selection[]"]').forEach((cb) => cb.checked = false);
                });
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                const formData = new FormData(form);
                const selection = formData.getAll('selection[]');
                const recentOnly = formData.get('recent_only') ? true : false;

                try {
                    const res = await fetch(@json($updateUrl), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ selection: selection, recent_only: recentOnly }),
                    });

                    if (res.ok) {
                        const updated = {
                            type: 'ai_chat_pro_connector_access_updated',
                            nonce: (Date.now().toString(36) + Math.random().toString(36).slice(2, 8)),
                            success: true,
                        };
                        try {
                            if (typeof BroadcastChannel === 'function') {
                                const ch = new BroadcastChannel('ai_chat_pro_connectors');
                                ch.postMessage(updated);
                                ch.close();
                            }
                        } catch (e) {}
                        if (window.opener) {
                            try { window.opener.postMessage(updated, window.location.origin); } catch (e) {}
                        }
                        window.close();
                    } else {
                        alert(@json(__('Could not save your selection. Please try again.')));
                    }
                } catch (err) {
                    alert(@json(__('Could not save your selection. Please try again.')));
                }
            });
        })();
    </script>
</body>
</html>
