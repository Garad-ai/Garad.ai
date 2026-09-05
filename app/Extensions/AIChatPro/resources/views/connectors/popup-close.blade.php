<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Connecting...') }}</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f9f9f9; color: #333; }
        .box { text-align: center; }
        .icon { font-size: 2rem; margin-bottom: .5rem; }
    </style>
</head>
<body>
<div class="box">
    @if($success)
        <div class="icon">✓</div>
        <p>{{ __('Connected! Closing...') }}</p>
    @else
        <div class="icon">✗</div>
        <p>{{ $message }}</p>
    @endif
</div>
<script>
    (function () {
        var payload = {
            type: 'ai_chat_pro_connector_connected',
            nonce: ((Date.now()).toString(36) + Math.random().toString(36).slice(2, 8)),
            success: @json($success),
            message: @json($message),
            connector: @json($connector),
        };
        try {
            if (typeof BroadcastChannel === 'function') {
                var ch = new BroadcastChannel('ai_chat_pro_connectors');
                ch.postMessage(payload);
                ch.close();
            }
        } catch (e) {}
        if (window.opener) {
            try { window.opener.postMessage(payload, window.location.origin); } catch (e) {}
        }
        window.close();
    })();
</script>
</body>
</html>
