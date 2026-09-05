<x-form.group class="flex w-full flex-col gap-3">
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3 w-full"
        name="ai_chat_pro_connector_notion_enabled"
        label="{{ __('Connector: Notion') }}"
        checked="{{ (bool) setting('ai_chat_pro_connector_notion_enabled', '1') }}"
        tooltip="{{ __('Allow users to connect their Notion workspace so AI Chat Pro can search and read pages for context.') }}"
    />

    <div class="rounded-input border border-input-border px-3 py-3">
        <p class="m-0 mb-2 text-2xs font-semibold text-heading-foreground">
            {{ __('Notion OAuth credentials') }}
        </p>
        <p class="m-0 mb-3 text-3xs text-foreground/55">
            {{ __('Create a public integration in Notion. Add the redirect URI below to the integration\'s "Redirect URIs" list.') }}
            <a
                class="font-medium text-primary hover:underline"
                href="https://www.notion.so/profile/integrations"
                target="_blank"
                rel="noopener"
            >{{ __('Open Notion Integrations') }} →</a>
        </p>

        <div class="flex flex-col gap-3">
            <x-forms.input
                label="{{ __('OAuth Client ID') }}"
                type="text"
                name="ai_chat_pro_notion_client_id"
                value="{{ setting('ai_chat_pro_notion_client_id') }}"
            />

            <x-forms.input
                label="{{ __('OAuth Client Secret') }}"
                type="password"
                name="ai_chat_pro_notion_client_secret"
                value="{{ setting('ai_chat_pro_notion_client_secret') }}"
            />

            <x-forms.input
                label="{{ __('Redirect URI') }}"
                type="text"
                name="ai_chat_pro_notion_redirect_uri"
                value="{{ setting('ai_chat_pro_notion_redirect_uri', route('dashboard.user.ai-chat-pro.connectors.notion.callback')) }}"
                tooltip="{{ __('Copy this exact URL into the Notion integration as a Redirect URI.') }}"
            />
        </div>
    </div>
</x-form.group>
