<div class="flex h-screen flex-col">
    <x-table>
        <x-slot:head>
            <tr>
                <th>
                    <button
                        class="table-sort"
                        data-sort="sort-name"
                    >
                        {{ __('Name') }}
                    </button>
                </th>
                <th>
                    <button
                        class="table-sort"
                        data-sort="sort-status"
                    >
                        {{ __('Status') }}
                    </button>
                </th>
                <th>
                    <button
                        class="table-sort"
                        data-sort="sort-end-date"
                    >
                        {{ __('END DATE') }}
                    </button>
                </th>
                <th>
                    <button
                        class="table-sort"
                        data-sort="sort-type"
                    >
                        {{ __('TYPE') }}
                    </button>
                </th>
                <th>
                    <button
                        class="table-sort"
                        data-sort="sort-discount"
                    >
                        {{ __('DISCOUNT') }}
                    </button>
                </th>
                <th class="text-end">
                    {{ __('Actions') }}
                </th>
            </tr>
        </x-slot:head>

        <x-slot:body
            class="text-xs"
            id="discounts-list"
            x-data="discountTableData"
        >
            @forelse ($discounts as $discount)
                @php
                    $end_date = \Carbon\Carbon::parse($discount->discountable?->end_date);
                @endphp
                <tr data-discount-id="{{ $discount->id }}">
                    <td class="sort-name">
                        {{ $discount->discountable?->title }}
                    </td>
                    <td data-sort="sort-status">
                        <p @class([
                            'mb-0 mt-auto inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[12px] font-medium leading-none',
                            'text-green-500' => $discount->discountable?->active,
                            'text-yellow-800' => !$discount->discountable?->active,
                        ])>
                            @if ($discount?->discountable?->active)
                                <x-tabler-check class="size-4" />
                                @lang('Active')
                            @else
                                <x-tabler-clock-hour-4 class="size-4" />
                                @lang($end_date->isPast() ? 'Expired' : 'Scheduled')
                            @endif
                        </p>
                    </td>
                    <td data-sort="sort-end-date">
                        <span class="text-nowrap text-heading-foreground">
							@if(is_null($discount->discountable?->end_date))
								-
							@else
								{{ $end_date->format('M d, Y') }}
								<span class="text-foreground/50">{{ $end_date->format('H:i') }}</span>
							@endif
                        </span>
                    </td>
                    <td data-sort="sort-type">
                        {{ $discount->type() }}
                    </td>
                    <td data-sort="sort-discount">
                        @if ($discount->type() == 'Discount')
                            <span class="text-nowrap text-heading-foreground">
                                {{ ($discount->discountable?->type == \App\Extensions\DiscountManager\System\Enums\DiscountTypeEnum::PERCENTAGE->value ? '' : currency()->symbol . ' ') . $discount->discountable?->amount . ($discount->discountable?->type == App\Extensions\DiscountManager\System\Enums\DiscountTypeEnum::PERCENTAGE->value ? '%' : '') }}
                                <span class="text-foreground/50">{{ $discount->discountable?->type }}</span>
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="flex flex-nowrap items-center justify-end gap-3 text-end">
						<x-button
							class="size-9"
							variant="ghost-shadow"
							size="none"
							href="{{ route('dashboard.admin.discount-manager.' . ($discount->type() === 'Discount' ? 'discount' : 'banner'), $discount->id) }}"
							title="{{ __('Edit') }}"
						>
							<x-tabler-pencil class="size-4" />
						</x-button>
                        @if ($app_is_demo)
                            <x-dropdown.dropdown
                                anchor="end"
                                offsetY="15px"
                            >
                                <x-slot:trigger>
                                    <x-tabler-dots-vertical class="size-4" />
                                </x-slot:trigger>

                                <x-slot:dropdown
                                    class="p-2"
                                >
                                    <x-button
                                        class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline"
                                        variant="link"
                                        onclick="return toastr.info('This feature is disabled in Demo version.')"
                                    >
                                        <x-tabler-box-multiple class="size-4" />
                                        @lang('Duplicate')
                                    </x-button>
                                    <x-button
                                        class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline"
                                        variant="link"
                                        onclick="return toastr.info('This feature is disabled in Demo version.')"
                                    >
                                        <x-tabler-trash class="size-4" />
                                        @lang('Delete')
                                    </x-button>
                                </x-slot:dropdown>
                            </x-dropdown.dropdown>
                        @else
                            <x-dropdown.dropdown
                                anchor="end"
                                offsetY="15px"
                            >
                                <x-slot:trigger>
                                    <x-tabler-dots-vertical class="size-4" />
                                </x-slot:trigger>

                                <x-slot:dropdown
                                    class="p-2"
                                >
                                    <x-button
                                        class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline"
                                        variant="link"
                                        @click="duplicate({{ $discount->id }})"
                                    >
                                        <x-tabler-box-multiple class="size-4" />
                                        @lang('Duplicate')
                                    </x-button>
                                    <x-button
                                        class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline"
                                        variant="link"
                                        @click="confirm('{{ __('Are you sure you want to delete this discount?') }}') ? discountDelete({{ $discount->id }}) : ''"
                                    >
                                        <x-tabler-trash class="size-4" />
                                        @lang('Delete')
                                    </x-button>
                                </x-slot:dropdown>
                            </x-dropdown.dropdown>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        class="text-center"
                        colspan="8"
                    >
                        {{ __('No discounts found.') }}
                    </td>
                </tr>
            @endforelse
        </x-slot:body>
    </x-table>
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('discountTableData', () => ({
                // actions
                async duplicate(discount_id) {
                    try {
                        Alpine.store('appLoadingIndicator').show();
                        const res = await fetch('{{ route('dashboard.admin.discount-manager.discount-duplicate') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                'discount_id': discount_id
                            })
                        })

                        const resJson = await res.json();

                        if (!res.ok || resJson?.status == 'error') {
                            throw new Error(resJson?.message || '{{ __('Something went wrong. Please reload the page and try it again') }}')
                        }

                        toastr.success('{{ __('Duplicate successfully!') }}');
                        Alpine.store('appLoadingIndicator').hide();

                        setTimeout(() => {
                            location.reload();
                        }, 200);
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error.message || error);
                        Alpine.store('appLoadingIndicator').hide();
                    }
                },
                async discountDelete(discount_id) {
                    try {
                        Alpine.store('appLoadingIndicator').show();
                        const res = await fetch('{{ route('dashboard.admin.discount-manager.discount-delete') }}', {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                'discount_id': discount_id
                            })
                        })

                        const resJson = await res.json();

                        if (!res.ok || resJson?.status == 'error') {
                            throw new Error(resJson?.message || '{{ __('Something went wrong. Please reload the page and try it again') }}')
                        }

                        toastr.success('{{ __('Delete successfully!') }}');
                        Alpine.store('appLoadingIndicator').hide();

                        document.querySelector(`[data-discount-id="${discount_id}"]`)?.remove();
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error.message || error);
                        Alpine.store('appLoadingIndicator').hide();
                    }
                }
            }));
        })
    </script>
@endpush
