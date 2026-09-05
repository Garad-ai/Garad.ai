<x-form.group class="flex w-full flex-col gap-3">
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3 w-full"
        name="ai_chat_pro_connector_outlook_enabled"
        label="{{ __('Connector: Outlook') }}"
        checked="{{ (bool) setting('ai_chat_pro_connector_outlook_enabled', '1') }}"
        tooltip="{{ __('Allow users to connect their Outlook account so AI Chat Pro can search and read emails for context.') }}"
    />

    <div class="rounded-input border border-input-border px-3 py-3">
        <p class="m-0 mb-2 text-2xs font-semibold text-heading-foreground">
            {{ __('Outlook (Microsoft Graph) OAuth credentials') }}
        </p>
        <p class="m-0 mb-3 text-3xs text-foreground/55">
            {{ __('Register an app in the Microsoft Entra admin center. Add the redirect URI below to the app\'s authentication settings.') }}
            <a
                class="font-medium text-primary hover:underline"
                href="https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade"
                target="_blank"
                rel="noopener"
            >{{ __('Open Microsoft Entra') }} →</a>
        </p>

        <div class="flex flex-col gap-3">
            <x-forms.input
                label="{{ __('Client ID') }}"
                type="text"
                name="ai_chat_pro_outlook_client_id"
                value="{{ setting('ai_chat_pro_outlook_client_id') }}"
            />

            <x-forms.input
                label="{{ __('Client Secret') }}"
                type="password"
                name="ai_chat_pro_outlook_client_secret"
                value="{{ setting('ai_chat_pro_outlook_client_secret') }}"
            />

            <x-forms.input
                label="{{ __('Tenant') }}"
                type="text"
                name="ai_chat_pro_outlook_tenant"
                value="{{ setting('ai_chat_pro_outlook_tenant', 'common') }}"
                tooltip="{{ __('Leave as `common` to allow any Microsoft account, or paste your tenant ID to restrict to a single organization.') }}"
            />

            <x-forms.input
                label="{{ __('Redirect URI') }}"
                type="text"
                name="ai_chat_pro_outlook_redirect_uri"
                value="{{ setting('ai_chat_pro_outlook_redirect_uri', route('dashboard.user.ai-chat-pro.connectors.outlook.callback')) }}"
                tooltip="{{ __('Copy this exact URL into the Microsoft app registration as a Web Redirect URI.') }}"
            />
        </div>
    </div>
</x-form.group>
