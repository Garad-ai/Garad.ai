@php
    $availableConnectors = [
        [
            'type'        => 'gmail',
            'label'       => 'Gmail',
            'description' => __('Send emails, manage labels, create drafts, and more.'),
            'icon'        => 'tabler-brand-gmail',
            'route'       => route('dashboard.user.ai-agent.connectors.gmail.redirect'),
        ],
    ];

    if (class_exists(\App\Extensions\AIAgentOutlook\System\AIAgentOutlookServiceProvider::class)) {
        $availableConnectors[] = [
            'type'        => 'outlook',
            'label'       => 'Outlook',
            'description' => __('Send emails, manage calendar events, contacts, and more.'),
            'icon'        => 'tabler-brand-office',
            'route'       => route('dashboard.user.ai-agent.connectors.outlook.redirect'),
        ];
    }
@endphp

<div>
    <h2 class="mb-7">
        @lang('Add New Connector')
    </h2>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
        @foreach ($availableConnectors as $connector)
            <x-card
                class="flex flex-col text-heading-foreground transition-all hover:scale-105 hover:border-heading-foreground/10 hover:shadow-lg hover:shadow-black/5"
                class:body="flex flex-col"
            >
                <figure class="mb-8 flex size-10 items-center justify-center rounded-xl bg-primary/10 transition-all group-hover/card:scale-125">
                    @svg($connector['icon'], 'size-5 text-primary')
                </figure>

                <h4 class="mb-2 text-lg text-inherit">
                    {{ $connector['label'] }}
                </h4>

                <p class="mb-4 text-2xs text-foreground/60">
                    {{ $connector['description'] }}
                </p>

                <a
                    class="relative z-10 text-2xs opacity-70 hover:opacity-100"
                    href="{{ $connector['route'] }}"
                    onclick="{{ $app_is_demo ? 'toastr.error(\''. __('This feature is disabled in demo mode.') .'\'); return false;' : '' }}"
                >
                    @lang('Connect')
                </a>

                <a
                    class="absolute inset-0 z-2 inline-block"
                    href="{{ $connector['route'] }}"
                    onclick="{{ $app_is_demo ? 'toastr.error(\''. __('This feature is disabled in demo mode.') .'\'); return false;' : '' }}"
                ></a>
            </x-card>
        @endforeach
    </div>
</div>
