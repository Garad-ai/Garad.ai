{!! adsense_how_it_works_728x90() !!}
<section
    class="site-section py-24 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="how-it-works"
>
    <div class="container">
        <header class="relative mx-auto mb-14 w-full text-center lg:w-1/2">
            <h6 class="before:bg-gradient relative z-1 mb-6 inline-flex px-3.5 py-2 before:absolute before:inset-0 before:-z-1 before:rounded-full before:opacity-30">
                {{ __('How it works') }}
            </h6>
            <p class="text-header-p mx-auto text-[25px] font-semibold leading-7 lg:w-3/4">
                {!! $fSectSettings->how_it_works_title
                    ? __($fSectSettings->how_it_works_title)
                    : __("MagicAI is incredibly user-friendly you'll be amazed at how simple it is to create your first AI content.") !!}
            </p>
        </header>

        <div class="mx-auto w-full lg:w-10/12">
            <div
                class="relative lg:before:pointer-events-none lg:before:absolute lg:before:-top-2.5 lg:before:z-0 lg:before:h-[50px] lg:before:w-full lg:before:bg-gradient-to-r lg:before:from-transparent lg:before:via-[#E1EDFB] lg:before:to-transparent">
                <figure
                    class="absolute end-0 top-10 hidden -translate-y-full lg:block"
                    aria-hidden="true"
                >
                    <img
                        class="animate-bounce [animation-duration:4s]"
                        src="{{ custom_theme_url('/assets/landing-page/robot.png') }}"
                        alt="{{ __('Robot Image') }}"
                        width="78"
                        height="149"
                    />
                </figure>
                <div class="grid-cols-{{ count($howitWorks) }} mb-20 grid gap-7 max-lg:grid-cols-1 lg:gap-36">
                    @foreach ($howitWorks as $item)
                        @include('landing-page.how-it-works.item')
                    @endforeach
                </div>
            </div>

            @if ($howitWorksDefaults['option'] == 1)
                <div class="mt-20 text-center">
                    <p class="inline-block rounded-full bg-[#F5F7F9] px-5 py-2 text-xs leading-tight text-[#A2B2C9] [&_a]:text-blue-700">
                        {!! $howitWorksDefaults['html'] !!}
                    </p>
                </div>
            @endif
        </div>
    </div>
</section>
