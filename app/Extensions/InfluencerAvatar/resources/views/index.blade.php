@extends('panel.layout.app', [
    'disable_tblr' => true,
])

@section('title', __('AI Influencer Avatar'))
@section('titlebar_subtitle', __('Generate captivating influencer video content for Reels, TikTok, and Shorts.'))
@section('titlebar_actions', '')

@section('content')
    <div class="py-10">
        <div
            class="lqd-external-chatbot-edit"
            x-data="aiInflucencerData"
        >
            @include('influencer-avatar::social-video-window.social-video-window', ['overlay' => false])
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiInflucencerData', () => ({
                influencerAvatarWindowKey: 0,
                init() {
                    Alpine.store('aiInflucencerData', this);
                    this.toggleWindow();
                },
                // when open or close the window, change some neccessary css.
                toggleWindow(open = true) {
                    Alpine.nextTick(() => {
                        this.influencerAvatarWindowKey = 1;
                    })

                    const topNoticeBar = document.querySelector('.top-notice-bar');
                    const navbar = document.querySelector('.lqd-navbar');
                    const pageContentWrap = document.querySelector('.lqd-page-content-wrap');

                    if (window.innerWidth >= 992) {

                        if (navbar) {
                            navbar.style.position = open ? 'fixed' : '';
                        }

                        if (pageContentWrap && navbar?.offsetWidth > 0) {
                            pageContentWrap.style.paddingInlineStart = open ? 'var(--navbar-width)' : '';
                        }

                        if (topNoticeBar) {
                            topNoticeBar.style.visibility = open ? 'hidden' : '';
                        }

                        if (navbarExpander) {
                            navbarExpander.style.visibility = open ? 'hidden' : '';
                            navbarExpander.style.opacity = open ? 0 : 1;
                        }
                    }
                }
            }))
        });
    </script>
@endpush
