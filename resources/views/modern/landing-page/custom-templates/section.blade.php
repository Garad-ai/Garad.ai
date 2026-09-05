{!! adsense_templates_728x90() !!}
<section
    class="site-section relative overflow-hidden bg-[#0A0A0E] py-32 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="templates"
    data-color-scheme="dark"
>
    <div
        class="pointer-events-none"
        aria-hidden="true"
    >
        <div class="size-96 bg-white/35 absolute start-1/2 top-0 z-0 -translate-x-1/2 -translate-y-1/2 scale-x-150 rounded-full blur-[160px]"></div>
    </div>
    <div class="container">
        <header class="relative mx-auto w-full text-center lg:w-1/2">
            <h2 class="mb-5 text-white">
                {!! __($fSectSettings->custom_templates_title) !!}
            </h2>
            <p class="text-header-p">
                {!! $fSectSettings->custom_templates_description
                    ? __($fSectSettings->custom_templates_description)
                    : __('Our tools can help you generate any kind of content from product descriptions and blog posts to newsletters and social media updates.') !!}
            </p>
        </header>
    </div>
    <div
        class="relative"
        style="mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent)"
    >
        <marquee behavior="alternate">
            <div class="flex gap-5 whitespace-nowrap">
                @for ($i = 0; $i < 2; $i++)
                    @foreach ($templates as $item)
                        @if ($item->active != 1)
                            @continue
                        @endif
                        @include('landing-page.custom-templates.item')
                    @endforeach
                @endfor
            </div>
        </marquee>
    </div>

    <div class="relative z-20 px-5 text-center">
        <p class="inline-block rounded-xl border border-white/5 px-8 py-3 text-xs text-white/60">
            {{ __('Couldn’t find what you’re looking for?') }}
            <a
                class="text-[#106AC4] underline-offset-4 transition-all hover:text-white hover:underline hover:underline-offset-2"
                href="{{  (route('dashboard.index')) }}"
            >
                {{ __('You can add your custom templates.') }}
            </a>
        </p>
    </div>
</section>
