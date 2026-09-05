{{-- Included in AI Chat Pro settings page via @includeIf('ai-chat-pro-smart-image::settings') --}}
<x-form.checkbox
    class="border-input rounded-input border !px-2.5 !py-3"
    name="ai_chat_pro_smart_image_enabled"
    label="{{ __('Smart Image Display') }}"
    checked="{{ (bool) setting('ai_chat_pro_smart_image_enabled', '1') }}"
    tooltip="{{ __('Automatically search and display relevant images for visual queries. Uses the configured realtime search provider.') }}"
/>
