<x-card
    class:body="px-5 lg:px-10 py-6 lg:py-11 flex items-center flex-wrap gap-y-5"
    class="mb-9"
>
    <div class="w-full shrink lg:basis-1/3">
        <p class="mb-0 font-heading text-xl font-semibold text-heading-foreground">
            @lang('Summary')
        </p>
    </div>

    <div class="w-full lg:basis-2/3">
        <div class="flex flex-col gap-y-4 md:flex-row">

            <a
                class="group flex grow flex-col gap-1 border-b pb-4 text-heading-foreground transition-all md:border-b-0 md:border-e md:pb-0 md:pe-3 xl:px-10"
                href="{{ route('dashboard.user.social-media.platforms') }}"
            >
                <span class="group-hover:text-primary group-hover:underline">
                    {{ __('Total Accounts') }}

                    <x-tabler-chevron-right class="ms-1 inline size-4 -translate-x-1 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100" />
                </span>
                <span class="flex font-heading text-[23px]/none font-semibold">
                    {{ $userPlatforms->count() }}
                </span>
            </a>

            <a
                class="group flex grow flex-col gap-1 border-b pb-4 text-heading-foreground transition-all md:border-b-0 md:border-e md:px-3 md:pb-0 xl:px-10"
                href="{{ route('dashboard.user.social-media.platforms', ['active' => 'on']) }}"
            >
                <span class="group-hover:text-primary group-hover:underline">
                    {{ __('Active Accounts') }}

                    <x-tabler-chevron-right class="ms-1 inline size-4 -translate-x-1 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100" />
                </span>
                <span class="flex font-heading text-[23px]/none font-semibold">
                    {{ $userPlatforms
                        ?->filter(function ($item) {
                            return $item->connected_at && $item->expires_at && $item->expires_at->gt(now());
                        })
                        ?->count() }}
                </span>
            </a>

            <a
                class="group flex grow flex-col gap-1 pb-4 text-heading-foreground transition-all md:px-3 md:pb-0 xl:px-10"
                href="{{ route('dashboard.user.social-media.platforms', ['inactive' => 'on']) }}"
            >
                <span class="group-hover:text-primary group-hover:underline">
                    {{ __('Inactive Accounts') }}
                    {{-- <x-tabler-chevron-right class="ms-1 inline size-4 -translate-x-1 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100" /> --}}
                </span>
                <span class="flex font-heading text-[23px]/none font-semibold">
                    {{ $userPlatforms
                        ?->filter(function ($item) {
                            return !($item->connected_at && $item->expires_at && $item->expires_at->gt(now()));
                        })
                        ?->count() }}
                </span>
            </a>
        </div>
    </div>
</x-card>
