@php
    $theme = get_theme();
@endphp

@extends('panel.layout.app', ['disable_tblr' => true, 'disable_tblr' => true, 'layout_wide' => true])
@section('title')
    {{ __('Welcome') }}, {{ auth()->user()->name }}.
@endsection
@section('titlebar_subtitle', __('Manage your AI-powered BlogPilot Agents'))
@section('titlebar_actions')
    @include('blogpilot::components.titlebar-actions')
@endsection

@push('after-body-open')
    <script>
        (() => {
            localStorage.setItem('lqdNavbarShrinked', true);
            document.body.classList.add("navbar-shrinked");
        })();
    </script>
@endpush

@push('css')
    <link
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
        rel="stylesheet"
    />

    <style>
        @keyframes slide-up {
            from {
                transform: translateY(100px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .animate-slide-up {
            animation: slide-up 0.3s ease-out;
        }
    </style>
@endpush

@section('content')
    <div @class(['px-5', 'py-5' => $theme !== 'blogpilot-dashboard'])>
        <div x-data="blogPilotPosts">
            @include('blogpilot::dashboard.banner', [
                'total_posts_count' => $total_posts_count,
                'scheduled_posts_count' => $scheduled_posts_count,
                'pending_posts_count' => $pending_posts_count,
                'new_posts' => $new_posts,
                'new_impressions' => $new_impressions,
                'default_agent_id' => $defaultAgent->id ?? null,
                'generation_status' => $generation_status ?? ['status' => 'idle'],
            ])

            @include('blogpilot::dashboard.calendar')

            @include('blogpilot::components.posts.carousel.posts-container', ['posts' => $posts])
        </div>
    </div>
@endsection

@push('script')
    @include('blogpilot::components.posts.posts-script', [
        'platforms_with_image' => '',
        'total_posts_count' => $total_posts_count,
        'scheduled_posts_count' => $scheduled_posts_count,
        'pending_posts_count' => $pending_posts_count,
        'default_agent_id' => $defaultAgent->id ?? null,
        'generation_status' => $generation_status ?? ['status' => 'idle'],
    ])

    <script>
        // Check for pending posts count and update badge
        function updatePendingCount() {
            fetch('{{ route('dashboard.user.blogpilot.agent.api.pending-count') }}', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        const badge = document.getElementById('pending-count-badge');
                        if (badge) {
                            badge.textContent = data.count;
                            badge.classList.remove('hidden');
                        }
                    }
                })
                .catch(error => console.error('Error fetching pending count:', error));
        }

        // Update count on page load
        updatePendingCount();

        // Update count every 30 seconds
        setInterval(updatePendingCount, 30000);

        // Listen for broadcast notifications (if Echo is available)
        if (typeof Echo !== 'undefined') {
            Echo.private('App.Models.User.{{ Auth::id() }}')
                .notification((notification) => {
                    if (notification.type === 'post_generation_completed') {
                        const message = notification.message || '{{ __('Posts generation completed!') }}';
                        toastr.success(message);
                        setTimeout(updatePendingCount, 1000);
                    }
                });
        }
    </script>
@endpush
