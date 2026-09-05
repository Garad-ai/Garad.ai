@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;

    $platformLabels = collect($platforms)->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()]);
@endphp

<x-card x-data="{
    publishedPosts: {{ json_encode($platforms_published_posts) }},
    currentPlatform: '{{ PlatformEnum::facebook->value }}',
    platformLabels: {{ $platformLabels->toJson() }},
    init() {
        this.updateChart = this.updateChart.bind(this);
    },
    updateChart(platformName) {
        const chart = window.lqdSocialMediaPublishedPostsChart;
        const platform = this.publishedPosts.find(platform => platform.name === platformName);

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
            {{ __('Published Posts') }}
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
                    x-text="platformLabels[currentPlatform] ?? currentPlatform"
                >
                    {{ PlatformEnum::facebook->label() }}
                </span>
                <x-tabler-chevron-down class="size-4" />
            </x-slot:trigger>

            <x-slot:dropdown
                class="p-2"
            >
                @foreach ($platforms as $platform)
                    <x-button
                        class="w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline [&.lqd-is-active]:text-primary [&.lqd-is-active]:underline"
                        data-platform-switcher="{{ $platform->value }}"
                        ::class="{ 'lqd-is-active': currentPlatform === '{{ $platform->value }}' }"
                        variant="link"
                        href="#"
                        @click.prevent="updateChart('{{ $platform->value }}'); currentPlatform = '{{ $platform->value }}';"
                    >
                        {{ $platform->label() }}
                    </x-button>
                @endforeach
            </x-slot:dropdown>
        </x-dropdown.dropdown>
    </x-slot:head>

    <div
        class="min-h-[275px] [&_.apexcharts-legend-text]:!text-foreground"
        id="chart-published-posts"
    ></div>
</x-card>

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>

    <script>
        (() => {
            const publishedPostsChart = {
                series: [{
                    name: '{{ __('Published Posts') }}',
                    data: @json($platforms_published_posts[0]['data'])
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
                    categories: [
                        '{{ __('Jan') }}', '{{ __('Feb') }}', '{{ __('Mar') }}', '{{ __('Apr') }}', '{{ __('May') }}', '{{ __('Jun') }}',
                        '{{ __('Jul') }}', '{{ __('Aug') }}', '{{ __('Sep') }}', '{{ __('Oct') }}', '{{ __('Nov') }}', '{{ __('Dec') }}'
                    ],
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

            window.lqdSocialMediaPublishedPostsChart = new ApexCharts(document.querySelector("#chart-published-posts"), publishedPostsChart);
            window.lqdSocialMediaPublishedPostsChart.render();
        })();
    </script>
@endpush
