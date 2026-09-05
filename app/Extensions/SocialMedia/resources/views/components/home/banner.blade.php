<div
    class="group relative flex flex-wrap items-center justify-center gap-x-4 gap-y-7 overflow-hidden rounded-xl bg-gradient-to-r from-gradient-from/40 via-gradient-via/40 to-gradient-to/60 px-7 py-10 text-center dark:border dark:border-heading-foreground/10 dark:from-[rgba(186,255,219,0.07)] dark:via-[rgba(185,213,255,0.07)] dark:to-[rgba(228,188,252,0.07)] md:justify-start md:px-20 md:text-start">
    <div class="flex w-full flex-wrap items-center justify-center gap-x-7 gap-y-3 md:w-1/2 md:flex-nowrap md:justify-start">
        <figure class="max-w-20 group-hover:motion-preset-confetti">
            <img
                class="w-full"
                alt="@lang('Like Image')"
                src="{{ asset('vendor/social-media/images/like.png') }}"
                width="166"
                height="183"
            >
        </figure>
        <h3 class="m-0 grow xl:pe-24">
            {{ trans('Personalize and automate your social media posts') }}
        </h3>
    </div>

    <div class="md:ms-auto">
        <x-button
            href="{{ route('dashboard.user.social-media.campaign.index') }}"
            variant="ghost-shadow"
        >
            <x-tabler-plus class="size-4" />
            {{ trans('Visit BrandCenter') }}
        </x-button>
    </div>
</div>
