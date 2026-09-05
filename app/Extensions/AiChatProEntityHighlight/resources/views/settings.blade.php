{{-- Included in AI Chat Pro settings page via @includeIf('ai-chat-pro-entity-highlight::settings') --}}
@if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-entity-highlight'))
    <x-form.checkbox
        class="border-input rounded-input border !px-2.5 !py-3"
        name="ai_chat_pro_entity_highlight_enabled"
        label="{{ __('Entity Highlights') }}"
        checked="{{ (bool) setting('ai_chat_pro_entity_highlight_enabled', '1') }}"
        tooltip="{{ __('Highlight notable entities in AI responses with clickable details.') }}"
    />
@endif
