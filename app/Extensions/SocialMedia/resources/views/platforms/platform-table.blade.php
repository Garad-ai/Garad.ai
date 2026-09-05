@php

    //Replicating table styles from table component
    $base_class = 'rounded-xl transition-colors';

    $variations = [
        'variant' => [
            'solid' => 'rounded-card bg-card-background pt-1 group-[&[data-view-mode=grid]]:bg-transparent',
            'outline' => 'rounded-card border border-card-border pt-1 group-[&[data-view-mode=grid]]:border-0',
            'shadow' => ' rounded-card shadow-card bg-card-background pt-1 group-[&[data-view-mode=grid]]:shadow-none group-[&[data-view-mode=grid]]:bg-transparent',
            'outline-shadow' => 'rounded-card border border-card-border pt-1 shadow-card bg-card-background',
            'plain' => '',
        ],
    ];

    $variant =
        isset($variant) && isset($variations['variant'][$variant])
            ? $variations['variant'][$variant]
            : $variations['variant'][Theme::getSetting('defaultVariations.table.variant', 'outline')];

    $class = @twMerge($base_class, $variant);
@endphp

<div class="mt-10">
    <h3 class="mb-7">
        @lang('Accounts')
    </h3>

    @include('social-media::platforms.platform-table-search')

    {{--	@if ($items?->count() || !request('search')) --}}
    <div class="{{ $class }}">
        <div
            class="lqd-social-posts-head grid gap-x-4 border-b px-4 py-3 text-4xs font-medium uppercase leading-tight tracking-wider text-foreground/50 [grid-template-columns:3fr_repeat(2,minmax(0,1fr))_100px_1fr] group-[&[data-view-mode=grid]]:hidden">
            <span>
                {{ __('Name') }}
            </span>

            <span>
                {{ __('Status') }}
            </span>

            <span>
                {{ __('Creation Date') }}
            </span>

            <span>
                {{ __('Platform') }}
            </span>

            <span class="text-end">
                {{ __('Actions') }}
            </span>
        </div>

        @if ($items->count())
            <div
                class="lqd-posts-list lqd-social-media-posts-list group-[&[data-view-mode=grid]]:grid group-[&[data-view-mode=grid]]:grid-cols-2 group-[&[data-view-mode=grid]]:gap-5 md:group-[&[data-view-mode=grid]]:grid-cols-3 lg:group-[&[data-view-mode=grid]]:grid-cols-4 lg:group-[&[data-view-mode=grid]]:gap-8 xl:group-[&[data-view-mode=grid]]:grid-cols-5"
                id="lqd-posts-list"
            >
                @foreach ($items as $item)
                    @include('social-media::platforms.platform-table-item', ['item' => $item])
                @endforeach
            </div>
        @else
            <div class="flex justify-center text-center">
                <p class="p-2">
                    {{ __('No posts found.') }}
                </p>
            </div>
        @endif
    </div>
    {{--	@endif --}}
</div>
