<div class="lqd-megamenu-link-item">
    <a
        class="flex items-center gap-6"
        href="{{ $mega_menu_item['link'] ?? '/' }}"
    >
        @if (filled($mega_menu_item['icon']))
            <span class="inline-grid size-16 shrink-0 place-items-center rounded-lg bg-heading-foreground/5">
                <img
                    class="size-full rounded-lg object-cover object-center [&[src*=svg]]:size-8 [&[src*=svg]]:rounded-none [&[src*=svg]]:[object-fit:unset]"
                    src="{{ asset($mega_menu_item['icon_url']) }}"
                    alt="{{ $mega_menu_item['label'] }}"
                >
            </span>
        @endif
        @if (filled($mega_menu_item['label']) || filled($mega_menu_item['description']))
            <span class="flex w-full grow flex-col gap-1">
                @if (filled($mega_menu_item['label']))
                    <span class="block w-full text-lg font-medium text-heading-foreground">
                        {{ $mega_menu_item['label'] }}
                    </span>
                @endif
                @if (filled($mega_menu_item['description']))
                    <span class="block w-full text-xs text-foreground">
                        {{ $mega_menu_item['description'] }}
                    </span>
                @endif
            </span>
        @endif
    </a>
</div>
