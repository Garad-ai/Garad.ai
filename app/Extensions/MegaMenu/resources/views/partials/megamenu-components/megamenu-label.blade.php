<div class="lqd-megamenu-label mb-10 last:mb-0">
    <h3 class="text-base font-medium tracking-normal">
        {{ $mega_menu_item['label'] }}
    </h3>
    @if (!empty($mega_menu_item['description']))
        <p class="text-2xs text-foreground">
            {{ $mega_menu_item['description'] }}
        </p>
    @endif
</div>
