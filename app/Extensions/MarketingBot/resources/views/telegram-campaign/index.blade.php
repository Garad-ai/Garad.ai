@php
    use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $title)

@section('titlebar_actions')

    <div class="flex gap-4 lg:justify-end">
        <x-button href="{{ route('dashboard.user.marketing-bot.telegram-campaign.create') }}">
            <x-tabler-plus class="size-4" />
            {{ __('Add campaign') }}
        </x-button>
    </div>
@endsection

@section('content')
    <div class="py-10">
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
                    {{ __('Schedule at') }}
                </th>

                <th class="text-end">
                    {{ __('Action') }}
                </th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($items as $item)
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
                            {{ $item->scheduled_at ? $item->scheduled_at->format('Y-m-d H:i') : __('Not scheduled') }}
                        </td>
                        <td class="whitespace-nowrap text-end">
                            @if ($app_is_demo)
                                <x-button
                                    class="size-9"
                                    variant="ghost-shadow"
                                    size="none"
                                    onclick="return toastr.info('This feature is disabled in Demo version.')"
                                    title="{{ __('Edit') }}"
                                >
                                    <x-tabler-edit class="size-4" />
                                </x-button>
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
                                {{--								<x-button --}}
                                {{--									href="{{ route('dashboard.user.marketing-bot.train.index', $item->id) }}" --}}
                                {{--									class="size-9" --}}
                                {{--									variant="ghost-shadow" --}}
                                {{--									size="none" --}}
                                {{--									title="{{ __('Training') }}" --}}
                                {{--								> --}}
                                {{--									<x-tabler-transform class="size-4" /> --}}
                                {{--								</x-button> --}}
                                <x-button
                                    class="size-9"
                                    href="{{ route('dashboard.user.marketing-bot.telegram-campaign.edit', $item->id) }}"
                                    variant="ghost-shadow"
                                    size="none"
                                    title="{{ __('Edit') }}"
                                >
                                    <x-tabler-edit class="size-4" />
                                </x-button>
                                <x-button
                                    class="size-9"
                                    data-delete="delete"
                                    data-delete-link="{{ route('dashboard.user.marketing-bot.telegram-campaign.destroy', $item->id) }}"
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
            {{ $items->links() }}
        </div>
    </div>
@endsection

@push('script')
    <script>
        $('[data-delete="delete"]').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this campaign?')) {
                return;
            }

            let deleteLink = $(this).data('delete-link');

            $.ajax({
                url: deleteLink,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(data) {
                    if (data.status === 'success') {
                        toastr.success(data.message);

                        setTimeout(function() {
                            window.location.reload();
                        }, 600);

                        return;
                    }

                    if (data.message) {
                        toastr.error(data.message);
                        return;
                    }

                    toastr.error('Something went wrong!');
                },
                error: function(e) {
                    if (e?.responseJSON?.message) {
                        toastr.error(e.responseJSON.message);
                    } else {
                        toastr.error('Something went wrong!');
                    }
                }
            });
        });
    </script>
@endpush
