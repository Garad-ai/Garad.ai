@php
    use App\Helpers\Classes\MarketplaceHelper;

    $theme = setting('dash_theme', 'default');
    $fromDashboard = str(request()->route()?->getName() ?? '')->startsWith('dashboard.user.ai-image-pro.');
    $isCreativeSuiteInstalled = MarketplaceHelper::isRegistered('creative-suite');
    $isAdvancedImageInstalled = MarketplaceHelper::isRegistered('advanced-image');
@endphp
@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
    'layout_wide' => true,
])

@section('title', __('AI Image Pro'))
@section('titlebar_actions', '')

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.remove("focus-mode");
            document.body.classList.add('navbar-shrinked');
            localStorage.setItem('lqdNavbarShrinked', true);
        })();
    </script>
@endpush

@push('css')
    <style>
        @media (min-width: 992px) {
            .lqd-header {
                display: none !important;
            }
        }
    </style>
@endpush

@section('content')
    {{-- Background --}}
    <div
        class="pointer-events-none absolute end-[var(--body-padding,0px)] start-[var(--body-padding,0px)] top-[var(--body-padding,0px)] -z-1 overflow-hidden mix-blend-darken blur-[1px] dark:opacity-70 dark:mix-blend-color dark:[mask-image:linear-gradient(to_bottom,black,transparent)] lg:start-[--navbar-width]"
        aria-hidden="true"
    >
        <img
            class="w-full"
            src="{{ custom_theme_url('/vendor/ai-image-pro/images/bg.jpg') }}"
            alt="Background image"
        >
    </div>

    {{-- Main Wrapper --}}
    <div
        class="lqd-adv-img-editor [--header-h:60px] [--sidebar-w:370px] md:pt-[--header-h]"
        x-data="imageProManager"
    >
        <div class="lqd-adv-img-editor-home transition-all">
            <div @class([
                'container',
                'max-sm:px-0' => $theme === 'social-media-dashboard',
            ])>
                @include('ai-image-pro::home.top-navbar')

                @include('ai-image-pro::home.generator-form')
            </div>
        </div>

        @if ($fromDashboard)
            <div class="pb-20">
                <div class="container">
                    @includeWhen(setting('ai-image-pro:show-tools-section', 1), 'ai-image-pro::home.sections.tools')
                </div>

                @include('ai-image-pro::home.sections.user-dashboard')
            </div>

            {{-- Community Gallery for dashboard (hidden by default, shown when viewing user gallery) --}}
            @include('ai-image-pro::home.sections.community-gallery.full')
        @else
            <div class="container">
                @includeWhen(setting('ai-image-pro:show-tools-section', 1), 'ai-image-pro::home.sections.tools')
            </div>

            @includeWhen(setting('ai-image-pro:show-community-section', 1), 'ai-image-pro::home.sections.community-gallery.wrapper')

            @includeWhen($fSectSettings->generators_active == 1, 'ai-image-pro::home.sections.generators')
            @includeWhen($fSectSettings->faq_active == 1, 'ai-image-pro::home.sections.faq')
            @includeWhen($setting->gdpr_status == 1, 'landing-page.gdpr')
            @include('ai-image-pro::home.sections.footer')
        @endif

        {{-- Image Modal Component --}}
        @include('ai-image-pro::home.shared-components.image-modal', ['community_enabled' => true])
    </div>
@endsection

@include('ai-image-pro::includes.scripts.image-pro-manager-script')
