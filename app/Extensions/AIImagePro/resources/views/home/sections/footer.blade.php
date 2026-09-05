@php
    use App\Helpers\Classes\MarketplaceHelper;
@endphp

@if (setting('ai-image-pro:show-footer', 1))
    <div class="relative pb-40">
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 z-0 h-full w-full overflow-hidden mix-blend-darken dark:opacity-80 dark:mix-blend-color dark:[mask-image:linear-gradient(to_bottom,transparent,black)]">
            <img
                class="absolute inset-0 size-full object-contain object-bottom"
                src="{{ custom_theme_url('/vendor/ai-image-pro/images/footer-bg.jpg') }}"
                alt=""
                aria-hidden="true"
            >
        </div>
        <div class="container">
            <hr class="mb-20">
        </div>

        <div class="container relative z-1 max-w-5xl">
            @php

                $showSocialIcons = (bool) setting('ai-image-pro:footer-show-social', 1);
                $customCopyright = setting('ai-image-pro:footer-copyright', '');

                // Social media accounts
                $socialAccounts = \App\Models\SocialMediaAccounts::where('is_active', true)->get();

                // Get footer columns configuration
                $footerColumns = json_decode(setting('ai-image-pro:footer-columns', '[]'), true) ?: [];

                // Default columns if none configured
                if (empty($footerColumns)) {
                    $footerColumns = [
                        ['title' => 'Models', 'enabled' => true, 'source' => 'models', 'links' => []],
                        ['title' => 'Editor', 'enabled' => true, 'source' => 'editor', 'links' => []],
                        ['title' => 'Tools', 'enabled' => true, 'source' => 'tools', 'links' => []],
                        ['title' => 'Company', 'enabled' => true, 'source' => 'pages', 'links' => []],
                    ];
                }

                // Filter only enabled columns
                $enabledColumns = collect($footerColumns)->filter(fn($col) => $col['enabled'] ?? false);

                // Pre-fetch data for automatic sources
                $modelsData = \App\Extensions\AIImagePro\System\Services\AIImageProService::getActiveImageModels();

                $editorData = MarketplaceHelper::isRegistered('advanced-image') ? collect(\App\Extensions\AdvancedImage\System\Helpers\Tool::get() ?? []) : collect();

                $toolsData = collect();
                if (MarketplaceHelper::isRegistered('advanced-image')) {
                    $toolsData->push(['title' => __('Image Editor'), 'href' => route('dashboard.user.advanced-image.index')]);
                }
                if (MarketplaceHelper::isRegistered('creative-suite')) {
                    $toolsData->push(['title' => __('Marketing'), 'href' => route('dashboard.user.creative-suite.index')]);
                }
                if (MarketplaceHelper::isRegistered('ai-chat-pro-image-chat')) {
                    $toolsData->push(['title' => __('Assistant'), 'href' => route('ai-chat-image.index')]);
                }
                if (MarketplaceHelper::isRegistered('social-media')) {
                    $toolsData->push(['title' => __('Social Media'), 'href' => '#']);
                }

                // Pages data
                $footerMenuItems = MarketplaceHelper::isRegistered('footer-menu')
                    ? \App\Extensions\FooterMenu\System\Models\FooterMenu::query()->active()->orderBy('order')->get()
                    : collect();
                $footerPages = \App\Models\Page::where(['status' => 1, 'show_on_footer' => 1])->get();

                $columnCount = $enabledColumns->count();
            @endphp

            @if ($columnCount > 0)
                <div class="lg:grid-cols-{{ min($columnCount, 4) }} mb-20 grid grid-cols-2 gap-4">
                    @foreach ($enabledColumns as $column)
                        <div>
                            <h3 class="mb-6 text-base font-semibold">
                                {{ __($column['title'] ?? 'Links') }}
                            </h3>

                            <ul class="space-y-3 text-xs">
                                @php
                                    $source = $column['source'] ?? 'custom';
                                    $links = [];

                                    if ($source === 'models') {
                                        foreach (array_slice($modelsData, 0, 6) as $model) {
                                            $links[] = ['label' => $model['label'] ?? ($model['slug'] ?? __('Unknown')), 'url' => '#'];
                                        }
                                    } elseif ($source === 'editor') {
                                        foreach ($editorData->take(6) as $feature) {
                                            $links[] = ['label' => $feature['title'] ?? __('Unknown'), 'url' => route('dashboard.user.advanced-image.index')];
                                        }
                                    } elseif ($source === 'tools') {
                                        foreach ($toolsData as $tool) {
                                            $links[] = ['label' => $tool['title'], 'url' => $tool['href']];
                                        }
                                    } elseif ($source === 'pages') {
                                        if ($footerMenuItems->count() > 0) {
                                            foreach ($footerMenuItems as $item) {
                                                $links[] = ['label' => __($item->label), 'url' => $item->link];
                                            }
                                        } elseif ($footerPages->count() > 0) {
                                            foreach ($footerPages as $page) {
                                                $links[] = ['label' => $page->title, 'url' => '/page/' . $page->slug];
                                            }
                                        } else {
                                            // Fallback links
                                            if (\App\Models\Page::where('slug', 'terms')->exists()) {
                                                $links[] = ['label' => __('Terms of Service'), 'url' => '/page/terms'];
                                            }
                                            if (\App\Models\Page::where('slug', 'privacy-policy')->exists()) {
                                                $links[] = ['label' => __('Privacy Policy'), 'url' => '/page/privacy-policy'];
                                            }
                                            $links[] = ['label' => __('Home'), 'url' => route('index')];
                                        }
                                    } elseif ($source === 'custom') {
                                        $links = $column['links'] ?? [];
                                    }
                                @endphp

                                @foreach ($links as $link)
                                    @if (!empty($link['label']))
                                        <li>
                                            <a href="{{ $link['url'] ?? '#' }}">{{ $link['label'] }}</a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Footer Copyright Text --}}
            @php
                $copyrightText = $customCopyright ?: $fSetting->footer_copyright ?? 'By using this Website, you agreed to accept all terms and conditions written in here.';
            @endphp
            <p class="mx-auto mb-7 w-full text-pretty text-center text-[12px] leading-[1.4em] opacity-65 lg:w-7/12">
                {{ __($copyrightText) }}
            </p>

            {{-- Social Media Icons --}}
            @if ($showSocialIcons && $socialAccounts->count() > 0)
                <ul class="flex items-center justify-center gap-8">
                    @foreach ($socialAccounts as $social)
                        <li>
                            <a
                                class="inline-flex"
                                href="{{ $social['link'] }}"
                                target="_blank"
                                title="{{ $social['title'] }}"
                            >
                                <span class="w-6 [&_path:not([fill=none])]:fill-current [&_svg:not([fill=none])]:fill-current [&_svg]:h-auto [&_svg]:w-full">
                                    {!! $social['icon'] !!}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Copyright Year --}}
            <p class="mt-8 text-center text-[12px] opacity-50">
                {{ date('Y') . ' ' . ($setting->site_name ?? config('app.name')) . '. ' . __('All rights reserved.') }}
            </p>
        </div>
    </div>
@endif
