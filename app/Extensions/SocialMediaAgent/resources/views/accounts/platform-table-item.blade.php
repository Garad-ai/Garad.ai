@php
    $url_show_query = request()->query('show');

    $image = 'vendor/social-media/icons/' . $platform->platform . '.svg';
    $image_dark_version = 'vendor/social-media/icons/' . $platform->platform . '-mono-light.svg';
    $darkImageExists = file_exists(public_path($image_dark_version));
@endphp

<tr
    data-name="{{ data_get($platform['credentials'], 'name') }}"
    data-platform="{{ $platform->platform }}"
    x-show="!searchString || ($el.getAttribute('data-name').toLowerCase().includes(searchString) || $el.getAttribute('data-platform').toLowerCase().includes(searchString))"
    x-transition
>
    <td>
        <div class="m-0 flex items-center gap-3">
            <figure class="inline-grid size-9 place-items-center overflow-hidden rounded-full">
                @if (data_get($platform['credentials'], 'picture') && data_get($platform['credentials'], 'picture') !== 'test')
                    <img
                        class="size-full object-cover object-center"
                        src="{{ data_get($platform['credentials'], 'picture') }}"
                    >
                @else
                    <svg
                        class="size-7"
                        width="53"
                        height="60"
                        viewBox="0 0 53 60"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M26.4191 59.3442C17.0361 59.3442 8.44287 56.9236 1.81433 51.2617C0.199606 49.8832 -0.398015 47.2257 0.26792 44.7404C2.35094 36.9664 6.7234 35.2167 16.2625 35.2167H36.0775C45.619 35.2167 50.3425 36.4263 52.5702 44.7404C53.34 47.6133 52.6432 49.8808 51.0262 51.2597C44.3998 56.9236 35.8043 59.3442 26.4191 59.3442Z"
                        />
                        <path
                            d="M26.4171 0C34.8842 0 41.7449 6.87005 41.7449 15.3416C41.7449 23.8152 34.8842 30.6833 26.4171 30.6833C17.9541 30.6833 11.0933 23.8152 11.0933 15.3416C11.0933 6.87005 17.9541 0 26.4171 0Z"
                        />
                    </svg>
                @endif
            </figure>
            <p class="m-0 font-medium">
                <span class="block text-xs">
                    {{ data_get($platform['credentials'], 'name') }}
                </span>
                <span class="text-2xs opacity-60">
                    {{ $platform->platformLabel() }}
                </span>
            </p>
        </div>
    </td>

    <td class="text-2xs">
        {{ date('M j Y', strtotime($platform->connected_at)) }}
    </td>

    <td>
        <p class="m-0 flex items-center gap-3 text-2xs font-medium">
            <span @class([
                'inline-flex size-[9px] rounded-full',
                'bg-green-500' => $platform->isConnected(),
                'bg-foreground/50' => !$platform->isConnected(),
            ])></span>
            {{ $platform->isConnected() ? __('Active') : __('Inactive') }}
        </p>
    </td>

    <td>
        <figure>
            <img
                class="h-auto max-h-5 w-5 dark:hidden"
                src="{{ asset($image) }}"
                alt="{{ $platform->platformLabel() }}"
            />
            <img
                class="hidden h-auto max-h-5 w-5 dark:block"
                src="{{ asset($image_dark_version) }}"
                alt="{{ $platform->platformLabel() }}"
            />
        </figure>
    </td>

    <td>
        <div class="flex items-center justify-end gap-2 font-normal">
            <x-button
                class="z-10 size-9"
                size="none"
                variant="ghost-shadow"
                title="{{ __('View') }}"
                href="{{ route('social-media.oauth.connect.' . $platform->platform) . '?platform_id=' . $platform->getKey() }}"
                onclick="return confirm('{{ __('Are you sure? This is permanent..') }}')"
            >

                @if ($platform->expires_at < now()->subMinutes(10))
                    <x-tabler-reload class="size-5 text-red-500" />
                @else
                    <x-tabler-reload class="size-5 text-green-500" />
                @endif
            </x-button>

            <x-button
                class="z-10 size-9"
                size="none"
                variant="ghost-shadow"
                title="{{ __('View') }}"
                href="{{ route('dashboard.user.social-media.platforms.disconnect', $platform->getKey()) }}"
                onclick="return confirm('{{ __('Are you sure? This is permanent..') }}')"
            >
                <x-tabler-x class="size-5" />
            </x-button>
        </div>
    </td>
</tr>
