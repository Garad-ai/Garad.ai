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
    if (window.opener) {
        window.opener.postMessage({
            type: 'connector_connected',
            success: @json($success),
            message: @json($message),
            connector: @json($connector),
        }, window.location.origin);
    }
    window.close();
</script>
</body>
</html>
