<footer
    class="site-footer relative bottom-0 z-0 [&.is-sticky]:sticky"
    x-data="{
        checkSticky: function() {
            $el.classList.toggle('sticky', $el.offsetHeight < window.innerHeight)
        }
    }"
    x-init="checkSticky()"
    @resize.window.debounce.500ms="checkSticky()"
>
    <section
        class="site-section relative overflow-hidden bg-[#0A0A0E] pb-20 pt-28 text-white/80"
        data-color-scheme="dark"
    >
        <div
            class="pointer-events-none"
            aria-hidden="true"
        >
            <div class="size-96 bg-white/35 absolute -top-20 start-1/2 z-0 -translate-x-1/2 -translate-y-1/2 scale-x-150 rounded-full blur-[160px]"></div>
        </div>

        <div class="container relative">
            <div class="mb-24 flex flex-wrap items-center justify-between gap-y-12">
                <div class="relative w-full lg:w-5/12">
                    <div aria-hidden="true">
                        <div class="size-32 absolute start-0 top-1/3 -translate-x-1/2 rounded-full bg-[#FF7847] opacity-80 blur-3xl"></div>
                    </div>
                    <div class="relative">
                        <p class="mb-9 text-[10px] font-semibold uppercase tracking-widest">
                            <span class="!me-2 inline-block rounded-xl bg-[#262626] px-3 py-1">
                                {{ __($setting->site_name) }}
                            </span>
                            {{ __($fSetting->footer_text_small) }}
                        </p>
                        <p class="mb-8 text-[63px] font-semibold leading-none">
                            {{ __($fSetting->footer_header) }}
                        </p>
                        <p class="mb-8 text-lg lg:w-10/12">
                            {{ __($fSetting->footer_text) }}
                        </p>
                        <a
                            class="inline-flex items-center gap-4 rounded-full bg-white/15 px-12 py-4 text-white transition-all hover:scale-105 hover:bg-white hover:text-black hover:shadow-2xl hover:shadow-white/20"
                            href="{{ !empty($fSetting->footer_button_url) ? $fSetting->footer_button_url : '#' }}"
                            target="_blank"
                        >
                            {!! __($fSetting->footer_button_text) !!}
                            <x-tabler-chevron-right class="size-4" />
                        </a>
                    </div>
                </div>

                <div class="relative flex w-full flex-col lg:w-5/12 lg:items-end">
                    <div aria-hidden="true">
                        <div class="size-44 top-1/6 absolute start-1/2 translate-x-1/2 rounded-full bg-[#4C6A85] opacity-80 blur-3xl"></div>
                    </div>

                    <div
                        x-data="{ activeIndex: -1 }"
                        x-init="const styles = getComputedStyle($el);
                        const stayTime = parseInt(styles.getPropertyValue('--stay-time'), 10);
                        const transitionDuration = parseInt(styles.getPropertyValue('--transition-duration'), 10);
                        const firstItem = $refs['item-0'];
                        const lastItem = $refs['item-{{ count($footer_items) - 1 }}'];
                        $el.style.height = `${$refs.ul.offsetHeight + firstItem.offsetHeight}px`;
                        $refs.ul.insertAdjacentElement('beforeend', firstItem.cloneNode(true));
                        setInterval(() => {
                            activeIndex = activeIndex < {{ count($footer_items) - 1 }} ? activeIndex + 1 : 0;
                            const activeItem = $refs[`item-${activeIndex}`];
                            $refs.ul.insertAdjacentElement('beforeend', activeItem);
                            $refs.ul.animate([{ translate: '0 0', }, { translate: `0 -${activeItem.offsetHeight}px`, }, ], { duration: transitionDuration, easing: 'cubic-bezier(.19,.86,.29,.97)', fill: 'forwards' });
                        }, stayTime)"
                        x-ref="parent"
                        style="--stay-time: 2150ms; --transition-duration: 800ms; mask-image: linear-gradient(to bottom, transparent 0%, black 45%, black 55%, transparent 100%)"
                    >
                        <ul
                            class="lqd-features-rotator relative flex flex-col gap-3.5 text-lg text-white transition-[translate] duration-[--transition-duration]"
                            x-ref="ul"
                        >
                            @php
                                $items = $footer_items;
                                $count = count($items);
                                $maxOpacity = 100;
                                $minOpacity = 25;
                                $middleIndex = floor($count / 2);
                            @endphp
                            @foreach ($items as $index => $item)
                                @php
                                    $distanceFromCenter = abs($index - $middleIndex);
                                    $opacity = $maxOpacity - ($distanceFromCenter * ($maxOpacity - $minOpacity)) / $middleIndex;
                                @endphp
                                <li
                                    class="lqd-features-rotator-item flex items-center gap-4"
                                    x-ref="item-{{ $loop->index }}"
                                    {{-- style="opacity: {{ $opacity / 100 }}" --}}
                                >
                                    <span class="size-11 inline-grid shrink-0 place-content-center rounded-md bg-[#707070]">
                                        <x-tabler-check class="size-6" />
                                    </span>
                                    {{ __($item) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-8 border-y border-white/5 py-12">
                <a href="{{ route('index') }}">
                    @if (isset($setting->logo_dark))
                        <img
                            src="{{ custom_theme_url($setting->logo_dark_path, true) }}"
                            @if (isset($setting->logo_dark_2x_path)) srcset="/{{ $setting->logo_dark_2x_path }} 2x" @endif
                            alt="{{ custom_theme_url($setting->site_name) }} logo"
                        >
                    @else
                        <img
                            src="{{ custom_theme_url($setting->logo_path, true) }}"
                            @if (isset($setting->logo_2x_path)) srcset="/{{ $setting->logo_2x_path }} 2x" @endif
                            alt="{{ $setting->site_name }} logo"
                        >
                    @endif
                </a>

                <ul class="flex flex-wrap items-center gap-5 text-sm lg:justify-end">
                    @foreach (\App\Models\SocialMediaAccounts::where('is_active', true)->get() as $social)
                        <li>
                            <a
                                class="inline-flex items-center gap-2 opacity-75 transition-all hover:opacity-100"
                                href="{{ $social['link'] }}"
                            >
                                <span class="w-3.5 [&_svg]:h-auto [&_svg]:w-full">
                                    {!! $social['icon'] !!}
                                </span>
                                {{ $social['title'] }}
                            </a>
                        </li>
                    @endforeach
                    @foreach (\App\Models\Page::where(['status' => 1, 'show_on_footer' => 1])->get() ?? [] as $page)
                        <li>
                            <a
                                class="inline-flex items-center gap-2 opacity-75 transition-all hover:opacity-100"
                                href="/page/{{ $page->slug }}"
                            >
                                {{ $page->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="opacity-55 flex flex-wrap items-center justify-center gap-4 pt-11 text-center text-sm">
                <p>
                    {{ date('Y') . ' ' . $setting->site_name . '. ' . __($fSetting->footer_copyright) }}
                </p>
            </div>

        </div>
    </section>
</footer>
