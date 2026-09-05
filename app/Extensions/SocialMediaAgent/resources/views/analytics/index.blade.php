@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Reports and Analytics'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __(''))
@section('titlebar_actions')
    @include('social-media-agent::components.titlebar-actions')
@endsection

{{-- loading apex charts lib before loading page components --}}
@push('script')
    <script src="/themes/default/assets/libs/apexcharts/dist/apexcharts.min.js"></script>
@endpush

@section('content')
    <div class="py-10">
        @includeWhen(!empty($news), 'social-media-agent::analytics.news-slideshow', ['news' => $news])
        @include('social-media-agent::analytics.stats-boxes', ['stats' => $stats])
        @include('social-media-agent::analytics.charts.index', ['stats' => $stats, 'platforms' => $platforms])
    </div>
@endsection
