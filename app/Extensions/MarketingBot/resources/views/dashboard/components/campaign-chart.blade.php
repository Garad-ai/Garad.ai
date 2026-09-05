@php
    use App\Extensions\MarketingBot\System\Enums\CampaignType;
@endphp

<x-card x-data="{
    publishedCampaigns: {{ json_encode($data) }},
    currentPlatform: '{{ CampaignType::whatsapp }}',
    init() {
        this.updateChart = this.updateChart.bind(this);
    },
    updateChart(platformName) {
        const chart = window.lqdCampaignChart;
        const platform = this.publishedCampaigns.find(platform => platform.name === platformName);

        if (!chart || !platform) return;

        chart.updateSeries([{
            name: platform.name,
            data: platform.data
        }]);
    }
}">
    <x-slot:head
        class="flex items-center justify-between gap-3"
    >
        <h4 class="m-0 text-base font-medium">
            {{ __('Campaigns') }}
        </h4>
        <x-dropdown.dropdown
            anchor="end"
            offsetY="15px"
        >
            <x-slot:trigger
                class="text-2xs"
            >
                <span
                    class="capitalize"
                    x-text="currentPlatform"
                >
                    {{ CampaignType::whatsapp->name }}
                </span>
                <x-tabler-chevron-down class="size-4" />
            </x-slot:trigger>

            <x-slot:dropdown
                class="p-2"
            >
                @foreach (CampaignType::cases() as $type)
                    <x-button
                        class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline [&.lqd-is-active]:text-primary [&.lqd-is-active]:underline"
                        data-platform-switcher="{{ $type->name }}"
                        ::class="{ 'lqd-is-active': currentPlatform === '{{ $type->name }}' }"
                        variant="link"
                        href="#"
                        @click.prevent="updateChart('{{ $type->name }}'); currentPlatform = '{{ $type->name }}';"
                    >
                        {{ Str::title($type->name) }}
                    </x-button>
                @endforeach
            </x-slot:dropdown>
        </x-dropdown.dropdown>
    </x-slot:head>

    <div
        class="min-h-[275px] [&_.apexcharts-legend-text]:!text-foreground"
        id="chart-campaigns"
    ></div>
</x-card>

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>

    <script>
        (() => {
            const publishedCampaignsChart = {
                series: [{
                    name: '{{ __('Published Campaigns') }}',
                    data: @json($data[0]['data'])
                }],
                chart: {
                    type: 'line',
                    height: 260,
                },
                colors: ['hsl(var(--primary))'],
                grid: {
                    show: false,
                },
                stroke: {
                    show: false,
                },
                xaxis: {
                    categories: @json($lastTenDays),
                    labels: {
                        offsetY: 0,
                        style: {
                            colors: 'hsl(var(--foreground) / 40%)',
                            fontSize: '10px',
                            fontFamily: 'inherit',
                            fontWeight: 500,
                        },
                    },
                    axisBorder: {
                        show: false,
                    },
                    axisTicks: {
                        show: false,
                    },
                },
                yaxis: {
                    labels: {
                        offsetX: -15,
                        style: {
                            colors: 'hsl(var(--foreground) / 40%)',
                            fontSize: '10px',
                            fontFamily: 'inherit',
                            fontWeight: 500,
                        },
                    },
                    axisBorder: {
                        show: false,
                    },
                    axisTicks: {
                        show: false,
                    },
                },
                tooltip: {
                    x: {
                        format: 'dd MMM yyyy'
                    }
                },
                stroke: {
                    width: 2,
                    curve: 'smooth'
                },
            };

            window.lqdCampaignChart = new ApexCharts(document.querySelector("#chart-campaigns"), publishedCampaignsChart);
            window.lqdCampaignChart.render();
        })();
    </script>
@endpush
