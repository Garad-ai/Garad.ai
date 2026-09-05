<div
    class="mt-10"
    x-data="socialMediaAgentAccounts"
>
    <h2 class="mb-7">
        @lang('Manage Accounts')
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
            placeholder="{{ __('Search for campaigns') }}"
            x-model="_searchStr"
        />
    </form>

    @if (filled($platforms))
        <x-table>
            <x-slot:head>
                <th>
                    {{ __('Name / Username') }}
                </th>

                <th>
                    {{ __('Registered') }}
                </th>

                <th>
                    {{ __('Status') }}
                </th>

                <th>
                    {{ __('Platform') }}
                </th>

                <th class="text-end">
                    {{ __('Actions') }}
                </th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($platforms as $platform)
                    @include('social-media-agent::accounts.platform-table-item', ['platform' => $platform])
                @endforeach
            </x-slot:body>
        </x-table>
    @else
        <h4>
            {{ __('No agents added yet.') }}
        </h4>
    @endif
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('socialMediaAgentAccounts', () => ({
                _searchStr: '',

                get searchString() {
                    return this._searchStr.trim().toLowerCase()
                }
            }))
        })
    </script>
@endpush
