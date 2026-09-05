@if ($menu_item && isset($menu_item['mega_menu']) && $menu_item['mega_menu'])
    <div
        class="lqd-megamenu z-10 hidden max-w-[100vw] origin-top whitespace-normal rounded-xl border bg-background text-start transition-all motion-duration-150 motion-ease-spring-smooth max-lg:w-full max-lg:group-[&.is-hover]/li:block lg:pointer-events-none lg:invisible lg:absolute lg:start-0 lg:top-[calc(100%+var(--sub-offset,0px))] lg:mx-4 lg:block lg:opacity-0 lg:shadow-2xl lg:group-[&.is-hover]/li:pointer-events-auto lg:group-[&.is-hover]/li:visible lg:group-[&.is-hover]/li:opacity-100 lg:group-[&.is-hover]/li:[&[data-direction=left]]:motion-translate-x-in-[15px] lg:group-[&.is-hover]/li:[&[data-direction=right]]:motion-translate-x-in-[-15px]"
        @resize.window.debounce="position"
        @mousemove.window.throttle.30ms="onWindowMouseMove"
        x-data="liquidMegamenu"
    >
        <div class="lqd-megamenu-row flex flex-wrap lg:flex-nowrap">
            @foreach ($menu_item['mega_menu']['items'] ?? [] as $mega_menu_item)
                @if ($mega_menu_item['type'] === 'divider')
                    @includeFirst(['mega-menu::partials.megamenu-components.megamenu-' . $mega_menu_item['type'], 'vendor.empty'], ['mega_menu_item' => $mega_menu_item])
                @else
                    <div class="lqd-megamenu-col basis-full p-5 lg:min-w-80 lg:px-9 lg:py-8">
                        @includeFirst(
                            ['mega-menu::partials.megamenu-components.megamenu-' . $mega_menu_item['type'], 'vendor.empty'],
                            ['mega_menu_item' => $mega_menu_item]
                        )
                        @if (filled($mega_menu_item['active_children']))
                            @foreach ($mega_menu_item['active_children'] as $mega_menu_child_item)
                                @includeFirst(
                                    ['mega-menu::partials.megamenu-components.megamenu-' . $mega_menu_child_item['type'], 'vendor.empty'],
                                    ['mega_menu_item' => $mega_menu_child_item]
                                )
                            @endforeach
                        @endif
                    </div>
                @endif
            @endforeach
            {{-- @dump($menu_item) --}}
        </div>
    </div>
@endif
