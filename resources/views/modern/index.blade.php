@php
    // TODO: remove these after implementing backend
    $fSectSettings['advanced_features_active'] = true;
    $fSectSettings['marquee_active'] = true;
    $fSectSettings['comparison_active'] = true;
@endphp

@extends('layout.app')

@section('content')
    <div class="relative z-1 bg-background">
        <svg
            width="0"
            height="0"
        >
            <defs>
                <linearGradient
                    id="icons-gradient"
                    x1="25.3232"
                    y1="26.2102"
                    x2="9.4744"
                    y2="30.2264"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stop-color="#48BDB6" />
                    <stop
                        offset="0.47"
                        stop-color="#6E85FF"
                    />
                    <stop
                        offset="1"
                        stop-color="#709CB4"
                    />
                </linearGradient>
            </defs>
        </svg>

        @include('landing-page.banner.section')

        @includeWhen($fSectSettings->testimonials_active == 1, 'landing-page.clients.section')

        @includeWhen($fSectSettings->features_active == 1, 'landing-page.features.section')

        @includeWhen($fSectSettings->generators_active == 1, 'landing-page.generators.section')

        @includeWhen($fSectSettings->custom_templates_active == 1, 'landing-page.custom-templates.section')

        @includeWhen($fSectSettings->advanced_features_active == 1, 'landing-page.advanced-features.section')

        @includeWhen($fSectSettings->marquee_active == 1, 'landing-page.marquee.section')

        @includeWhen($fSectSettings->comparison_active == 1, 'landing-page.comparison.section')

        @includeWhen($fSectSettings->who_is_for_active == 1, 'landing-page.who-is-for.section')

        @includeWhen($fSectSettings->tools_active == 1, 'landing-page.tools.section')

        @includeWhen($fSectSettings->how_it_works_active == 1, 'landing-page.how-it-works.section')

        @includeWhen($fSectSettings->testimonials_active == 1, 'landing-page.testimonials.section')

        @includeWhen($fSectSettings->pricing_active == 1, 'landing-page.pricing.section')

        @includeWhen($fSectSettings->faq_active == 1, 'landing-page.faq.section')

        @includeWhen($fSectSettings->blog_active == 1, 'landing-page.blog.section')

        @includeWhen($setting->gdpr_status == 1, 'landing-page.gdpr')
    </div>
@endsection

@push('script')
    <script>
        const restArguments = function(func, startIndex) {
            startIndex = startIndex == null ? func.length - 1 : +startIndex;
            return function() {
                var length = Math.max(arguments.length - startIndex, 0),
                    rest = Array(length),
                    index = 0;
                for (; index < length; index++) {
                    rest[index] = arguments[index + startIndex];
                }
                switch (startIndex) {
                    case 0:
                        return func.call(this, rest);
                    case 1:
                        return func.call(this, arguments[0], rest);
                    case 2:
                        return func.call(this, arguments[0], arguments[1], rest);
                }
                var args = Array(startIndex + 1);
                for (index = 0; index < startIndex; index++) {
                    args[index] = arguments[index];
                }
                args[startIndex] = rest;
                return func.apply(this, args);
            };
        };
        window.liquidDebounce = function(func, wait, immediate) {
            var timeout, result;

            var later = function(context, args) {
                timeout = null;
                if (args) result = func.apply(context, args);
            };

            var debounced = restArguments(function(args) {
                if (timeout) clearTimeout(timeout);
                if (immediate) {
                    var callNow = !timeout;
                    timeout = setTimeout(later, wait);
                    if (callNow) result = func.apply(this, args);
                } else {
                    timeout = liquidDelay(later, wait, this, args);
                }

                return result;
            });

            debounced.cancel = function() {
                clearTimeout(timeout);
                timeout = null;
            };

            return debounced;
        };
        const liquidDelay = restArguments(function(func, wait, args) {
            return setTimeout(function() {
                return func.apply(null, args);
            }, wait);
        });

        (() => {
            const particlesContainer = document.querySelectorAll('.lqd-particles-container');

            particlesContainer.forEach(el => {
                const io = new IntersectionObserver(([entry]) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.remove('invisible')
                    } else {
                        entry.target.classList.add('invisible')
                    }
                });
                io.observe(el);
            })
        })();
    </script>
    <script src="{{ custom_theme_url('/assets/libs/matter/matter.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/gsap/minified/gsap.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/liquid-throwable/liquidThrowable.min.js') }}"></script>
@endpush
