<div
    class="group relative flex flex-wrap items-center justify-center gap-x-4 gap-y-7 overflow-hidden rounded-xl bg-gradient-to-r from-gradient-from/40 via-gradient-via/40 to-gradient-to/60 px-7 py-10 text-center dark:border dark:border-heading-foreground/10 dark:from-[rgba(186,255,219,0.07)] dark:via-[rgba(185,213,255,0.07)] dark:to-[rgba(228,188,252,0.07)] md:justify-start md:px-20 md:text-start">
    <div class="flex w-full flex-wrap items-center justify-center gap-x-7 gap-y-3 md:w-1/2 md:flex-nowrap md:justify-start">
        <div class="!flex flex-nowrap items-center justify-center gap-2.5 group-hover:motion-preset-confetti">
            <div class="size-16">
                <img
                    class="h-full w-full object-cover"
                    src="{{ asset('vendor/marketing-bot/images/telegram_banner.png') }}"
                    alt="@lang('Like Image')"
                >
            </div>
            <div class="size-16">
                <img
                    class="h-full w-full object-cover"
                    src="{{ asset('vendor/marketing-bot/images/whatsapp_banner.png') }}"
                    alt="@lang('Like Image')"
                >
            </div>
        </div>
        <h3 class="m-0 grow xl:pe-24">
            {{ trans('Broadcast messages to your audience instantly on WhatsApp and Telegram.') }}
        </h3>
    </div>

    <div class="md:ms-auto">
        <x-button
            href="{{ route('dashboard.user.marketing-bot.settings.index') }}"
            variant="ghost-shadow"
        >
            <x-tabler-settings class="size-4" />
            {{ trans('Manage Settings') }}
        </x-button>
    </div>
</div>
