@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('MarketingBot'))
@section('subtitle', __('MarketingBot'))
@section('titlebar_subtitle', __('Smart automations across WhatsApp, Telegram and more.'))
@section('titlebar_actions')
    <x-dropdown.dropdown
        anchor="end"
        offsetY="13px"
    >
        <x-slot:trigger
            variant="none"
        >
            @lang('View Campaigns')
        </x-slot:trigger>

        <x-slot:dropdown
            class="min-w-52 overflow-hidden p-2"
        >
            <x-button
                @class([
                    'w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline',
                ])
                variant="link"
                href="{{ route('dashboard.user.marketing-bot.whatsapp-campaign.index') }}"
            >
                <img
                    class="h-auto w-6"
                    src="{{ asset('vendor/marketing-bot/images/whatsapp.png') }}"
                    alt="{{ 'whatsapp' }}"
                />
                WhatsApp
            </x-button>
            <x-button
                @class([
                    'w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline',
                ])
                variant="link"
                href="{{ route('dashboard.user.marketing-bot.telegram-campaign.index') }}"
            >
                <img
                    class="h-auto w-6"
                    src="{{ asset('vendor/marketing-bot/images/telegram.png') }}"
                    alt="{{ 'telegram' }}"
                />
                Telegram
            </x-button>

        </x-slot:dropdown>
    </x-dropdown.dropdown>
    <x-dropdown.dropdown
        anchor="end"
        offsetY="13px"
    >
        <x-slot:trigger
            variant="primary"
        >
            <x-tabler-plus class="size-4" />
            @lang('Create New Campaign')
        </x-slot:trigger>

        <x-slot:dropdown
            class="min-w-52 overflow-hidden p-2"
        >
            <x-button
                @class([
                    'w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline',
                ])
                variant="link"
                href="{{ route('dashboard.user.marketing-bot.whatsapp-campaign.create') }}"
            >
                <img
                    class="h-auto w-6"
                    src="{{ asset('vendor/marketing-bot/images/whatsapp.png') }}"
                    alt="{{ 'whatsapp' }}"
                />
                WhatsApp
            </x-button>
            <x-button
                @class([
                    'w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline',
                ])
                variant="link"
                href="{{ route('dashboard.user.marketing-bot.telegram-campaign.create') }}"
            >
                <img
                    class="h-auto w-6"
                    src="{{ asset('vendor/marketing-bot/images/telegram.png') }}"
                    alt="{{ 'telegram' }}"
                />
                Telegram
            </x-button>

        </x-slot:dropdown>
    </x-dropdown.dropdown>
@endsection

@section('content')
    <div class="py-10">
        <div class="space-y-12">
            @include('marketing-bot::dashboard.components.banner')
        </div>

        <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
            @include('marketing-bot::dashboard.components.campaign-chart', ['data' => $chartCampaigns])
            @include('marketing-bot::dashboard.components.new-contact-chart', ['data' => $chartNewContacts])
        </div>

        @include('marketing-bot::dashboard.components.overview-grid', ['items' => $totals])
        @include('marketing-bot::dashboard.components.list')

        {{-- blade-formatter-disable --}}
        <svg class="absolute h-0 w-0" width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" > <defs> <linearGradient id="social-posts-overview-gradient" x1="9.16667" y1="15.1507" x2="32.6556" y2="31.9835" gradientUnits="userSpaceOnUse" > <stop stop-color="hsl(var(--gradient-from))" /> <stop offset="0.502" stop-color="hsl(var(--gradient-via))" /> <stop offset="1" stop-color="hsl(var(--gradient-to))" /> </linearGradient> </defs> </svg>
		{{-- blade-formatter-enable --}}
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
