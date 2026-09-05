@extends('panel.layout.app', ['disable_tblr' => true, 'disable_titlebar' => true])
@section('title', __('CRM Dashboard'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Overview of your contacts, companies, deals, and tasks.'))

@section('content')
    <div class="py-10">

        @include('crm::dashboard.copilot')

        {{-- @include('crm::dashboard.kpi-stats') --}}

        @include('crm::dashboard.crm-summary')

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-9">
            @include('crm::dashboard.top-contacts')
            @include('crm::dashboard.whats-new')
            @include('crm::dashboard.upcoming-tasks')
            @include('crm::dashboard.recent-activity')
        </div>

    </div>
@endsection
