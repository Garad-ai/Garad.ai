<x-form.group class="flex w-full flex-col gap-3">
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3 w-full"
        name="ai_chat_pro_connector_google_calendar_enabled"
        label="{{ __('Connector: Google Calendar') }}"
        checked="{{ (bool) setting('ai_chat_pro_connector_google_calendar_enabled', '1') }}"
        tooltip="{{ __('Allow users to connect their Google Calendar so AI Chat Pro can see upcoming events for context.') }}"
    />

    <div class="rounded-input border border-input-border px-3 py-3">
        <p class="m-0 mb-2 text-2xs font-semibold text-heading-foreground">
            {{ __('Google Calendar OAuth credentials') }}
        </p>
        <p class="m-0 mb-3 text-3xs text-foreground/55">
            {{ __('Use OAuth credentials from Google Cloud Console with the Calendar API enabled. Add the redirect URI below to the OAuth client.') }}
            <a
                class="font-medium text-primary hover:underline"
                href="https://console.cloud.google.com/apis/credentials"
                target="_blank"
                rel="noopener"
            >{{ __('Open Google Cloud Console') }} →</a>
            ·
            <a
                class="font-medium text-primary hover:underline"
                href="https://console.cloud.google.com/apis/library/calendar-json.googleapis.com"
                target="_blank"
                rel="noopener"
            >{{ __('Enable Calendar API') }} →</a>
        </p>

        <div class="flex flex-col gap-3">
            <x-forms.input
                label="{{ __('Client ID') }}"
                type="text"
                name="ai_chat_pro_google_calendar_client_id"
                value="{{ setting('ai_chat_pro_google_calendar_client_id') }}"
                placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
            />

            <x-forms.input
                label="{{ __('Client Secret') }}"
                type="password"
                name="ai_chat_pro_google_calendar_client_secret"
                value="{{ setting('ai_chat_pro_google_calendar_client_secret') }}"
                placeholder="GOCSPX-..."
            />

            <x-forms.input
                label="{{ __('Redirect URI') }}"
                type="text"
                name="ai_chat_pro_google_calendar_redirect_uri"
                value="{{ setting('ai_chat_pro_google_calendar_redirect_uri', route('dashboard.user.ai-chat-pro.connectors.google-calendar.callback')) }}"
                tooltip="{{ __('Copy this exact URL into Google Cloud Console as an Authorized redirect URI.') }}"
            />
        </div>
    </div>
</x-form.group>
