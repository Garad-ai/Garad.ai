{{-- Included in AI Chat Pro settings page via @includeIf('ai-chat-pro-highlight-to-ask::settings') --}}
@if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-highlight-to-ask'))
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3"
        name="ai_chat_pro_highlight_to_ask_enabled"
        label="{{ __('Highlight to Ask') }}"
        checked="{{ (bool) setting('ai_chat_pro_highlight_to_ask_enabled', '1') }}"
        tooltip="{{ __('Allow users to highlight text in chat responses and quickly ask follow-up questions about it.') }}"
    />
@endif
