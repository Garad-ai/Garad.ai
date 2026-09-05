<div
    class="mt-10"
    x-data="aiAgentConnectors"
>
    <h2 class="mb-7">
        @lang('Manage Connectors')
    </h2>

    <form class="relative mb-9 w-full">
        <x-tabler-search
            class="absolute start-6 top-1/2 -translate-y-1/2 text-heading-foreground"
            stroke-width="1.5"
        />
        <x-forms.input
            class="h-[52px] rounded-full bg-foreground/5 ps-14 text-foreground placeholder:text-foreground"
            name="search"
            type="text"
            placeholder="{{ __('Search connectors') }}"
            x-model="_searchStr"
        />
    </form>

    @if ($connectors->isNotEmpty())
        <x-table>
            <x-slot:head>
                <th>{{ __('Account') }}</th>
                <th>{{ __('Connected') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Service') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($connectors as $connector)
                    @include('ai-agent-gmail::connectors.connector-table-item', compact('connector'))
                @endforeach
            </x-slot:body>
        </x-table>
    @else
        <x-empty-state
            class="py-10"
            icon="tabler-plug-x"
            :title="__('No connectors yet')"
            :description="__('Connect a service above to get started.')"
        />
    @endif
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiAgentConnectors', () => ({
                _searchStr: '',

                get searchString() {
                    return this._searchStr.trim().toLowerCase()
                }
            }))
        })
    </script>
@endpush
