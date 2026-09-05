<x-forms.input
    class="h-11 shrink-0 rounded-none !border-x-0 !border-t-0 bg-transparent px-4 !text-[11px] font-semibold uppercase tracking-wide text-foreground/70 focus:border-input-border focus:ring-0 xl:px-6"
    name="chatbot_channel"
    size="lg"
    type="select"
    x-model="chatbot_channel"
    @change.prevent="fetchChats({filter: true})"
>
    <option
        class="bg-background text-foreground"
        value="all"
    >@lang('All Channel')</option>
    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-telegram'))
        <option
            class="bg-background text-foreground"
            value="telegram"
        >@lang('Telegram')</option>
    @endif
    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-whatsapp'))
        <option
            class="bg-background text-foreground"
            value="whatsapp"
        >@lang('Whatsapp')</option>
    @endif
</x-forms.input>
