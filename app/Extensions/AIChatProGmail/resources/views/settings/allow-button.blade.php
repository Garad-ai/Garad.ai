<x-form.group class="flex w-full flex-col gap-3">
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3 w-full"
        name="ai_chat_pro_connector_gmail_enabled"
        label="{{ __('Connector: Gmail') }}"
        checked="{{ (bool) setting('ai_chat_pro_connector_gmail_enabled', '1') }}"
        tooltip="{{ __('Allow users to connect their Gmail account so AI Chat Pro can search and read emails for context.') }}"
    />

    <div class="rounded-input border border-input-border px-3 py-3">
        <p class="m-0 mb-2 text-2xs font-semibold text-heading-foreground">
            {{ __('Gmail OAuth credentials') }}
        </p>
        <p class="m-0 mb-3 text-3xs text-foreground/55">
            {{ __('Create OAuth credentials in Google Cloud Console. The redirect URI below must be added to your OAuth client\'s "Authorized redirect URIs".') }}
            <a
                class="font-medium text-primary hover:underline"
                href="https://console.cloud.google.com/apis/credentials"
                target="_blank"
                rel="noopener"
            >{{ __('Open Google Cloud Console') }} →</a>
        </p>

        <div class="flex flex-col gap-3">
            <x-forms.input
                label="{{ __('Client ID') }}"
                type="text"
                name="ai_chat_pro_gmail_client_id"
                value="{{ setting('ai_chat_pro_gmail_client_id') }}"
                placeholder="xxxxxxxxxxxx.apps.googleusercontent.com"
            />

            <x-forms.input
                label="{{ __('Client Secret') }}"
                type="password"
                name="ai_chat_pro_gmail_client_secret"
                value="{{ setting('ai_chat_pro_gmail_client_secret') }}"
                placeholder="GOCSPX-..."
            />

            <x-forms.input
                label="{{ __('Redirect URI') }}"
                type="text"
                name="ai_chat_pro_gmail_redirect_uri"
                value="{{ setting('ai_chat_pro_gmail_redirect_uri', route('dashboard.user.ai-chat-pro.connectors.gmail.callback')) }}"
                tooltip="{{ __('Copy this exact URL into Google Cloud Console as an Authorized redirect URI.') }}"
            />
        </div>
    </div>
</x-form.group>
