@php
    use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
@endphp

<h3 class="col-span-2 mb-2 mt-5 self-center pb-2">
    @lang('Campaigns')
</h3>
<form
    class="header-search header-search-in-content group relative mb-5 w-full transition-all"
    action=""
>
    <div class="relative w-full max-lg:bg-white max-lg:p-3 max-lg:dark:bg-zinc-800">
        <svg
            class="lqd-header-search-icon pointer-events-none absolute start-3 top-1/2 z-10 w-5 -translate-y-1/2 opacity-75 max-lg:start-6"
            stroke-width="1.5"
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            stroke="currentColor"
            fill="none"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
            <path d="M21 21l-6 -6"></path>
        </svg>
        <div class="lqd-input-container peer relative">
            <input
                class="lqd-input lqd-input-md header-search-input peer block h-10 w-full border border-none border-input-border bg-heading-foreground/5 px-4 py-2 ps-10 text-base text-input-foreground ring-offset-0 transition-colors focus:border-secondary focus:outline-0 focus:ring focus:ring-secondary dark:focus:ring-foreground/10 max-lg:rounded-md sm:text-2xs"
                name="search"
                value=""
                type="text"
                placeholder="Search"
            >
        </div>
    </div>
</form>

<div class="py-2">
    <x-table>
        <x-slot:head>
            <th>
                {{ __('ID') }}
            </th>
            <th>
                {{ __('Name') }}
            </th>
            <th>
                {{ __('Status') }}
            </th>
            <th>
                {{ __('Platform') }}
            </th>

            <th>
                {{ __('Schedule at') }}
            </th>

            <th class="text-end">
                {{ __('Action') }}
            </th>
        </x-slot:head>

        <x-slot:body>
            @foreach ($campaignList as $item)
                <tr>
                    <td>
                        {{ $item->id }}
                    </td>
                    <td>
                        {{ $item->name }}
                    </td>
                    <td>
                        <p @class([
                            'lqd-posts-item-type sort-file inline-flex w-auto m-0 items-center gap-1.5 justify-self-start whitespace-nowrap rounded-full border px-2 py-1 text-[12px] font-medium leading-none',
                            'text-green-500' => $item['status'] === CampaignStatus::published,
                            'text-yellow-700' => $item['status'] === CampaignStatus::scheduled,
                        ])>
                            @if ($item['status'] === CampaignStatus::published)
                                <x-tabler-check class="size-4" />
                            @elseif ($item['status'] === CampaignStatus::scheduled)
                                <x-tabler-clock class="size-4" />
                            @else
                                <x-tabler-circle-dashed class="size-4" />
                            @endif
                            @lang(str()->title($item->status->value))
                        </p>
                    </td>
                    <td>
                        <img
                            class="size-6"
                            alt="{{ $item->type->name }}"
                            src="{{ asset('vendor/marketing-bot/images/' . $item->type->value . '.png') }}"
                        />
                    </td>
                    <td>
                        {{ $item->scheduled_at ? $item->scheduled_at->format('Y-m-d H:i') : __('Not scheduled') }}
                    </td>
                    <td class="whitespace-nowrap text-end">
                        @if ($app_is_demo)
                            <x-button
                                class="size-9"
                                variant="ghost-shadow"
                                hover-variant="danger"
                                size="none"
                                onclick="return toastr.info('This feature is disabled in Demo version.')"
                                title="{{ __('Delete') }}"
                            >
                                <x-tabler-x class="size-4" />
                            </x-button>
                        @else
                            @if ($item->type->value === 'whatsapp')
                                <x-button
                                    class="size-9"
                                    href="{{ route('dashboard.user.marketing-bot.whatsapp-campaign.analytics', $item->id) }}"
                                    variant="ghost-shadow"
                                    size="none"
                                    title="{{ __('Analytics') }}"
                                >
                                    <x-tabler-chart-bar class="size-4" />
                                </x-button>
                            @endif
                            <x-button
                                class="size-9"
                                href="{{ route('dashboard.user.marketing-bot.' . $item->type->value . '-campaign.edit', $item->id) }}"
                                variant="ghost-shadow"
                                size="none"
                                title="{{ __('Edit') }}"
                            >
                                <x-tabler-edit class="size-4" />
                            </x-button>
                            <x-button
                                class="size-9"
                                data-delete="delete"
                                data-delete-link="{{ route('dashboard.user.marketing-bot.' . $item->type->value . '-campaign.destroy', $item->id) }}"
                                variant="ghost-shadow"
                                hover-variant="danger"
                                size="none"
                                title="{{ __('Delete') }}"
                            >
                                <x-tabler-x class="size-4" />
                            </x-button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-table>

    <div class="mt-3 flex justify-end">
        {{ $campaignList->links() }}
    </div>
</div>
