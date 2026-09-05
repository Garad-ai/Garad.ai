{!! adsense_pricing_728x90() !!}

<section
    class="site-section relative border-b border-border pb-28 pt-36 transition-all duration-700 md:translate-y-8 md:opacity-0 [&.lqd-is-in-view]:translate-y-0 [&.lqd-is-in-view]:opacity-100"
    id="pricing"
>
    <div class="container relative">
        <header class="relative mx-auto mb-14 w-full text-center lg:w-1/2">
            <figure
                class="size-28 absolute -end-36 top-0 hidden -translate-y-1/2 animate-bounce place-content-center rounded-full bg-[#D9CCF4] [animation-duration:3s] lg:inline-grid"
                aria-hidden="true"
            >
                <img
                    class="relative -start-1 -top-0.5 group-[&.lqd-is-in-view]/section:animate-tada group-[&.lqd-is-in-view]/section:[animation-iteration-count:3]"
                    src="{{ custom_theme_url('/assets/landing-page/avatar-2.png') }}"
                    alt="{{ __('Decor Image') }}"
                    width="106"
                    height="106"
                >
            </figure>
            <h6 class="mb-5 inline-flex rounded-full border px-3.5 py-1.5">
                {!! __($fSectSettings->pricing_subtitle) ?? __('Designed to be your co-pilot') !!}
            </h6>
            <h2 class="mb-5">
                {!! __($fSectSettings->pricing_title) !!}
            </h2>
            <p class="text-header-p mx-auto lg:w-10/12">
                {!! $fSectSettings->pricing_description
                    ? __($fSectSettings->pricing_description)
                    : __('We have tailored a variety of plans to fit your budget, ensuring that you can leverage the power of AI anytime, anywhere.') !!}
            </p>
        </header>
        <div class="lqd-tabs text-center">
            <div
                class="lqd-tabs-triggers mx-auto mb-12 inline-flex gap-3 rounded-full border border-border p-2 text-xs font-semibold max-md:flex-wrap max-md:gap-y-2 max-md:rounded-2xl">

                @if ($plansSubscriptionMonthly->count() > 0)
                    @include('landing-page.pricing.item-trigger', [
                        'target' => '#pricing-monthly',
                        'label' => __('Monthly'),
                        'active' => true,
                        'currency' => $currency,
                    ])
                @endif
                @if ($plansSubscriptionAnnual->count() > 0)
                    @include('landing-page.pricing.item-trigger', [
                        'target' => '#pricing-annual',
                        'label' => __('Annual'),
                        'badge' => __($fSectSettings->pricing_save_percent),
                        'active' => false,
                        'currency' => $currency,
                    ])
                @endif
                @if ($plansSubscriptionLifetime->count() > 0)
                    @include('landing-page.pricing.item-trigger', [
                        'target' => '#pricing-lifetime',
                        'label' => __('Lifetime'),
                        'active' => false,
                        'currency' => $currency,
                    ])
                @endif
                @if ($plansPrepaid->count() > 0)
                    @include('landing-page.pricing.item-trigger', [
                        'target' => '#pricing-prepaid',
                        'label' => __('Pre-Paid'),
                        'active' => false,
                        'currency' => $currency,
                    ])
                @endif
            </div>

            <div class="lqd-tabs-content-wrap px-10 max-xl:px-0">
                <div class="lqd-tabs-content">
                    <div id="pricing-monthly">
                        <div class="grid grid-cols-3 items-start gap-2 max-md:grid-cols-1">
                            @foreach ($plansSubscriptionMonthly as $plan)
                                @include('landing-page.pricing.item-content', ['period' => $plan->frequency == 'monthly' ? 'Per Month' : 'Per Year'])
                            @endforeach
                        </div>
                    </div>
                    <div
                        class="hidden"
                        id="pricing-annual"
                    >
                        <div class="grid grid-cols-3 items-start gap-2 max-md:grid-cols-1">
                            @foreach ($plansSubscriptionAnnual as $plan)
                                @include('landing-page.pricing.item-content', ['period' => $plan->frequency == 'monthly' ? 'Per Month' : 'Per Year'])
                            @endforeach
                        </div>
                    </div>
                    <div
                        class="hidden"
                        id="pricing-prepaid"
                    >
                        <div class="grid grid-cols-3 items-start gap-2 max-md:grid-cols-1">
                            @foreach ($plansPrepaid as $plan)
                                @include('landing-page.pricing.item-content', ['period' => __('One Time Payment')])
                            @endforeach
                        </div>
                    </div>
                    <div
                        class="hidden"
                        id="pricing-lifetime"
                    >
                        <div class="grid grid-cols-3 items-start gap-2 max-md:grid-cols-1">
                            @foreach ($plansSubscriptionLifetime as $plan)
                                @include('landing-page.pricing.item-content', ['period' => __('One Time Payment')])
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto mt-12 text-center text-xs text-[#4A5C73] lg:w-1/2 lg:px-10">
            <p>
                {{ $fSectSettings->plan_footer_text ?: __('All payments undergo processing through the associated payment gateway, ensuring complete security with 256-bit SSL encryption.') }}
            </p>
        </div>
    </div>
</section>
