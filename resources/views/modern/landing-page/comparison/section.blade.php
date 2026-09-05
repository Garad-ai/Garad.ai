@php
    $items = $comparison_section_items;
@endphp

<section
    class="site-section relative py-16 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="comparison"
>
    <svg
        width="0"
        height="0"
    >
        <defs>
            <linearGradient
                id="checkmark-gradient"
                x1="9.91335e-08"
                y1="3.79037"
                x2="13.424"
                y2="15.6304"
                gradientUnits="userSpaceOnUse"
            >
                <stop stop-color="#82E2F4" />
                <stop
                    offset="0.502"
                    stop-color="#8A8AED"
                />
                <stop
                    offset="1"
                    stop-color="#6977DE"
                />
            </linearGradient>
        </defs>
    </svg>
    <div class="container">
        <div class="lqd-comparison text-lg text-heading-foreground">
            <div class="lqd-comparison-head flex items-center justify-end px-5 pb-10 lg:px-8">
                <div class="w-7/12"></div>

                <div class="flex w-5/12 items-center justify-between">
                    <div class="flex w-1/2 justify-center px-1.5 text-center">
                        {{ __('Others') }}
                    </div>

                    <div class="flex w-1/2 justify-center px-1.5 text-center">
                        <figure>
                            <img
                                src="{{ custom_theme_url($setting->logo_path, true) }}"
                                @if (isset($setting->logo_2x_path)) srcset="/{{ $setting->logo_2x_path }} 2x" @endif
                                alt="{{ $setting->site_name }}"
                            >
                        </figure>
                    </div>
                </div>
            </div>

            <div class="lqd-comparison-body overflow-hidden rounded-xl border">
                @foreach ($items as $item)
                    <div class="flex items-center px-5 py-5 even:bg-heading-foreground/[2%] lg:px-8">
                        <div class="w-7/12">
                            <p>{{ __($item['label']) }}</p>
                        </div>

                        <div class="flex w-5/12 justify-between">
                            <div class="flex w-1/2 justify-center px-1.5 text-center">
                                @if ($item['others'])
                                    <svg
                                        width="16"
                                        height="17"
                                        viewBox="0 0 16 17"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M1.66311 7.8335C1.3482 7.83441 1.03997 7.92675 0.774083 8.09986C0.508199 8.27297 0.295525 8.51976 0.160666 8.81169C0.0258074 9.10362 -0.025725 9.42875 0.0120309 9.74946C0.0497867 10.0702 0.175288 10.3734 0.374015 10.624L4.61041 15.9476C4.76145 16.14 4.95507 16.2926 5.17518 16.3928C5.39529 16.4931 5.63553 16.538 5.87595 16.5238C6.39016 16.4955 6.8544 16.2133 7.15038 15.7494L15.9504 1.21089C15.9519 1.20848 15.9534 1.20607 15.9549 1.2037C16.0375 1.07364 16.0107 0.815911 15.8403 0.654001C15.7935 0.609539 15.7383 0.575379 15.6781 0.553626C15.6179 0.531873 15.5541 0.522988 15.4904 0.527517C15.4268 0.532045 15.3647 0.549893 15.308 0.57996C15.2513 0.610027 15.2013 0.651676 15.1609 0.702344C15.1577 0.706327 15.1545 0.710251 15.1511 0.714114L6.27615 11.0005C6.24238 11.0396 6.20137 11.0715 6.15549 11.0942C6.10961 11.117 6.05978 11.1301 6.0089 11.133C5.95802 11.1358 5.9071 11.1282 5.8591 11.1107C5.8111 11.0931 5.76697 11.066 5.72928 11.0308L2.78384 8.28122C2.47793 7.99355 2.0781 7.83382 1.66311 7.8335Z"
                                            fill="url(#checkmark-gradient)"
                                        />
                                    </svg>
                                @else
                                    <span class="size-6 inline-grid place-content-center rounded-full bg-heading-foreground/5 text-heading-foreground">
                                        -
                                    </span>
                                @endif
                            </div>

                            <div class="flex w-1/2 justify-center px-1.5 text-center">
                                @if ($item['ours'])
                                    <svg
                                        width="16"
                                        height="17"
                                        viewBox="0 0 16 17"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M1.66311 7.8335C1.3482 7.83441 1.03997 7.92675 0.774083 8.09986C0.508199 8.27297 0.295525 8.51976 0.160666 8.81169C0.0258074 9.10362 -0.025725 9.42875 0.0120309 9.74946C0.0497867 10.0702 0.175288 10.3734 0.374015 10.624L4.61041 15.9476C4.76145 16.14 4.95507 16.2926 5.17518 16.3928C5.39529 16.4931 5.63553 16.538 5.87595 16.5238C6.39016 16.4955 6.8544 16.2133 7.15038 15.7494L15.9504 1.21089C15.9519 1.20848 15.9534 1.20607 15.9549 1.2037C16.0375 1.07364 16.0107 0.815911 15.8403 0.654001C15.7935 0.609539 15.7383 0.575379 15.6781 0.553626C15.6179 0.531873 15.5541 0.522988 15.4904 0.527517C15.4268 0.532045 15.3647 0.549893 15.308 0.57996C15.2513 0.610027 15.2013 0.651676 15.1609 0.702344C15.1577 0.706327 15.1545 0.710251 15.1511 0.714114L6.27615 11.0005C6.24238 11.0396 6.20137 11.0715 6.15549 11.0942C6.10961 11.117 6.05978 11.1301 6.0089 11.133C5.95802 11.1358 5.9071 11.1282 5.8591 11.1107C5.8111 11.0931 5.76697 11.066 5.72928 11.0308L2.78384 8.28122C2.47793 7.99355 2.0781 7.83382 1.66311 7.8335Z"
                                            fill="url(#checkmark-gradient)"
                                        />
                                    </svg>
                                @else
                                    <span class="size-6 inline-grid place-content-center rounded-full bg-heading-foreground/5 text-heading-foreground">
                                        -
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>

        <div class="mt-20 text-center">
            <p class="inline-block rounded-full bg-[#F5F7F9] px-5 py-2 text-xs leading-tight text-[#A2B2C9]">
                {{ __('Need a specific feature?') }}
                <a
                    class="text-blue-700"
                    href="#"
                >
                    {{ __('Contact us') }}
                </a>
            </p>
        </div>
    </div>
</section>
