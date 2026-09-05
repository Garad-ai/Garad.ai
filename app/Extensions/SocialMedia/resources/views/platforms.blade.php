@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Social Media Accounts'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('You can connect and manage multiple social media accounts from here.'))

@section('content')
    <div class="py-10">
        @include('social-media::platforms.platform-statistics', ['items' => $userPlatforms])
        @include('social-media::platforms.platform-cards', ['platforms' => $platforms])
        @include('social-media::platforms.platform-table', ['items' => $userPlatforms])
    </div>
@endsection
