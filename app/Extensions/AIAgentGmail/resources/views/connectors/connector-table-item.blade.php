@php
    $isActive   = $connector->is_active && $connector->expires_at?->isFuture();
    $email      = $connector->getCredential('email');
    $name       = $connector->getCredential('name');
    $picture    = $connector->getCredential('picture');
    $displayName = $name ?? $email ?? ucfirst($connector->type);
@endphp

<tr
    data-name="{{ strtolower($displayName) }}"
    data-type="{{ $connector->type }}"
    x-show="!searchString || ($el.getAttribute('data-name').includes(searchString) || $el.getAttribute('data-type').includes(searchString))"
    x-transition
>
    <td>
        <div class="m-0 flex items-center gap-3">
            <figure class="inline-grid size-9 place-items-center overflow-hidden rounded-full bg-primary/10">
                @if ($picture)
                    <img
                        class="size-full object-cover object-center"
                        src="{{ $picture }}"
                        alt="{{ $displayName }}"
                    />
                @else
                    <x-tabler-brand-gmail class="size-5 text-primary" />
                @endif
            </figure>
            <p class="m-0 font-medium">
                <span class="block text-xs">{{ $displayName }}</span>
                @if ($email && $name)
                    <span class="text-2xs opacity-60">{{ $email }}</span>
                @endif
            </p>
        </div>
    </td>

    <td class="text-2xs">
        {{ $connector->connected_at?->format('M j Y') ?? '—' }}
    </td>

    <td>
        <p class="m-0 flex items-center gap-3 text-2xs font-medium">
            <span @class([
                'inline-flex size-[9px] rounded-full',
                'bg-green-500' => $isActive,
                'bg-foreground/50' => !$isActive,
            ])></span>
            {{ $isActive ? __('Active') : __('Expired') }}
        </p>
    </td>

    <td>
        <div class="flex items-center gap-2">
            <x-tabler-brand-gmail class="size-5" />
            <span class="text-2xs capitalize">{{ $connector->type }}</span>
        </div>
    </td>

    <td>
        <div class="flex items-center justify-end gap-2 font-normal">
            <x-button
                class="z-10 size-9"
                size="none"
                variant="ghost-shadow"
                title="{{ __('Reconnect') }}"
                href="{{ route('dashboard.user.ai-agent.connectors.gmail.redirect') }}"
                onclick="{{ $app_is_demo ? 'toastr.error(\''. __('This feature is disabled in demo mode.') .'\'); return false;' : '' }}"
            >
                @if (!$isActive)
                    <x-tabler-reload class="size-5 text-red-500" />
                @else
                    <x-tabler-reload class="size-5 text-green-500" />
                @endif
            </x-button>

            <form
                method="POST"
                action="{{ route('dashboard.user.ai-agent.connectors.destroy', $connector) }}"
            >
                @csrf
                @method('DELETE')
                <x-button
                    class="z-10 size-9"
                    type="submit"
                    size="none"
                    variant="ghost-shadow"
                    title="{{ __('Disconnect') }}"
                    onclick="{{ $app_is_demo ? 'toastr.error(\''. __('This feature is disabled in demo mode.') .'\'); return false;' : 'return confirm(\''. __('Are you sure? This is permanent.') .'\')'  }}"
                >
                    <x-tabler-x class="size-5" />
                </x-button>
            </form>
        </div>
    </td>
</tr>
