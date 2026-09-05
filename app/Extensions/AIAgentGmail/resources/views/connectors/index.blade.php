@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Connectors'))
@section('titlebar_subtitle', __('Connect third-party services to use in your AI Agent workflows.'))

@section('content')
    <div class="px-5 py-5">
        @include('ai-agent-gmail::connectors.available-connectors')
        @include('ai-agent-gmail::connectors.connected-table', ['connectors' => $connectors])
    </div>
@endsection
