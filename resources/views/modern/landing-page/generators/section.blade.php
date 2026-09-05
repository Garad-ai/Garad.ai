<section class="site-section relative pb-52 pt-20 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100">
    <figure
        class="absolute bottom-0 start-0 z-0 flex h-full w-full items-end overflow-hidden opacity-30"
        style="mask-image: linear-gradient(to bottom left, transparent, black)"
        aria-hidden="true"
    >
        <img
            src="{{ custom_theme_url('assets/landing-page/decor-2.jpg') }}"
            width="2880"
            height="2030"
            alt="{{ __('Decor Image') }}"
        />
    </figure>
    <div class="container relative">
        <header class="relative mx-auto mb-10 w-full text-center lg:w-1/2">
            <h6 class="mb-5 inline-flex rounded-full border px-3.5 py-1.5">
                {!! __($fSectSettings->generators_subtitle) ?? __('Seamless Content Generation') !!}
            </h6>
            <h2 class="mb-5">
                {!! $fSectSettings->generators_title ? __($fSectSettings->generators_title) : __('Supercharged Generative AI.') !!}
            </h2>
            <p class="text-header-p mx-auto lg:w-3/4">
                {!! $fSectSettings->generators_description ?? __($fSectSettings->generators_description) !!}
            </p>
        </header>

        <div class="lqd-tabs">
            <div class="lqd-tabs-triggers mb-10 flex justify-between gap-y-4 rounded-2xl border p-6 max-md:flex-wrap">

                @foreach ($generatorsList as $item)
                    @include('landing-page.generators.item-trigger')
                @endforeach
            </div>

            <div class="lqd-tabs-content-wrap">
                @foreach ($generatorsList as $item)
                    @include('landing-page.generators.item-content')
                @endforeach
            </div>
        </div>
    </div>
</section>
