@php
    $items = $advanced_features_section;
    if ($items->isEmpty()) {
        $defaultData = [
            [
                'title' => __('Article Wizard'),
                'description' => __('Create a social media post and schedule it to be published directly on Linkedin or X.'),
                'image' => custom_theme_url('/assets/landing-page/advanced-feature-1.png'),
            ],
            [
                'title' => __('Intelligent AI Assistant'),
                'description' => __('Create a social media post and schedule it to be published directly on Linkedin or X.'),
                'image' => custom_theme_url('/assets/landing-page/advanced-feature-1.png'),
            ],
            [
                'title' => __('Publish on Social Media'),
                'description' => __('Create a social media post and schedule it to be published directly on Linkedin or X.'),
                'image' => custom_theme_url('/assets/landing-page/advanced-feature-1.png'),
            ],
            [
                'title' => __('SEO Tool'),
                'description' => __('Create a social media post and schedule it to be published directly on Linkedin or X.'),
                'image' => custom_theme_url('/assets/landing-page/advanced-feature-1.png'),
            ],
            [
                'title' => __('Real-Time Data'),
                'description' => __('Create a social media post and schedule it to be published directly on Linkedin or X.'),
                'image' => custom_theme_url('/assets/landing-page/advanced-feature-1.png'),
            ],
            [
                'title' => __('AI Photo Editor'),
                'description' => __('Create a social media post and schedule it to be published directly on Linkedin or X.'),
                'image' => custom_theme_url('/assets/landing-page/advanced-feature-1.png'),
            ],
        ];
        $items = $defaultData;
    }
@endphp

<section
    class="site-section relative overflow-hidden pb-14 pt-28 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="advanced-features"
>
    <div aria-hidden="true">
        <div class="size-28 absolute start-0 top-1/2 -translate-x-1/2 rounded-full bg-[#7cb4f5] blur-3xl"></div>
        <div class="size-28 absolute end-0 top-1/3 translate-x-1/2 rounded-full bg-[#da76d3] blur-3xl"></div>
    </div>
    <div class="container">

        <header class="relative mx-auto mb-12 w-full text-center lg:w-1/2">
            <h2 class="mb-5">
                {!! $fSectSettings->advanced_features_section_title ? __($fSectSettings->advanced_features_section_title) : 'Driving Innovation.' !!}
            </h2>
            <p class="text-header-p mx-auto lg:w-3/4">
                {!! $fSectSettings->advanced_features_section_description
                    ? __($fSectSettings->advanced_features_section_description)
                    : __('Optimize your content for search engines and reach more customers and increase your online visibility.') !!}
            </p>
        </header>

        <div x-data='{ items: @json($items), activeIndex: 0 }'>
            <div class="gap- flex flex-wrap items-center justify-between">
                <div class="flex w-full flex-col-reverse gap-y-12 lg:w-4/12 lg:flex-col">
                    <div class="rounded-xl bg-[#FAFBFE] px-8 py-6">
                        <h5
                            class="mb-3 text-lg"
                            x-text="items[activeIndex].title"
                            x-init="$el.innerText = items[activeIndex].title"
                        >
                            {{ $items[0]['title'] }}
                        </h5>
                        <p
                            x-text="items[activeIndex].description"
                            x-init="$el.innerText = items[activeIndex].description"
                        >
                            {{ $items[0]['description'] }}
                        </p>
                    </div>

                    <ul class="flex flex-col gap-4 text-lg font-semibold text-heading-foreground">
                        @foreach ($items as $item)
                            <li
                                class="group flex cursor-pointer items-center gap-4 transition-all duration-200 [&.active]:translate-x-1"
                                x-on:mouseenter="activeIndex = {{ $loop->index }}"
                                :class="{ 'active': activeIndex === {{ $loop->index }} }"
                            >
                                <span
                                    class="size-6 before:bg-gradient relative inline-grid flex-shrink-0 place-content-center rounded-full bg-heading-foreground/5 transition-all duration-200 before:absolute before:inset-0 before:z-0 before:rounded-full before:opacity-0 before:transition-opacity group-[&.active]:text-primary-foreground group-[&.active]:before:opacity-100"
                                >
                                    <x-tabler-chevron-right class="size-3.5 relative z-1" />
                                </span>
                                <span class="group-[&.active]:text-gradient">
                                    {{ $item['title'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="w-full lg:w-6/12">
                    <figure>
                        <img
                            class="h-auto w-full"
                            src="{{ $items[0]['image'] }}"
                            alt="{{ $items[0]['title'] }}"
                            x-init="$el.src = items[activeIndex].image;
                            $el.alt = items[activeIndex].title"
                            x-bind:src="items[activeIndex].image"
                            x-bind:alt="items[activeIndex].title"
                        />
                    </figure>
                </div>
            </div>
        </div>
    </div>
</section>
