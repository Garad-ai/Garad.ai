@php
    use App\Extensions\SocialMedia\System\Enums\PlatformEnum;
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('AI Social Media Suite'))
@section('subtitle', __('AI Social Media Suite'))

@section('titlebar_actions')
    <x-button
        href="{{ route('dashboard.user.social-media.platforms') }}"
        variant="ghost-shadow"
    >
        @lang('Connect Accounts')
    </x-button>

    @include('social-media::components.create-post-dropdown', ['platforms' => $platforms])
@endsection

@section('content')
    <div class="py-10">
        <div class="space-y-12">
            @include('social-media::components.home.banner')

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                @include('social-media::components.home.platform-cards', ['platforms' => $platforms])
                @include('social-media::components.home.published-posts-chart', ['platforms_published_posts' => $platforms_published_posts])
            </div>

            @include('social-media::components.home.overview-grid', ['posts_stats' => $posts_stats])

            @include('social-media::components.home.posts-grid', ['platforms' => $platforms, 'posts' => $posts])

            @include('social-media::components.home.accounts', ['platforms' => $platforms])

            @include('social-media::components.home.tools')
        </div>

        {{-- blade-formatter-disable --}}
        <svg class="absolute h-0 w-0" width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" > <defs> <linearGradient id="social-posts-overview-gradient" x1="9.16667" y1="15.1507" x2="32.6556" y2="31.9835" gradientUnits="userSpaceOnUse" > <stop stop-color="hsl(var(--gradient-from))" /> <stop offset="0.502" stop-color="hsl(var(--gradient-via))" /> <stop offset="1" stop-color="hsl(var(--gradient-to))" /> </linearGradient> </defs> </svg>
		{{-- blade-formatter-enable --}}
    </div>
@endsection
