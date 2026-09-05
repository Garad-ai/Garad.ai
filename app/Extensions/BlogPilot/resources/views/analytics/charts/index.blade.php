<div class="grid grid-cols-1 gap-6">
    @include('blogpilot::analytics.charts.published-posts', ['chartData' => $publishedChartData, 'months' => $publishedMonths])
</div>
