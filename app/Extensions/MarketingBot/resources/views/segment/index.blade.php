@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Segments'))

@section('titlebar_actions')
    <x-modal
        class:modal-body="pt-0"
        class:modal-head="border-none"
    >
        <x-slot:trigger>
            <x-tabler-plus class="size-4" />
            @lang('Add Segment')
        </x-slot:trigger>

        <x-slot:modal>
            <h3 class="mb-3">
                @lang('Create a Segment')
            </h3>
            <p class="mb-8 max-w-[520px] font-medium text-heading-foreground/70">
                @lang('Create a new segment for your whatsapp.')
            </p>
            <form
                class="space-y-5"
                id="submit"
                action="{{ route('dashboard.user.marketing-bot.segment.store') }}"
                method="post"
            >
                @csrf
                <x-forms.input
                    class:label="text-heading-foreground"
                    id="cam_name"
                    name="name"
                    label="{{ __('Segment') }}"
                    required
                    size="lg"
                />
                <x-forms.input
                    id="status"
                    name="status"
                    type="checkbox"
                    switcher
                    type="checkbox"
                    label="{{ __('Status') }}"
                />
                <x-button
                    class="w-full"
                    type="submit"
                    variant="secondary"
                >
                    @lang('Add')
                    <span class="inline-grid size-7 place-content-center rounded-full bg-background">
                        <x-tabler-chevron-right class="size-4" />
                    </span>
                </x-button>
            </form>
        </x-slot:modal>
    </x-modal>
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
                                <x-button
                                    class="size-9"
                                    href="{{ route('dashboard.user.marketing-bot.segment.edit', $item->id) }}"
                                    variant="ghost-shadow"
                                    size="none"
                                    title="{{ __('Delete') }}"
                                >
                                    <x-tabler-edit class="size-4" />
                                </x-button>
                                <x-button
                                    class="size-9"
                                    data-delete="delete"
                                    data-delete-link="{{ route('dashboard.user.marketing-bot.segment.destroy', $item->id) }}"
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
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            "use strict";
            $('#submit').on('submit', function(e) {
                e.preventDefault();
                let form = $(this);
                let data = $(this).serialize();

                $.post(form.attr('action'), data, function(data) {}).done(function(data) {
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
                }).fail(function(e) {
                    if (e?.responseJSON?.message) {
                        toastr.error(e.responseJSON.message);
                    } else {
                        toastr.error('Something went wrong!');
                    }
                });
            });

            $('[data-delete="delete"]').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this contact?')) {
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
        });
    </script>
@endpush
