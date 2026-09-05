<x-dropdown.dropdown
    anchor="end"
    offsetY="13px"
>
    <x-slot:trigger
        variant="primary"
    >
        <x-tabler-plus class="size-4" />
        @lang('Generate New Post')
    </x-slot:trigger>

    <x-slot:dropdown
        class="min-w-52 overflow-hidden p-2"
    >
        @foreach ($platforms as $platform)
            @php
                $image = 'vendor/social-media/icons/' . $platform->value . '.svg';
                $image_dark_version = 'vendor/social-media/icons/' . $platform->value . '-light.svg';
                $darkImageExists = file_exists(public_path($image_dark_version));
                $is_connected = $platform->platform()?->isConnected();
            @endphp
            <x-button
                @class([
                    'w-full justify-start rounded-md px-3 py-2 text-start text-2xs hover:bg-heading-foreground/5 hover:no-underline',
                    'opacity-50 pointer-events-none saturate-0' => !$is_connected,
                ])
                variant="link"
                href="{{ route('dashboard.user.social-media.post.create', ['platform' => $platform->value]) }}"
            >
                <img
                    @class(['w-6 h-auto max-h-6', 'dark:hidden' => $darkImageExists])
                    src="{{ asset($image) }}"
                    alt="{{ $platform->label() }}"
                />
                @if ($darkImageExists)
                    <img
                        class="hidden h-auto max-h-6 w-6 dark:block"
                        src="{{ asset($image_dark_version) }}"
                        alt="{{ $platform->label() }}"
                    />
                @endif
                {{ $platform->label() }}
            </x-button>
        @endforeach
    </x-slot:dropdown>
</x-dropdown.dropdown>
