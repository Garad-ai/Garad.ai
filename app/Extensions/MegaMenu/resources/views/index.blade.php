@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Mega Menus'))
@section('titlebar_actions')
    <x-button
        href="{{ $app_is_demo ? '#' : route('dashboard.admin.mega-menu.create') }}"
        onclick="{{ $app_is_demo ? 'return toastr.info(\'This feature is disabled in Demo version.\')' : '' }}"
        variant="primary"
    >
        <x-tabler-plus class="size-4" />
        {{ __('Add Mega Menu') }}
    </x-button>
@endsection

@section('content')
    <div class="py-10">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>
                        {{ __('Name') }}
                    </th>
                    {{--					<th> --}}
                    {{--						{{ __('status') }} --}}
                    {{--					</th> --}}
                    <th class="text-end">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </x-slot:head>

            <x-slot:body>
                @foreach ($items as $item)
                    <tr>
                        <td>
                            {{ $item->name }}
                        </td>
                        {{--						<td> --}}
                        {{--							{{ $item->status }} --}}
                        {{--						</td> --}}
                        <td class="whitespace-nowrap text-end">
                            <x-button
                                class="size-9"
                                href="{{ route('dashboard.admin.mega-menu.items', $item->id) }}"
                                title="{{ __('Menus') }}"
                                variant="warning"
                                size="none"
                            >
                                <x-tabler-menu-2 class="size-4" />
                            </x-button>
                            <x-button
                                class="size-9"
                                href="{{ route('dashboard.admin.mega-menu.edit', $item->id) }}"
                                title="{{ __('Send') }}"
                                variant="success"
                                size="none"
                            >
                                <x-tabler-edit class="size-4" />
                            </x-button>
                            <x-button
                                class="size-9"
                                variant="ghost-shadow"
                                hover-variant="danger"
                                size="none"
                                href="{{ route('dashboard.admin.mega-menu.destroy', $item->id) }}"
                                onclick="return confirm('Are you sure? This is permanent and will delete all documents related to email template.')"
                                title="{{ __('Delete') }}"
                            >
                                <x-tabler-x class="size-4" />
                            </x-button>
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-table>
    </div>
@endsection

@push('script')
@endpush
