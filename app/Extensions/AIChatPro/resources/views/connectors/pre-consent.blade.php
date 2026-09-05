@php
    /** @var \App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition $definition */
    $popup = $popup ?? false;
    $iconName = $definition->icon();
    $isBladeIcon = str_contains($iconName, '::') && view()->exists($iconName);
    $permissions = $definition->permissions();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Connect to :provider', ['provider' => $definition->label()]) }}</title>
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
            --warning: #b45309;
            --warning-bg: #fef3c7;
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
        .container { max-width: 520px; margin: 0 auto; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
        .header { display: flex; align-items: center; gap: 14px; }
        .icon { width: 48px; height: 48px; border-radius: 10px; background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .icon svg { width: 28px; height: 28px; object-fit: contain; }
        h1 { font-size: 18px; margin: 0; font-weight: 600; }
        .desc { font-size: 13px; color: var(--muted); margin: 4px 0 0; }
        .section-title { font-size: 14px; font-weight: 600; margin: 24px 0 8px; }
        ul { margin: 0; padding-inline-start: 20px; }
        li { margin: 6px 0; font-size: 14px; line-height: 1.5; }
        .note { background: rgba(0,0,0,0.03); border-radius: 8px; padding: 12px 14px; font-size: 12px; color: var(--muted); margin-top: 20px; }
        .actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 28px; }
        button, .btn { font: inherit; cursor: pointer; border-radius: 9999px; padding: 9px 18px; font-size: 13px; font-weight: 500; border: 1px solid var(--border); background: transparent; color: var(--foreground); transition: opacity .15s ease, background .15s ease; }
        .btn-primary { background: var(--primary); color: var(--primary-foreground); border-color: var(--primary); }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary:hover { background: rgba(0,0,0,0.04); }
        html[dir="rtl"] .actions { justify-content: flex-start; }
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
                    <h1>{{ __('Connect to :provider', ['provider' => $definition->label()]) }}</h1>
                    <p class="desc">{{ $definition->description() }}</p>
                </div>
            </div>

            <p class="section-title">{{ __('What this connector can do') }}</p>
            <ul>
                @foreach ($permissions as $permission)
                    <li>{{ $permission }}</li>
                @endforeach
            </ul>

            <div class="note">
                {{ __('AI Chat Pro will only read this data when you ask a question that needs it. We never write to your account, never run in the background, and you can disconnect at any time.') }}
            </div>

            <form class="actions" method="GET" action="{{ $definition->redirectRoute() }}">
                @if ($popup)
                    <input type="hidden" name="popup" value="1">
                @endif
                <button type="button" class="btn btn-secondary" onclick="window.close();">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Continue to :provider', ['provider' => $definition->label()]) }}</button>
            </form>
        </div>
    </div>
</body>
</html>
