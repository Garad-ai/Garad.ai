<div class="lqd-social-media-cards-grid mt-10 flex justify-between gap-5">
    @foreach ($platforms as $platform)
        @php
            $image = 'vendor/social-media/icons/' . $platform->value . '.svg';
            $image_dark_version = 'vendor/social-media/icons/' . $platform->value . '-light.svg';

        @endphp
        <x-card
            class="lqd-social-media-card flex flex-col text-heading-foreground transition-all hover:scale-105 hover:border-heading-foreground/10 hover:shadow-lg hover:shadow-black/5"
            class:body="flex flex-col "
        >
            <figure class="mb-8 w-9 transition-all group-hover/card:scale-125">
                <img
                    @class([
                        'w-full h-auto',
                        'dark:hidden' => file_exists($image_dark_version),
                    ])
                    src="{{ asset($image) }}"
                    alt="{{ $platform->label() }}"
                />
                @if (file_exists($image_dark_version))
                    <img
                        class="hidden h-auto w-full dark:block"
                        src="{{ asset($image_dark_version) }}"
                        alt="{{ $platform->label() }}"
                    />
                @endif
            </figure>
            <h4 class="mb-2 text-lg text-inherit">
                {{ $platform->label() }}
            </h4>
            <a
                class="relative opacity-70"
                target="_blank"
                href="{{ route('social-media.oauth.connect.' . $platform->value) }}"
            >@lang('Add New Account')</a>

            <a
                class="absolute inset-0 z-2 inline-block"
                target="_blank"
                href="{{ route('social-media.oauth.connect.' . $platform->value) }}"
            ></a>
        </x-card>
    @endforeach
</div>
