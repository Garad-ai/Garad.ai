<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @include('social-media-agent::analytics.charts.published-posts', ['chartData' => $publishedChartData, 'months' => $publishedMonths])
    @include('social-media-agent::analytics.charts.engagement-rate', ['chartData' => $engagementChartData, 'months' => $engagementMonths])
    @include('social-media-agent::analytics.charts.impressions', ['chartData' => $impressionsChartData, 'months' => $impressionsMonths])
    @include('social-media-agent::analytics.charts.audience-growth', ['chartData' => $audienceChartData, 'months' => $audienceMonths])
</div>
