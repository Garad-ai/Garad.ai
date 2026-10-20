<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Choose a LinkedIn account') }}</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #0b0b10; color: #f4f2f8; }
        body { margin: 0; padding: 32px 18px; }
        main { max-width: 520px; margin: 0 auto; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        p { color: #aaa6b6; line-height: 1.5; }
        form { display: grid; gap: 12px; }
        label { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid #302d3b; border-radius: 12px; background: #14131b; cursor: pointer; }
        label:has(input:checked) { border-color: #9b6bff; box-shadow: 0 0 0 1px #9b6bff inset; }
        input { accent-color: #9b6bff; }
        button { margin-top: 8px; padding: 12px 16px; border: 0; border-radius: 10px; background: #8e5cf7; color: white; font: inherit; font-weight: 650; cursor: pointer; }
        .notice { padding: 12px; border: 1px solid #474052; border-radius: 10px; background: #17151d; font-size: 14px; }
        .meta { display: block; margin-top: 4px; color: #aaa6b6; font-size: 13px; }
        h2 { margin: 22px 0 4px; font-size: 15px; letter-spacing: .04em; text-transform: uppercase; }
        .account { align-items: flex-start; }
        .account img { width: 42px; height: 42px; object-fit: cover; border-radius: 50%; }
        .account .meta + .meta { margin-top: 2px; }
        .permission-ok { color: #75d6a1; }
        .permission-warn { color: #f3c56b; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('Choose a LinkedIn account') }}</h1>
    <p>{{ __('Select your personal profile or a company Page returned by LinkedIn for this member.') }}</p>

    @if ($organizationError)
        <p class="notice">{{ $organizationError }}</p>
    @endif

    <form method="POST" action="{{ route('social-media.oauth.callback.linkedin.select') }}">
        @csrf
        <input type="hidden" name="selection_key" value="{{ $selectionKey }}">

        <h2>{{ __('Personal profile') }}</h2>
        <label class="account">
            <input type="radio" name="account" value="profile" checked>
            @if (data_get($profile, 'picture'))
                <img src="{{ data_get($profile, 'picture') }}" alt="">
            @endif
            <span>
                <strong>{{ data_get($profile, 'name', __('LinkedIn Profile')) }}</strong>
                <span class="meta">{{ data_get($profile, 'email', __('Member account')) }}</span>
                <span class="meta {{ in_array('w_member_social', $requestedScopes, true) ? 'permission-ok' : 'permission-warn' }}">
                    {{ in_array('w_member_social', $requestedScopes, true) ? __('Personal posting permission requested') : __('Personal posting permission is not configured') }}
                </span>
            </span>
        </label>

        @if (count($organizations))
            <h2>{{ __('Company Pages') }}</h2>
            @foreach ($organizations as $organization)
                <label class="account">
                    <input type="radio" name="account" value="{{ $organization['urn'] }}">
                    @if ($organization['picture'])
                        <img src="{{ $organization['picture'] }}" alt="">
                    @endif
                    <span>
                        <strong>{{ $organization['name'] }}</strong>
                        @if ($organization['url'])
                            <a class="meta" href="{{ $organization['url'] }}" target="_blank" rel="noopener noreferrer">{{ $organization['url'] }}</a>
                        @endif
                        <span class="meta">{{ __('Organization ID') }}: {{ $organization['id'] }} · {{ $organization['urn'] }}</span>
                        <span class="meta">{{ __('Role') }}: {{ str_replace('_', ' ', $organization['role']) }}</span>
                        <span class="meta {{ $organization['can_publish'] ? 'permission-ok' : 'permission-warn' }}">
                            {{ $organization['can_publish'] ? __('Can publish to this Page') : __('Page connected, but this LinkedIn account/app does not have permission to publish to this organization.') }}
                        </span>
                        @if ($organization['can_manage'])
                            <span class="meta permission-ok">{{ __('Can manage this organization') }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        @endif

        <button type="submit">{{ __('Connect selected account') }}</button>
    </form>
</main>
</body>
</html>
