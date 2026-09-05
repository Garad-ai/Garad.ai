@php
    use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;

    $sort_filters = [
        'newest' => ['label' => __('Newest')],
        'oldest' => ['label' => __('Oldest')],
    ];

    $status_filters = [
        'all'       => ['label' => __('ALL STATUS')],
        'completed' => ['label' => __('Completed')],
        'answered'  => ['label' => __('Answered')],
        'missed'    => ['label' => __('Missed')],
        'failed'    => ['label' => __('Failed')],
    ];

    $provider_filters = [
        'all'         => ['label' => __('ALL PROVIDERS')],
        'twilio'      => ['label' => __('Twilio')],
        'elevenlabs'  => ['label' => __('ElevenLabs')],
    ];

    $call_agent_filters = ['all' => ['label' => __('ALL AGENTS')]];
    foreach (ExtPhoneCallAgent::where('user_id', auth()->id())->get() as $phoneAgent) {
        $call_agent_filters[$phoneAgent->id] = ['label' => $phoneAgent->title];
    }
@endphp

{{-- Header bar --}}
<div class="relative flex h-[--header-height] shrink-0 items-center gap-2.5 border-b px-4">
    <x-button
        class="flex aspect-square size-8 bg-foreground/10 text-foreground"
        hover-variant="primary"
        size="none"
        aria-label="{{ __('Back to Dashboard') }}"
        href="#"
        @click.prevent="setOpen(false)"
    >
        <x-tabler-chevron-left class="size-5" />
    </x-button>

    <p class="m-0 flex items-center gap-1 font-heading text-base font-bold text-heading-foreground sm:gap-2">
        <x-tabler-phone class="size-[18px]" />
        {{ __('Call History') }}
    </p>

    <div class="ms-auto flex gap-2">
        <x-dropdown.dropdown
            anchor="end"
            triggerType="click"
            offsetY="10px"
        >
            <x-slot:trigger
                class="size-7 shrink-0 bg-foreground/5 p-0 hover:-translate-y-0.5"
                variant="none"
                title="{{ __('Filter Agents') }}"
            >
                <svg
                    width="14"
                    height="9"
                    viewBox="0 0 14 9"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path d="M5.58333 8.75V7.33333H8.41667V8.75H5.58333ZM2.75 5.20833V3.79167H11.25V5.20833H2.75ZM0.625 1.66667V0.25H13.375V1.66667H0.625Z" />
                </svg>
            </x-slot:trigger>

            <x-slot:dropdown class="min-w-44 p-2 text-xs font-medium">
                <ul>
                    @foreach ($call_agent_filters as $key => $filter)
                        <li>
                            <a
                                class='group flex items-center justify-between gap-1 rounded px-2.5 py-1.5 text-2xs font-medium transition hover:bg-foreground/5 [&.active]:bg-primary/5'
                                href="#"
                                @click.prevent="filterAgent('{{ $key }}')"
                                :class="{ active: filters.agent === '{{ $key }}' }"
                            >
                                {{ $filter['label'] }}
                                <x-tabler-check class="hidden size-4 text-primary group-[&.active]:block" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-slot:dropdown>
        </x-dropdown.dropdown>

        <x-dropdown.dropdown
            anchor="end"
            triggerType="click"
            offsetY="10px"
        >
            <x-slot:trigger
                class="size-7 shrink-0 bg-foreground/5 p-0 hover:-translate-y-0.5"
                variant="none"
                title="{{ __('Export Call History') }}"
            >
                <x-tabler-download class="size-4" />
            </x-slot:trigger>

            <x-slot:dropdown class="min-w-44 p-2 text-xs font-medium">
                <ul>
                    <li>
                        <a
                            class='group flex cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-2xs font-medium transition hover:bg-foreground/5'
                            href="#"
                            @click.prevent="exportCallHistory('csv')"
                        >
                            <x-tabler-file-spreadsheet class="size-4" />
                            {{ __('Export as CSV') }}
                        </a>
                    </li>
                    <li>
                        <a
                            class='group flex cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-2xs font-medium transition hover:bg-foreground/5'
                            href="#"
                            @click.prevent="exportCallHistory('json')"
                        >
                            <x-tabler-file-code class="size-4" />
                            {{ __('Export as JSON') }}
                        </a>
                    </li>
                    <li>
                        <a
                            class='group flex cursor-pointer items-center gap-2 rounded px-2.5 py-1.5 text-2xs font-medium transition hover:bg-foreground/5'
                            href="#"
                            @click.prevent="exportCallHistory('pdf')"
                        >
                            <x-tabler-file-text class="size-4" />
                            {{ __('Export as PDF') }}
                        </a>
                    </li>
                </ul>
            </x-slot:dropdown>
        </x-dropdown.dropdown>

        <x-button
            class="size-7 shrink-0 bg-foreground/5"
            size="none"
            variant="none"
            title="{{ __('Filter by Date') }}"
            @click.prevent="Alpine.$data(document.querySelector('#call-history-date-modal')).modalOpen = true;"
            ::class="{ 'bg-primary text-primary-foreground': filters.dateRange.start || filters.dateRange.end }"
        >
            <x-tabler-calendar class="size-4" />
        </x-button>

        <x-button
            class="size-7 shrink-0 bg-foreground/5"
            size="none"
            variant="none"
            title="{{ __('Search') }}"
            @click.prevent="searchFormVisible = !searchFormVisible"
        >
            <x-tabler-search class="size-4" />
        </x-button>
    </div>

    {{-- Search overlay --}}
    <form
        class="absolute start-0 top-0 h-full w-full bg-background"
        action="#"
        @submit.prevent="handleSearch()"
        x-cloak
        x-show="searchFormVisible"
        @keyup.escape.window="searchFormVisible = false"
        x-trap="searchFormVisible"
        x-transition
    >
        <input
            class="lqd-input h-full w-full rounded-none border-none bg-transparent pe-6 ps-10 font-medium text-input-foreground outline-none focus:ring-0 sm:ps-12 lg:pe-12"
            type="search"
            name="call_search"
            placeholder="{{ __('Search by phone number...') }}"
            x-model="filters.search"
        />
        <x-tabler-search class="pointer-events-none absolute start-4 top-1/2 size-[18px] -translate-y-1/2 sm:start-5" />

        <x-button
            class="absolute end-4 top-1/2 size-6 -translate-y-1/2 bg-foreground/15 text-foreground hover:-translate-y-1/2 hover:scale-105"
            hover-variant="danger"
            @click.prevent="filters.search = ''; handleSearch(); searchFormVisible = false"
            size="none"
        >
            <x-tabler-x class="size-4" />
        </x-button>
    </form>
</div>

{{-- Sort row --}}
<div class="flex shrink-0 justify-between gap-1 border-b px-4 py-5 xl:px-6">
    <x-dropdown.dropdown
        class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
        offsetY="10px"
        triggerType="click"
    >
        <x-slot:trigger
            class="gap-2 font-heading text-[12px] font-medium capitalize"
            variant="link"
        >
            <span x-text="filters.sort === 'newest' ? '{{ __('Newest') }}' : '{{ __('Oldest') }}'">
                {{ __('Newest') }}
            </span>
            <x-tabler-chevron-down class="size-4" />
        </x-slot:trigger>

        <x-slot:dropdown class="min-w-44 p-2 text-xs font-medium">
            <ul class="space-y-px">
                @foreach ($sort_filters as $key => $filter)
                    <li>
                        <a
                            @class([
                                'group flex items-center justify-between gap-1 rounded px-2.5 py-1.5 text-2xs font-medium transition hover:bg-foreground/5 [&.active]:bg-primary/5',
                                'active' => $key === 'newest',
                            ])
                            href="#"
                            @click.prevent="filterSort('{{ $key }}')"
                            :class="{ active: filters.sort === '{{ $key }}' }"
                        >
                            {{ $filter['label'] }}
                            <x-tabler-check class="hidden size-4 text-primary group-[&.active]:block" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-slot:dropdown>
    </x-dropdown.dropdown>
</div>

{{-- Status filter --}}
<x-forms.input
    class="h-11 shrink-0 rounded-none !border-x-0 !border-t-0 bg-transparent px-4 !text-[11px] font-semibold uppercase tracking-wide text-foreground/70 focus:border-input-border focus:ring-0 xl:px-6"
    name="call_status"
    size="lg"
    type="select"
    x-model="filters.status"
    @change.prevent="resetAndFetch()"
>
    @foreach ($status_filters as $key => $filter)
        <option
            class="bg-background text-foreground"
            value="{{ $key }}"
        >{{ $filter['label'] }}</option>
    @endforeach
</x-forms.input>

{{-- Provider filter --}}
<x-forms.input
    class="h-11 shrink-0 rounded-none !border-x-0 !border-t-0 bg-transparent px-4 !text-[11px] font-semibold uppercase tracking-wide text-foreground/70 focus:border-input-border focus:ring-0 xl:px-6"
    name="call_provider"
    size="lg"
    type="select"
    x-model="filters.provider"
    @change.prevent="resetAndFetch()"
>
    @foreach ($provider_filters as $key => $filter)
        <option
            class="bg-background text-foreground"
            value="{{ $key }}"
        >{{ $filter['label'] }}</option>
    @endforeach
</x-forms.input>

{{-- Agent filter --}}
<x-forms.input
    class="h-11 shrink-0 rounded-none !border-x-0 bg-transparent px-4 !text-[11px] font-semibold uppercase tracking-wide text-foreground/70 focus:border-input-border focus:ring-0 xl:px-6"
    name="call_agent"
    size="lg"
    type="select"
    x-model="filters.agent"
    @change.prevent="resetAndFetch()"
>
    @foreach ($call_agent_filters as $key => $filter)
        <option
            class="bg-background text-foreground"
            value="{{ $key }}"
        >{{ $filter['label'] }}</option>
    @endforeach
</x-forms.input>

{{-- Date Range Modal --}}
<x-modal
    class:modal-body="p-5"
    class:modal-content="w-[min(100%,400px)]"
    id="call-history-date-modal"
>
    <x-slot:modal>
        <div class="mb-4 [&_.air-datepicker]:w-full">
            <input
                class="hidden"
                id="callHistoryDatepicker"
                type="text"
                x-ref="datepicker"
            />
        </div>

        <div class="flex items-center justify-between gap-3">
            <x-button
                class="flex-1"
                variant="outline"
                @click.prevent="clearDateRange(); modalOpen = false;"
            >
                {{ __('Clear') }}
            </x-button>
            <x-button
                class="flex-1"
                variant="primary"
                @click.prevent="applyDateRange(); modalOpen = false;"
            >
                {{ __('Apply') }}
            </x-button>
        </div>
    </x-slot:modal>
</x-modal>
