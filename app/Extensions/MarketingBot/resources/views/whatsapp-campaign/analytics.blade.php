@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $title)

@section('titlebar_actions')
    <div class="flex gap-4 lg:justify-end">
        <x-button
            variant="ghost-shadow"
            href="{{ route('dashboard.user.marketing-bot.whatsapp-campaign.index') }}"
        >
            {{ __('All Campaigns') }}
        </x-button>
    </div>
@endsection

@section('content')
    <div class="py-10">
        @if (! $isMeta)
            <x-card>
                <div class="flex flex-col items-center gap-3 py-8 text-center">
                    <x-tabler-chart-bar class="size-10 text-foreground/30" />
                    <p class="text-heading-foreground font-semibold">{{ __('Analytics not available') }}</p>
                    <p class="text-foreground/60 text-sm">{{ __('Analytics are only available when using the Meta WhatsApp provider. Your current channel uses Twilio.') }}</p>
                </div>
            </x-card>
        @else
            <div class="mb-6 flex flex-col gap-1">
                <h2 class="text-heading-foreground text-xl font-semibold">{{ $campaign->name }}</h2>
                <p class="text-foreground/60 text-sm">
                    @if ($campaign->meta_template_name)
                        {{ __('Template:') }} <span class="font-medium">{{ $campaign->meta_template_name }}</span> &middot;
                    @endif
                    {{ __('Status:') }} <span class="font-medium">{{ str()->title($campaign->status->value) }}</span>
                </p>
            </div>

            {{-- Summary Cards --}}
            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-card class="flex flex-col gap-2 p-5">
                    <p class="text-foreground/60 text-xs font-medium uppercase tracking-wide">{{ __('Total Sent') }}</p>
                    <p class="text-heading-foreground text-3xl font-bold">
                        <x-number-counter :number="$stats?->total_sent ?? 0" />
                    </p>
                </x-card>
                <x-card class="flex flex-col gap-2 p-5">
                    <p class="text-foreground/60 text-xs font-medium uppercase tracking-wide">{{ __('Delivered') }}</p>
                    <p class="text-heading-foreground text-3xl font-bold">
                        <x-number-counter :number="$stats?->delivered_count ?? 0" />
                    </p>
                    @if (($stats?->total_sent ?? 0) > 0)
                        <p class="text-foreground/50 text-xs">
                            {{ round(($stats->delivered_count / $stats->total_sent) * 100, 1) }}%
                        </p>
                    @endif
                </x-card>
                <x-card class="flex flex-col gap-2 p-5">
                    <p class="text-foreground/60 text-xs font-medium uppercase tracking-wide">{{ __('Read') }}</p>
                    <p class="text-heading-foreground text-3xl font-bold">
                        <x-number-counter :number="$stats?->read_count ?? 0" />
                    </p>
                    @if (($stats?->total_sent ?? 0) > 0)
                        <p class="text-foreground/50 text-xs">
                            {{ round(($stats->read_count / $stats->total_sent) * 100, 1) }}%
                        </p>
                    @endif
                </x-card>
                <x-card class="flex flex-col gap-2 p-5">
                    <p class="text-foreground/60 text-xs font-medium uppercase tracking-wide">{{ __('Failed') }}</p>
                    <p class="text-heading-foreground text-3xl font-bold">
                        <x-number-counter :number="$stats?->failed_count ?? 0" />
                    </p>
                </x-card>
            </div>

            @if (($stats?->total_sent ?? 0) > 0)
                {{-- Delivery Funnel Chart --}}
                <x-card class="mb-6 p-5">
                    <p class="text-heading-foreground mb-4 font-semibold">{{ __('Delivery Funnel') }}</p>
                    <div id="analyticsChart"></div>
                </x-card>
            @endif

            {{-- Recipients Table --}}
            <x-card class="p-5">
                <p class="text-heading-foreground mb-4 font-semibold">{{ __('Recipients') }}</p>

                @if ($recipients->isEmpty())
                    <p class="text-foreground/50 py-6 text-center text-sm">{{ __('No data yet. Send the campaign to see results here.') }}</p>
                @else
                    <x-table>
                        <x-slot:head>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Sent At') }}</th>
                            <th>{{ __('Delivered At') }}</th>
                            <th>{{ __('Read At') }}</th>
                        </x-slot:head>
                        <x-slot:body>
                            @foreach ($recipients as $row)
                                <tr>
                                    <td>{{ $row->recipient_phone }}</td>
                                    <td>
                                        @php
                                            $statusVariant = match($row->status) {
                                                'read'      => 'success',
                                                'delivered' => 'info',
                                                'failed'    => 'danger',
                                                default     => 'default',
                                            };
                                        @endphp
                                        <x-badge
                                            class="text-3xs"
                                            variant="{{ $statusVariant }}"
                                        >
                                            {{ str()->title($row->status) }}
                                        </x-badge>
                                    </td>
                                    <td class="text-foreground/60 text-sm">{{ $row->sent_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="text-foreground/60 text-sm">{{ $row->delivered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="text-foreground/60 text-sm">{{ $row->read_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </x-slot:body>
                    </x-table>
                    <div class="mt-3 flex justify-end">
                        {{ $recipients->links() }}
                    </div>
                @endif
            </x-card>
        @endif
    </div>
@endsection

@push('script')
    @if ($isMeta && ($stats?->total_sent ?? 0) > 0)
        <script src="{{ custom_theme_url('/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const totalSent = @json($stats->total_sent ?? 0);
                const deliveredCount = @json($stats->delivered_count ?? 0);
                const readCount = @json($stats->read_count ?? 0);
                const failedCount = @json($stats->failed_count ?? 0);
                const sentOnly = totalSent - deliveredCount - failedCount;

                const options = {
                    chart: {
                        type: 'bar',
                        height: 280,
                        toolbar: { show: false },
                        background: 'transparent',
                    },
                    series: [{
                        name: '{{ __('Recipients') }}',
                        data: [totalSent, deliveredCount, readCount, failedCount],
                    }],
                    xaxis: {
                        categories: ['{{ __('Sent') }}', '{{ __('Delivered') }}', '{{ __('Read') }}', '{{ __('Failed') }}'],
                        labels: {
                            style: { colors: 'hsl(var(--foreground) / 0.6)' },
                        },
                    },
                    yaxis: {
                        labels: {
                            style: { colors: 'hsl(var(--foreground) / 0.6)' },
                        },
                    },
                    colors: [
                        'hsl(var(--primary))',
                        'hsl(var(--primary))',
                        'hsl(var(--primary))',
                        '#ef4444',
                    ],
                    plotOptions: {
                        bar: {
                            distributed: true,
                            borderRadius: 4,
                            columnWidth: '50%',
                        },
                    },
                    legend: { show: false },
                    grid: {
                        borderColor: 'hsl(var(--border))',
                    },
                    tooltip: {
                        theme: document.body.classList.contains('theme-dark') ? 'dark' : 'light',
                    },
                    dataLabels: { enabled: false },
                };

                const chart = new ApexCharts(document.querySelector('#analyticsChart'), options);
                chart.render();
            });
        </script>
    @endif
@endpush
