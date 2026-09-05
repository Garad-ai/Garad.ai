@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Reports and Analytics'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __(''))
@section('titlebar_actions')
    @include('blogpilot::components.titlebar-actions')
@endsection

{{-- loading apex charts lib before loading page components --}}
@push('script')
    <script src="/themes/default/assets/libs/apexcharts/dist/apexcharts.min.js"></script>
@endpush

@section('content')
    <div class="py-10">
        @includeWhen(!empty($news), 'blogpilot::analytics.news-slideshow', ['news' => $news])
        @include('blogpilot::analytics.stats-boxes', ['stats' => $stats])
        @include('blogpilot::analytics.charts.index', ['stats' => $stats, 'agents' => $agents])
    </div>
@endsection
