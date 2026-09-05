{!! adsense_tools_728x90() !!}
<section
    class="site-section relative overflow-hidden bg-[--bg] py-36 transition-all duration-700 [--bg:#0A0A0E] md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    data-color-scheme="dark"
>
    <div
        class="pointer-events-none relative z-0"
        aria-hidden="true"
    >
        <div class="size-28 absolute start-0 top-32 -translate-x-1/2 rounded-full bg-[#FF7847] blur-3xl"></div>
        <div class="size-28 absolute end-0 top-1/3 translate-x-1/2 rounded-full bg-[#0A7CFF] blur-3xl"></div>
    </div>

    <div class="container">
        <header class="relative mx-auto mb-14 w-full text-center lg:w-1/2">
            <h2 class="mb-5 text-white">
                {!! $fSectSettings->tools_title ? __($fSectSettings->tools_title) : 'Enhanced Experience' !!}
            </h2>
            <p class="text-header-p mx-auto lg:w-3/4">
                {!! $fSectSettings->tools_description ? __($fSectSettings->tools_description) : __('Enhance your content generation experience with unique tools and built-in features.') !!}
            </p>
        </header>

        <div class="-mx-4 flex flex-wrap gap-y-8">
            @foreach ($tools->take(4) as $item)
                @include('landing-page.tools.item', ['w_half' => $loop->index === 0, 'w_fourth' => $loop->index > 0])
            @endforeach
            <div class="flex w-full flex-col gap-8 lg:w-1/4">
                @foreach ($tools->skip(4)->take(2) as $item)
                    @include('landing-page.tools.item', ['w_half' => false, 'w_fourth' => false])
                @endforeach
            </div>
            @foreach ($tools->skip(6) as $item)
                @include('landing-page.tools.item', ['w_half' => false, 'w_fourth' => true])
            @endforeach
        </div>
    </div>
</section>
