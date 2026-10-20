<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Social account connection') }}</title>
</head>
<body>
    @php
        $oauthStatus = $oauthStatus ?? session('type', 'error');
        $oauthMessage = $oauthMessage ?? session('message', __('OAuth did not complete and no account was saved. Please retry and approve access in the provider window.'));
        $popupId = $popupId ?? session('oauth_popup_id');
        $openerOrigin = $openerOrigin ?? request()->getSchemeAndHttpHost();
    @endphp
    <p>{{ $oauthMessage }}</p>
    <script>
        const completion = {
            type: "garad-social-oauth-complete",
            popupId: {!! json_encode($popupId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!},
            status: {!! json_encode($oauthStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!},
            message: {!! json_encode($oauthMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
        };
        const popupId = completion.popupId;

        if (window.opener && !window.opener.closed) {
            window.opener.postMessage(completion, {!! json_encode($openerOrigin, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!});
        }
        if (popupId && "BroadcastChannel" in window) {
            const channel = new BroadcastChannel(`garad-social-oauth-${popupId}`);
            channel.postMessage(completion);
            window.setTimeout(() => channel.close(), 100);
        }
        window.setTimeout(() => window.close(), 150);
    </script>
</body>
</html>
